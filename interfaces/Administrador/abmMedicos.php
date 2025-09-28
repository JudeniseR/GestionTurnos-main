<?php
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
function back_with($qs){ header('Location: abmMedicos.php?'.$qs); exit; }
function qget($k,$d=null){ return isset($_GET[$k])?$_GET[$k]:$d; }

// ===== UI State / Flash =====
$action = qget('action','list'); // list | new | edit
$search = trim(qget('q',''));
$id     = (int)qget('id',0);

$status = qget('status'); // created | updated | deleted | error
$msg    = qget('msg');
$flashText = [
  'created'=>'Médico creado con éxito.',
  'updated'=>'Médico modificado con éxito.',
  'deleted'=>'Médico eliminado con éxito.',
  'error'  => ($msg ?: 'Ocurrió un error. Intentalo nuevamente.')
][$status] ?? null;
$flashKind = [
  'created'=>'success','updated'=>'success','deleted'=>'warning','error'=>'danger'
][$status] ?? 'success';

// ===== Acciones (POST) =====
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $form = $_POST['form_action'] ?? '';

  if ($form==='create'){
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $activo   = isset($_POST['activo']) ? 1 : 0;

    $matricula= trim($_POST['matricula'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    if ($nombre===''||$apellido===''||$email===''||$password===''){
      back_with('status=error&msg='.rawurlencode('Completá nombre, apellido, email y contraseña'));
    }

    // email duplicado
    $s=$conn->prepare("SELECT 1 FROM usuarios WHERE email=? LIMIT 1");
    $s->bind_param('s',$email); $s->execute();
    if ($s->get_result()->num_rows>0){ $s->close(); back_with('status=error&msg=Email%20ya%20registrado'); }
    $s->close();

    try{
      $conn->begin_transaction();

      // usuario (id_rol=2 médico)
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $s=$conn->prepare("INSERT INTO usuarios (nombre,apellido,email,password_hash,id_rol,activo) VALUES (?,?,?,?,2,?)");
      $s->bind_param('ssssi',$nombre,$apellido,$email,$hash,$activo);
      $ok=$s->execute(); $id_usuario=$conn->insert_id; $s->close();
      if(!$ok) throw new Exception('No se pudo crear usuario');

      // medicos
      $s=$conn->prepare("INSERT INTO medicos (id_usuario, matricula, telefono) VALUES (?,?,?)");
      $s->bind_param('iss',$id_usuario,$matricula,$telefono);
      $ok=$s->execute(); $s->close();
      if(!$ok) throw new Exception('No se pudo crear registro en medicos');

      $conn->commit();
      back_with('status=created');
    }catch(Throwable $e){
      $conn->rollback();
      back_with('status=error&msg='.rawurlencode($e->getMessage()));
    }
  }

  if ($form==='update'){
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $activo   = isset($_POST['activo']) ? 1 : 0;

    $matricula= trim($_POST['matricula'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    if(!$id_usuario || $nombre===''||$apellido===''||$email===''){
      back_with('status=error&msg=Datos%20incompletos');
    }

    // email en uso por otro
    $s=$conn->prepare("SELECT 1 FROM usuarios WHERE email=? AND id_usuario<>? LIMIT 1");
    $s->bind_param('si',$email,$id_usuario); $s->execute();
    if ($s->get_result()->num_rows>0){ $s->close(); back_with('status=error&msg=Email%20ya%20en%20uso'); }
    $s->close();

    try{
      $conn->begin_transaction();

      // usuario
      if($password!==''){
        $hash=password_hash($password,PASSWORD_BCRYPT);
        $s=$conn->prepare("UPDATE usuarios SET nombre=?,apellido=?,email=?,password_hash=?,activo=?,id_rol=2 WHERE id_usuario=?");
        $s->bind_param('ssssii',$nombre,$apellido,$email,$hash,$activo,$id_usuario);
      }else{
        $s=$conn->prepare("UPDATE usuarios SET nombre=?,apellido=?,email=?,activo=?,id_rol=2 WHERE id_usuario=?");
        $s->bind_param('sssii',$nombre,$apellido,$email,$activo,$id_usuario);
      }
      $ok=$s->execute(); $s->close();
      if(!$ok) throw new Exception('No se pudo actualizar usuario');

      // medicos (si no existe, lo creo)
      $s=$conn->prepare("SELECT 1 FROM medicos WHERE id_usuario=? LIMIT 1");
      $s->bind_param('i',$id_usuario); $s->execute();
      $exists = $s->get_result()->num_rows>0; $s->close();

      if ($exists){
        $s=$conn->prepare("UPDATE medicos SET matricula=?, telefono=? WHERE id_usuario=?");
        $s->bind_param('ssi',$matricula,$telefono,$id_usuario);
      }else{
        $s=$conn->prepare("INSERT INTO medicos (id_usuario,matricula,telefono) VALUES (?,?,?)");
        $s->bind_param('iss',$id_usuario,$matricula,$telefono);
      }
      $ok=$s->execute(); $s->close();
      if(!$ok) throw new Exception('No se pudo actualizar datos del médico');

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
      // primero medicos (por si no hay FK cascade)
      $s=$conn->prepare("DELETE FROM medicos WHERE id_usuario=?");
      $s->bind_param('i',$id_usuario); $s->execute(); $s->close();

      // usuario rol médico
      $s=$conn->prepare("DELETE FROM usuarios WHERE id_usuario=? AND id_rol=2");
      $s->bind_param('i',$id_usuario);
      $ok=$s->execute(); $s->close();

      $conn->commit();
      back_with('status='.($ok?'deleted':'error'));
    }catch(Throwable $e){
      $conn->rollback();
      back_with('status=error&msg='.rawurlencode($e->getMessage()));
    }
  }
}

// ===== Carga edición =====
$edit = null;
if ($action==='edit' && $id>0){
  $s=$conn->prepare("
    SELECT u.id_usuario,u.nombre,u.apellido,u.email,u.activo,
           m.matricula,m.telefono
    FROM usuarios u
    LEFT JOIN medicos m ON m.id_usuario=u.id_usuario
    WHERE u.id_usuario=? AND u.id_rol=2
    LIMIT 1
  ");
  $s->bind_param('i',$id); $s->execute();
  $edit=$s->get_result()->fetch_assoc();
  $s->close();
  if(!$edit) $action='list';
}

// ===== Listado =====
$rows=[];
if ($action==='list'){
  if ($search!==''){
    $like='%'.$search.'%';
    $s=$conn->prepare("
      SELECT u.id_usuario,u.nombre,u.apellido,u.email,u.activo,u.fecha_creacion,
             m.matricula,m.telefono
      FROM usuarios u
      LEFT JOIN medicos m ON m.id_usuario=u.id_usuario
      WHERE u.id_rol=2 AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR m.matricula LIKE ? OR m.telefono LIKE ?)
      ORDER BY u.apellido,u.nombre
      LIMIT 200
    ");
    $s->bind_param('sssss',$like,$like,$like,$like,$like);
  } else {
    $s=$conn->prepare("
      SELECT u.id_usuario,u.nombre,u.apellido,u.email,u.activo,u.fecha_creacion,
             m.matricula,m.telefono
      FROM usuarios u
      LEFT JOIN medicos m ON m.id_usuario=u.id_usuario
      WHERE u.id_rol=2
      ORDER BY u.apellido,u.nombre
      LIMIT 200
    ");
  }
  $s->execute();
  if ($r=$s->get_result()){ while($row=$r->fetch_assoc()) $rows[]=$row; }
  $s->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>ABM Médicos | Gestión de turnos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ===== Mismo diseño que principalAdmi ===== */
*{margin:0;padding:0;box-sizing:border-box}
:root{ --brand:#1e88e5; --brand-dark:#1565c0; --ok:#22c55e; --warn:#f59e0b; --bad:#ef4444; --bgcard: rgba(255,255,255,.92); --border:#e5e7eb;}
body{font-family:Arial,sans-serif;background:url("https://i.pinimg.com/1200x/9b/e2/12/9be212df4fc8537ddc31c3f7fa147b42.jpg") no-repeat center/cover fixed;color:#222}
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
.container{padding:32px 18px;max-width:1200px;margin:0 auto}
h1{color:#f5f8fa;text-shadow:1px 1px 3px rgba(0,0,0,.5);margin-bottom:22px;font-size:2.1rem}
.card{background:var(--bgcard);backdrop-filter:blur(3px);border-radius:16px;padding:16px;box-shadow:0 8px 16px rgba(0,0,0,.12);margin-bottom:18px;border:1px solid rgba(0,0,0,.03)}
.table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
.table th,.table td{padding:10px;border-bottom:1px solid #e8e8e8;text-align:left}
.table thead th{background:#f8fafc;color:#111}
.badge{padding:4px 8px;border-radius:999px;font-size:.78rem;color:#fff;display:inline-block}
.badge.on{background:var(--ok)} .badge.off{background:#9ca3af}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.form-grid .full{grid-column:1 / -1}
label{display:block;font-weight:700;margin-bottom:6px}
input[type="text"],input[type="email"],input[type="password"]{width:100%;padding:10px;border:1px solid var(--border);border-radius:10px}
.form-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
.small{color:#6b7280;font-size:.9rem}
</style>
</head>
<body>
<nav>
  <div class="nav-inner">
    <div class="nav-links">
      <a href="principalAdmi.php"><i class="fa fa-house"></i> Inicio</a>
    </div>
    <div class="nav-links">
      <span style="color:#333;font-weight:bold">Bienvenido, <?= esc($nombreAdmin) ?></span>
      <a class="btn" href="../../Logica/General/cerrarSesion.php"><i class="fa fa-right-from-bracket"></i> Cerrar sesión</a>
    </div>
  </div>
</nav>

<main class="container">
  <h1>ABM Médicos</h1>

  <?php if ($flashText): ?>
    <div class="card" style="padding:12px;border-left:4px solid <?= $flashKind==='danger'?'#ef4444':($flashKind==='warning'?'#f59e0b':'#22c55e') ?>">
      <strong><?= esc($flashText) ?></strong>
    </div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
    <?php if ($action==='list'): ?>
      <form method="get" action="abmMedicos.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="action" value="list"/>
        <input type="text" name="q" value="<?= esc($search) ?>" placeholder="Buscar nombre, email, matrícula o teléfono" style="min-width:280px"/>
        <button class="btn-outline btn-sm" type="submit"><i class="fa fa-search"></i> Buscar</button>
        <a class="btn btn-sm" href="abmMedicos.php?action=new"><i class="fa fa-user-doctor"></i> Nuevo médico</a>
      </form>
      <a class="btn-outline btn-sm" href="principalAdmi.php"><i class="fa fa-arrow-left"></i> Volver</a>
    <?php else: ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-sm" href="abmMedicos.php"><i class="fa fa-list"></i> Volver al listado</a>
        <a class="btn-outline btn-sm" href="principalAdmi.php"><i class="fa fa-house"></i> Ir al principal</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($action==='list'): ?>
    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>#Usuario</th>
            <th>Apellido y Nombre</th>
            <th>Email</th>
            <th>Matrícula</th>
            <th>Teléfono</th>
            <th>Estado</th>
            <th>Creación</th>
            <th style="width:220px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" style="color:#666">No hay médicos cargados.</td></tr>
          <?php else: foreach($rows as $m): ?>
            <tr>
              <td><?= (int)$m['id_usuario'] ?></td>
              <td><?= esc($m['apellido'].', '.$m['nombre']) ?></td>
              <td><?= esc($m['email']) ?></td>
              <td><?= esc($m['matricula'] ?? '-') ?></td>
              <td><?= esc($m['telefono'] ?? '-') ?></td>
              <td><?= (int)$m['activo'] ? '<span class="badge on">Activo</span>' : '<span class="badge off">Inactivo</span>' ?></td>
              <td><?= esc($m['fecha_creacion']) ?></td>
              <td>
                <a class="btn-outline btn-sm" href="abmMedicos.php?action=edit&id=<?= (int)$m['id_usuario'] ?>"><i class="fa fa-pen"></i> Modificar</a>
                <form style="display:inline" method="post" onsubmit="return confirm('¿Eliminar este médico?')">
                  <input type="hidden" name="form_action" value="delete"/>
                  <input type="hidden" name="id_usuario" value="<?= (int)$m['id_usuario'] ?>"/>
                  <button class="btn-danger btn-sm" type="submit"><i class="fa fa-trash"></i> Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($action==='new'): ?>
    <div class="card">
      <h2 style="margin-bottom:10px"><i class="fa fa-user-doctor"></i> Nuevo médico</h2>
      <form method="post" autocomplete="off">
        <input type="hidden" name="form_action" value="create"/>
        <div class="form-grid">
          <div><label>Nombre</label><input type="text" name="nombre" required></div>
          <div><label>Apellido</label><input type="text" name="apellido" required></div>
          <div class="full"><label>Email</label><input type="email" name="email" required></div>
          <div><label>Contraseña</label><input type="password" name="password" required></div>
          <div><label>Matrícula</label><input type="text" name="matricula"></div>
          <div><label>Teléfono</label><input type="text" name="telefono"></div>
          <div class="full"><label><input type="checkbox" name="activo" checked> Activo</label></div>
        </div>
        <div class="form-actions">
          <a class="btn-outline btn-sm" href="abmMedicos.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar</button>
        </div>
      </form>
    </div>

  <?php elseif ($action==='edit' && $edit): ?>
    <div class="card">
      <h2 style="margin-bottom:10px"><i class="fa fa-user-pen"></i> Modificar médico</h2>
      <form method="post" autocomplete="off">
        <input type="hidden" name="form_action" value="update">
        <input type="hidden" name="id_usuario" value="<?= (int)$edit['id_usuario'] ?>">
        <div class="form-grid">
          <div><label>Nombre</label><input type="text" name="nombre" value="<?= esc($edit['nombre']) ?>" required></div>
          <div><label>Apellido</label><input type="text" name="apellido" value="<?= esc($edit['apellido']) ?>" required></div>
          <div class="full"><label>Email</label><input type="email" name="email" value="<?= esc($edit['email']) ?>" required></div>
          <div><label>Nueva contraseña (opcional)</label><input type="password" name="password" placeholder="Dejar en blanco para no cambiar"></div>
          <div><label>Matrícula</label><input type="text" name="matricula" value="<?= esc($edit['matricula'] ?? '') ?>"></div>
          <div><label>Teléfono</label><input type="text" name="telefono" value="<?= esc($edit['telefono'] ?? '') ?>"></div>
          <div class="full"><label><input type="checkbox" name="activo" <?= ((int)$edit['activo']===1)?'checked':'' ?>> Activo</label></div>
        </div>
        <div class="form-actions">
          <a class="btn-outline btn-sm" href="abmMedicos.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar cambios</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

</main>
</body>
</html>
