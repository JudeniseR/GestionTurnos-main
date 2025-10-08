<?php
/******************************
 * ABM Pacientes (singular DB)
 ******************************/

// ===== CONFIG TABLAS (ajusta si difieren) =====
define('T_USUARIO',   'usuario');
define('T_PACIENTE',  'pacientes');
define('T_MEDICO',    'medicos');      // por si se usa en joins
define('T_AFILIADOS', 'afiliados');   // esta queda en plural

// ===== Seguridad / Sesión =====
$rol_requerido = 3; // Admin
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$nombreAdmin = $_SESSION['nombre'] ?? 'Admin';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// ===== Helpers =====
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function back_with($qs){ header('Location: abmPacientes.php?'.$qs); exit; }
function qget($k,$d=null){ return isset($_GET[$k])?$_GET[$k]:$d; }

function fetch_rows(mysqli $c, string $sql, array $p=[], string $t=''){
  $o=[]; $st=$c->prepare($sql); if(!$st) return $o;
  if($p) $st->bind_param($t, ...$p);
  $st->execute(); $r=$st->get_result();
  if($r) while($row=$r->fetch_assoc()) $o[]=$row;
  $st->close(); return $o;
}
function fetch_one(mysqli $c, string $sql, array $p=[], string $t=''){
  $st=$c->prepare($sql); if(!$st) return null;
  if($p) $st->bind_param($t, ...$p);
  $st->execute(); $r=$st->get_result();
  $row = $r? $r->fetch_assoc() : null;
  $st->close(); return $row;
}

// ===== UI State / Flash =====
$action = qget('action','list'); // list | new | edit
$search = trim(qget('q',''));
$id     = (int)qget('id',0);

$status = qget('status'); // created | updated | deleted | error
$msg    = qget('msg');
$flashText = [
  'created'=>'Paciente creado con éxito.',
  'updated'=>'Paciente modificado con éxito.',
  'deleted'=>'Paciente eliminado con éxito.',
  'error'  => ($msg ?: 'Ocurrió un error. Intentalo nuevamente.')
][$status] ?? null;
$flashKind = [
  'created'=>'#22c55e','updated'=>'#22c55e','deleted'=>'#f59e0b','error'=>'#ef4444'
][$status] ?? '#22c55e';

// ===== Catálogos fijos =====
$TIPOS_DOC = ['DNI','LE','LC','Pasaporte'];
$ESTADOS_CIVIL = ['soltero','casado','divorciado','viudo','conviviente','otro'];

// ===== Catálogos desde AFILIADOS =====
$OS_OPTIONS       = array_map(fn($r)=>$r['v'], fetch_rows($conn,"SELECT DISTINCT cobertura_salud AS v FROM ".T_AFILIADOS." WHERE cobertura_salud IS NOT NULL AND cobertura_salud<>'' ORDER BY v"));
$SECCIONALES      = array_map(fn($r)=>$r['v'], fetch_rows($conn,"SELECT DISTINCT seccional AS v FROM ".T_AFILIADOS." WHERE seccional IS NOT NULL AND seccional<>'' ORDER BY v"));
$TIPOBEN_OPTIONS  = array_map(fn($r)=>$r['v'], fetch_rows($conn,"SELECT DISTINCT tipo_beneficiario AS v FROM ".T_AFILIADOS." WHERE tipo_beneficiario IS NOT NULL AND tipo_beneficiario<>'' ORDER BY v"));
$ESTADOAF_OPTIONS = array_map(fn($r)=>$r['v'], fetch_rows($conn,"SELECT DISTINCT estado AS v FROM ".T_AFILIADOS." WHERE estado IS NOT NULL AND estado<>'' ORDER BY v"));

// ===== Acciones (POST) =====
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $form = $_POST['form_action'] ?? '';

  if ($form==='create'){
    // Usuario
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $activo   = isset($_POST['activo']) ? 1 : 0;

    // Paciente
    $tipo_documento  = trim($_POST['tipo_documento'] ?? '');
    $nro_documento   = trim($_POST['nro_documento'] ?? '');
    $fecha_nacimiento= trim($_POST['fecha_nacimiento'] ?? '');
    $direccion       = trim($_POST['direccion'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');
    $estado_civil    = trim($_POST['estado_civil'] ?? '');

    // (Selecciones manuales; no obligatorias para guardar, pero podés validarlas si querés)
    $obra_social     = trim($_POST['obra_social'] ?? '');
    $numero_afiliado = trim($_POST['numero_afiliado'] ?? '');
    $seccional       = trim($_POST['seccional'] ?? '');
    $tipo_benef      = trim($_POST['tipo_beneficiario'] ?? '');
    $estado_afiliado = trim($_POST['estado_afiliado'] ?? '');

    if ($nombre===''||$apellido===''||$email===''||$password===''||$nro_documento===''||$tipo_documento===''){
      back_with('status=error&msg='.rawurlencode('Completá nombre, apellido, email, contraseña, tipo y número de documento'));
    }

    // Documento debe existir en afiliados
    $af = fetch_one($conn,"SELECT 1 FROM ".T_AFILIADOS." WHERE numero_documento=? LIMIT 1",[$nro_documento],'s');
    if(!$af){ back_with('status=error&msg='.rawurlencode('El documento no existe en AFILIADOS')); }

    // email duplicado
    $dup = fetch_one($conn,"SELECT 1 FROM ".T_USUARIO." WHERE email=? LIMIT 1",[$email],'s');
    if ($dup){ back_with('status=error&msg=Email%20ya%20registrado'); }

    try{
      $conn->begin_transaction();

      // usuario (id_rol=1 paciente)
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $st=$conn->prepare("INSERT INTO ".T_USUARIO." (nombre,apellido,email,password_hash,id_rol,activo) VALUES (?,?,?,?,1,?)");
      $st->bind_param('ssssi',$nombre,$apellido,$email,$hash,$activo);
      $ok=$st->execute(); $id_usuario=$conn->insert_id; $st->close();
      if(!$ok) throw new Exception('No se pudo crear usuario');

      // paciente
      $st=$conn->prepare("INSERT INTO ".T_PACIENTE." (id_usuario,tipo_documento,nro_documento,fecha_nacimiento,direccion,telefono,estado_civil)
                          VALUES (?,?,?,?,?,?,?)");
      $st->bind_param('issssss',$id_usuario,$tipo_documento,$nro_documento,$fecha_nacimiento,$direccion,$telefono,$estado_civil);
      $ok=$st->execute(); $st->close();
      if(!$ok) throw new Exception('No se pudo crear paciente');

      $conn->commit();
      back_with('status=created');
    }catch(Throwable $e){
      $conn->rollback();
      back_with('status=error&msg='.rawurlencode($e->getMessage()));
    }
  }

  if ($form==='update'){
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);

    // Usuario
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $activo   = isset($_POST['activo']) ? 1 : 0;

    // Paciente
    $tipo_documento  = trim($_POST['tipo_documento'] ?? '');
    $nro_documento   = trim($_POST['nro_documento'] ?? '');
    $fecha_nacimiento= trim($_POST['fecha_nacimiento'] ?? '');
    $direccion       = trim($_POST['direccion'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');
    $estado_civil    = trim($_POST['estado_civil'] ?? '');

    if(!$id_usuario || $nombre===''||$apellido===''||$email===''||$nro_documento===''||$tipo_documento===''){
      back_with('status=error&msg=Datos%20incompletos');
    }

    // Documento debe existir en afiliados
    $af = fetch_one($conn,"SELECT 1 FROM ".T_AFILIADOS." WHERE numero_documento=? LIMIT 1",[$nro_documento],'s');
    if(!$af){ back_with('status=error&msg='.rawurlencode('El documento no existe en AFILIADOS')); }

    // email duplicado en otro
    $dup = fetch_one($conn,"SELECT 1 FROM ".T_USUARIO." WHERE email=? AND id_usuario<>? LIMIT 1",[$email,$id_usuario],'si');
    if ($dup){ back_with('status=error&msg=Email%20ya%20en%20uso'); }

    try{
      $conn->begin_transaction();

      // usuario
      if($password!==''){
        $hash=password_hash($password,PASSWORD_BCRYPT);
        $st=$conn->prepare("UPDATE ".T_USUARIO." SET nombre=?,apellido=?,email=?,password_hash=?,activo=? WHERE id_usuario=? AND id_rol=1");
        $st->bind_param('ssssii',$nombre,$apellido,$email,$hash,$activo,$id_usuario);
      }else{
        $st=$conn->prepare("UPDATE ".T_USUARIO." SET nombre=?,apellido=?,email=?,activo=? WHERE id_usuario=? AND id_rol=1");
        $st->bind_param('sssii',$nombre,$apellido,$email,$activo,$id_usuario);
      }
      $ok=$st->execute(); $st->close();
      if(!$ok) throw new Exception('No se pudo actualizar usuario');

      // paciente
      $st=$conn->prepare("UPDATE ".T_PACIENTE."
                            SET tipo_documento=?, nro_documento=?, fecha_nacimiento=?, direccion=?, telefono=?, estado_civil=?
                          WHERE id_usuario=?");
      $st->bind_param('ssssssi',$tipo_documento,$nro_documento,$fecha_nacimiento,$direccion,$telefono,$estado_civil,$id_usuario);
      $ok=$st->execute(); $st->close();
      if(!$ok) throw new Exception('No se pudo actualizar paciente');

      $conn->commit();
      back_with('status=updated');
    }catch(Throwable $e){
      $conn->rollback();
      back_with('status=error&msg='.rawurlencode($e->getMessage()));
    }
  }

  if ($form==='delete'){
    $id_usuario=(int)($_POST['id_usuario'] ?? 0);
    if(!$id_usuario) back_with('status=error');

    try{
      $conn->begin_transaction();

      // Borramos paciente (si no hay FK cascade)
      $st=$conn->prepare("DELETE FROM ".T_PACIENTE." WHERE id_usuario=?");
      $st->bind_param('i',$id_usuario); $st->execute(); $st->close();

      // Borramos usuario rol paciente
      $st=$conn->prepare("DELETE FROM ".T_USUARIO." WHERE id_usuario=? AND id_rol=1");
      $st->bind_param('i',$id_usuario);
      $ok=$st->execute(); $st->close();

      $conn->commit();
      back_with('status='.($ok?'deleted':'error'));
    }catch(Throwable $e){
      $conn->rollback();
      back_with('status=error&msg='.rawurlencode($e->getMessage()));
    }
  }
}

// ===== Carga para edición =====
$edit = null;
if ($action==='edit' && $id>0){
  $edit = fetch_one($conn, "
    SELECT 
      u.id_usuario, u.nombre, u.apellido, u.email, u.activo,
      p.tipo_documento, p.nro_documento, p.fecha_nacimiento, p.direccion, p.telefono, p.estado_civil
    FROM ".T_PACIENTE." p
    JOIN ".T_USUARIO." u ON u.id_usuario=p.id_usuario
    WHERE u.id_usuario=? AND u.id_rol=1
    LIMIT 1
  ",[$id],'i');
  if(!$edit) $action='list';
}

// ===== Listado =====
$rows=[];
if ($action==='list'){
  if ($search!==''){
    $like='%'.$search.'%';
    $rows = fetch_rows($conn, "
      SELECT 
        u.id_usuario, u.nombre, u.apellido, u.email, u.activo, u.fecha_creacion,
        p.nro_documento
      FROM ".T_PACIENTE." p
      JOIN ".T_USUARIO." u ON u.id_usuario=p.id_usuario
      WHERE u.id_rol=1 AND (
            u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR p.nro_documento LIKE ?
      )
      ORDER BY u.apellido,u.nombre
      LIMIT 200
    ", [$like,$like,$like,$like], 'ssss');
  } else {
    $rows = fetch_rows($conn, "
      SELECT 
        u.id_usuario, u.nombre, u.apellido, u.email, u.activo, u.fecha_creacion,
        p.nro_documento
      FROM ".T_PACIENTE." p
      JOIN ".T_USUARIO." u ON u.id_usuario=p.id_usuario
      WHERE u.id_rol=1
      ORDER BY u.apellido,u.nombre
      LIMIT 200
    ");
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>ABM Pacientes | Gestión de turnos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{ --brand:#1e88e5; --brand-dark:#1565c0; --ok:#22c55e; --warn:#f59e0b; --bad:#ef4444; --bgcard: rgba(255,255,255,.92); --border:#e5e7eb;}
body{
  font-family: Arial, sans-serif;
  background: url("https://i.pinimg.com/1200x/9b/e2/12/9be212df4fc8537ddc31c3f7fa147b42.jpg") no-repeat center/cover fixed;
  color:#222
}
nav{background:#fff;padding:12px 28px;box-shadow:0 4px 10px rgba(0,0,0,.08);position:sticky;top:0;z-index:10}
.nav-inner{display:flex;align-items:center;justify-content:space-between}
.nav-links{display:flex;gap:20px;align-items:center}
nav a{color:var(--brand);text-decoration:none;font-weight:bold}
nav a:hover{text-decoration:underline}
.btn{border:none;border-radius:8px;background:var(--brand);color:#fff;padding:8px 14px;cursor:pointer;font-weight:bold;text-decoration:none;display:inline-flex;gap:8px;align-items:center}
.btn:hover{background:var(--brand-dark)}
.btn-outline{background:#fff;color:#111;border:1px solid var(--border)}
.btn-danger{background:var(--bad); color:#fff}
.btn-sm{font-size:.9rem;padding:6px 10px}
.container{padding:32px 18px;max-width:1100px;margin:0 auto}
h1{color:#f5f8fa;text-shadow:1px 1px 3px rgba(0,0,0,.5);margin-bottom:22px;font-size:2rem}
.card{background:var(--bgcard);backdrop-filter:blur(3px);border-radius:16px;padding:16px;box-shadow:0 8px 16px rgba(0,0,0,.12);margin-bottom:18px;border:1px solid rgba(0,0,0,.03)}
.table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
.table th,.table td{padding:10px;border-bottom:1px solid #e8e8e8;text-align:left}
.table thead th{background:#f8fafc;color:#111}
.badge{padding:4px 8px;border-radius:999px;font-size:.78rem;color:#fff;display:inline-block}
.badge.on{background:var(--ok)} .badge.off{background:#9ca3af}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.form-grid .full{grid-column:1 / -1}
label{display:block;font-weight:700;margin-bottom:6px}
input[type="text"],input[type="email"],input[type="password"],input[type="date"],select{
  width:100%;padding:10px;border:1px solid var(--border);border-radius:10px;background:#fff
}
.form-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
.small{color:#6b7280;font-size:.9rem}
.backbar{display:flex;gap:10px;margin:8px 0 16px}
</style>
</head>
<body>
<nav>
  <div class="nav-inner">
    <div class="nav-links">
      <a href="principalAdmi.php"><i class="fa fa-house"></i> Inicio</a>
      <a href="abmPacientes.php"><i class="fa fa-list"></i> Pacientes</a>
    </div>
    <div class="nav-links">
      <span style="color:#333;font-weight:bold">Bienvenido, <?= esc($nombreAdmin) ?></span>
      <a class="btn" href="../../Logica/General/cerrarSesion.php" title="Salir"><i class="fa fa-right-from-bracket"></i> Cerrar sesión</a>
    </div>
  </div>
</nav>

<main class="container">
  <h1>ABM Pacientes</h1>

  <?php if ($flashText): ?>
    <div class="card" style="padding:12px;border-left:4px solid <?= esc($flashKind) ?>">
      <strong><?= esc($flashText) ?></strong>
    </div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
    <?php if ($action==='list'): ?>
      <form method="get" action="abmPacientes.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="action" value="list"/>
        <input type="text" name="q" value="<?= esc($search) ?>" placeholder="Buscar nombre, email o documento" style="min-width:280px"/>
        <button class="btn-outline btn-sm" type="submit"><i class="fa fa-search"></i> Buscar</button>
        <a class="btn btn-sm" href="abmPacientes.php?action=new"><i class="fa fa-hospital-user"></i> Nuevo paciente</a>
      </form>
      <a class="btn-outline btn-sm" href="principalAdmi.php"><i class="fa fa-arrow-left"></i> Volver</a>
    <?php else: ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-sm" href="abmPacientes.php"><i class="fa fa-list"></i> Volver al listado</a>
        <a class="btn-outline btn-sm" href="principalAdmi.php"><i class="fa fa-house"></i> Ir al principal</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($action==='list'): ?>
    <!-- LISTADO -->
    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>#Usuario</th>
            <th>Apellido y Nombre</th>
            <th>Email</th>
            <th>Documento</th>
            <th>Estado</th>
            <th>Creación</th>
            <th style="width:220px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" style="color:#666">No hay pacientes cargados.</td></tr>
          <?php else: foreach($rows as $p): ?>
            <tr>
              <td><?= (int)$p['id_usuario'] ?></td>
              <td><?= esc($p['apellido'].', '.$p['nombre']) ?></td>
              <td><?= esc($p['email']) ?></td>
              <td><?= esc($p['nro_documento'] ?? '-') ?></td>
              <td><?= (int)$p['activo'] ? '<span class="badge on">Activo</span>' : '<span class="badge off">Inactivo</span>' ?></td>
              <td><?= esc($p['fecha_creacion']) ?></td>
              <td>
                <a class="btn-outline btn-sm" href="abmPacientes.php?action=edit&id=<?= (int)$p['id_usuario'] ?>"><i class="fa fa-pen"></i> Modificar</a>
                <form style="display:inline" method="post" onsubmit="return confirm('¿Eliminar este paciente?')">
                  <input type="hidden" name="form_action" value="delete"/>
                  <input type="hidden" name="id_usuario" value="<?= (int)$p['id_usuario'] ?>"/>
                  <button class="btn-danger btn-sm" type="submit"><i class="fa fa-trash"></i> Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($action==='new'): ?>
    <!-- ALTA -->
    <div class="card">
      <h2 style="margin-bottom:10px"><i class="fa fa-hospital-user"></i> Nuevo paciente</h2>
      <form method="post" autocomplete="off" class="form-grid">
        <input type="hidden" name="form_action" value="create"/>

        <!-- Usuario -->
        <div><label>Nombre</label><input type="text" name="nombre" required></div>
        <div><label>Apellido</label><input type="text" name="apellido" required></div>
        <div class="full"><label>Email</label><input type="email" name="email" required></div>
        <div><label>Contraseña</label><input type="password" name="password" required></div>
        <div class="full"><label><input type="checkbox" name="activo" checked> Activo</label></div>

        <!-- Paciente -->
        <div>
          <label>Tipo doc.</label>
          <select name="tipo_documento" required>
            <option value="">Seleccionar…</option>
            <?php foreach($TIPOS_DOC as $td): ?>
              <option value="<?= esc($td) ?>"><?= esc($td) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>N° documento</label>
          <input type="text" name="nro_documento" required>
        </div>
        <div>
          <label>Fecha nacimiento</label>
          <input type="date" name="fecha_nacimiento">
        </div>
        <div>
          <label>Teléfono</label>
          <input type="text" name="telefono">
        </div>
        <div class="full">
          <label>Dirección</label>
          <input type="text" name="direccion">
        </div>
        <div>
          <label>Estado civil</label>
          <select name="estado_civil">
            <option value="">Seleccionar…</option>
            <?php foreach($ESTADOS_CIVIL as $ec): ?>
              <option value="<?= esc($ec) ?>"><?= esc(ucfirst($ec)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Obra social (solo selección manual, sin autocompletar) -->
        <div class="full" style="margin-top:4px;border-top:1px solid #e5e7eb;padding-top:10px">
          <strong>Datos de la obra social</strong>
        </div>

        <div>
          <label>Obra social</label>
          <select name="obra_social">
            <option value="">Seleccionar…</option>
            <?php foreach($OS_OPTIONS as $opt): ?>
              <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>N° Afiliado</label>
          <input type="text" name="numero_afiliado" value="">
        </div>
        <div>
          <label>Seccional</label>
          <select name="seccional">
            <option value="">Seleccionar…</option>
            <?php foreach($SECCIONALES as $sec): ?>
              <option value="<?= esc($sec) ?>"><?= esc($sec) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Tipo beneficiario</label>
          <select name="tipo_beneficiario">
            <option value="">Seleccionar…</option>
            <?php foreach($TIPOBEN_OPTIONS as $tb): ?>
              <option value="<?= esc($tb) ?>"><?= esc($tb) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="full">
          <label>Estado afiliado</label>
          <select name="estado_afiliado">
            <option value="">Seleccionar…</option>
            <?php foreach($ESTADOAF_OPTIONS as $ea): ?>
              <option value="<?= esc($ea) ?>"><?= esc($ea) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-actions full">
          <a class="btn-outline btn-sm" href="abmPacientes.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar</button>
        </div>
      </form>
    </div>

  <?php elseif ($action==='edit' && $edit): ?>
    <!-- EDICIÓN -->
    <div class="card">
      <h2 style="margin-bottom:10px"><i class="fa fa-user-pen"></i> Modificar paciente</h2>
      <form method="post" autocomplete="off" class="form-grid">
        <input type="hidden" name="form_action" value="update">
        <input type="hidden" name="id_usuario" value="<?= (int)$edit['id_usuario'] ?>">

        <div><label>Nombre</label><input type="text" name="nombre" value="<?= esc($edit['nombre']) ?>" required></div>
        <div><label>Apellido</label><input type="text" name="apellido" value="<?= esc($edit['apellido']) ?>" required></div>
        <div class="full"><label>Email</label><input type="email" name="email" value="<?= esc($edit['email']) ?>" required></div>
        <div><label>Nueva contraseña (opcional)</label><input type="password" name="password" placeholder="Dejar en blanco para no cambiar"></div>
        <div class="full"><label><input type="checkbox" name="activo" <?= ((int)$edit['activo']===1)?'checked':'' ?>> Activo</label></div>

        <div>
          <label>Tipo doc.</label>
          <select name="tipo_documento" required>
            <option value="">Seleccionar…</option>
            <?php foreach($TIPOS_DOC as $td): ?>
              <option value="<?= esc($td) ?>" <?= ($edit['tipo_documento']===$td?'selected':'') ?>><?= esc($td) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>N° documento</label>
          <input type="text" name="nro_documento" value="<?= esc($edit['nro_documento'] ?? '') ?>" required>
        </div>
        <div>
          <label>Fecha nacimiento</label>
          <input type="date" name="fecha_nacimiento" value="<?= esc($edit['fecha_nacimiento'] ?? '') ?>">
        </div>
        <div>
          <label>Teléfono</label>
          <input type="text" name="telefono" value="<?= esc($edit['telefono'] ?? '') ?>">
        </div>
        <div class="full">
          <label>Dirección</label>
          <input type="text" name="direccion" value="<?= esc($edit['direccion'] ?? '') ?>">
        </div>
        <div>
          <label>Estado civil</label>
          <select name="estado_civil">
            <option value="">Seleccionar…</option>
            <?php foreach($ESTADOS_CIVIL as $ec): ?>
              <option value="<?= esc($ec) ?>" <?= ($edit['estado_civil']===$ec?'selected':'') ?>><?= esc(ucfirst($ec)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Misma sección de obra social (solo selects; NO autocompleta) -->
        <div class="full" style="margin-top:4px;border-top:1px solid #e5e7eb;padding-top:10px">
          <strong>Datos de la obra social</strong>
        </div>

        <div>
          <label>Obra social</label>
          <select name="obra_social">
            <option value="">Seleccionar…</option>
            <?php foreach($OS_OPTIONS as $opt): ?>
              <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>N° Afiliado</label><input type="text" name="numero_afiliado" value=""></div>
        <div>
          <label>Seccional</label>
          <select name="seccional">
            <option value="">Seleccionar…</option>
            <?php foreach($SECCIONALES as $sec): ?>
              <option value="<?= esc($sec) ?>"><?= esc($sec) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Tipo beneficiario</label>
          <select name="tipo_beneficiario">
            <option value="">Seleccionar…</option>
            <?php foreach($TIPOBEN_OPTIONS as $tb): ?>
              <option value="<?= esc($tb) ?>"><?= esc($tb) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="full">
          <label>Estado afiliado</label>
          <select name="estado_afiliado">
            <option value="">Seleccionar…</option>
            <?php foreach($ESTADOAF_OPTIONS as $ea): ?>
              <option value="<?= esc($ea) ?>"><?= esc($ea) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-actions full">
          <a class="btn-outline btn-sm" href="abmPacientes.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar cambios</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

</main>
</body>
</html>
