<?php
/******************************
 * ABM Pacientes - Validación de afiliado primero
 ******************************/

// ===== CONFIG TABLAS =====
define('T_USUARIO',   'usuarios');
define('T_PACIENTE',  'pacientes');
define('T_AFILIADOS', 'afiliados');

// ===== Seguridad / Sesión =====
$rol_requerido = 5; // Administrativo
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$nombreAdmin = $_SESSION['nombre'] ?? 'Administrativo';

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
$action = qget('action','list'); // list | new | edit | verify
$search = trim(qget('q',''));
$id     = (int)qget('id',0);

$status = qget('status');
$msg    = qget('msg');
$flashText = [
  'created'=>'Paciente creado con éxito.',
  'updated'=>'Paciente modificado con éxito.',
  'deleted'=>'Paciente eliminado con éxito.',
  'not_found'=>'El número de afiliado no existe en el sistema.',
  'already_registered'=>'Este afiliado ya está registrado como paciente.',
  'inactive_affiliate'=>'Este afiliado está inactivo. No se puede registrar.',
  'error'  => ($msg ?: 'Ocurrió un error. Intentalo nuevamente.')
][$status] ?? null;
$flashKind = [
  'created'=>'#22c55e','updated'=>'#22c55e','deleted'=>'#f59e0b',
  'not_found'=>'#ef4444','already_registered'=>'#f59e0b',
  'inactive_affiliate'=>'#ef4444','error'=>'#ef4444'
][$status] ?? '#22c55e';

// ===== Catálogos =====
$TIPOS_DOC = ['DNI','LE','LC','Pasaporte'];
$ESTADOS_CIVIL = ['soltero','casado','divorciado','viudo','conviviente','otro'];

// Obtener valores ENUM de la tabla afiliados
$COBERTURA_SALUD = ['UOM','OSDE','Swiss Medical','Galeno','Otra'];
$TIPO_BENEFICIARIO = ['titular','conyuge','conviviente','hijo menor','hijo mayor'];

// ===== Validación de afiliado (PASO 1) =====
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form_action'] ?? '')==='verify_afiliado'){
  $numero_afiliado = trim($_POST['numero_afiliado'] ?? '');
  
  if($numero_afiliado === ''){
    back_with('status=error&msg='.rawurlencode('Debe ingresar un número de afiliado'));
  }
  
  // Buscar en tabla afiliados
  $afiliado = fetch_one($conn, "
    SELECT 
      id,
      numero_afiliado, 
      numero_documento, 
      cobertura_salud, 
      seccional, 
      tipo_beneficiario, 
      estado,
      cursa_estudios
    FROM ".T_AFILIADOS." 
    WHERE numero_afiliado = ? 
    LIMIT 1
  ", [$numero_afiliado], 's');
  
  if(!$afiliado){
    back_with('status=not_found');
  }
  
  // Verificar que esté activo
  if($afiliado['estado'] !== 'activo'){
    back_with('status=inactive_affiliate');
  }
  
  // Verificar si ya está registrado como paciente
  $yaRegistrado = fetch_one($conn, "
    SELECT 1 FROM ".T_PACIENTE." 
    WHERE nro_documento = ? 
    LIMIT 1
  ", [$afiliado['numero_documento']], 's');
  
  if($yaRegistrado){
    back_with('status=already_registered');
  }
  
  // ✅ Afiliado válido y activo, redirigir al formulario completo
  $params = http_build_query([
    'action' => 'new',
    'numero_afiliado' => $afiliado['numero_afiliado'],
    'nro_documento' => $afiliado['numero_documento'],
    'obra_social' => $afiliado['cobertura_salud'],
    'seccional' => $afiliado['seccional'] ?? '',
    'tipo_beneficiario' => $afiliado['tipo_beneficiario'],
    'cursa_estudios' => $afiliado['cursa_estudios']
  ]);
  header('Location: abmPacientes.php?'.$params);
  exit;
}

// ===== Crear paciente (PASO 2) =====
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form_action'] ?? '')==='create'){
  $nombre   = trim($_POST['nombre'] ?? '');
  $apellido = trim($_POST['apellido'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');
  $activo   = isset($_POST['activo']) ? 1 : 0;
  
  $tipo_documento  = trim($_POST['tipo_documento'] ?? '');
  $nro_documento   = trim($_POST['nro_documento'] ?? '');
  $fecha_nacimiento= trim($_POST['fecha_nacimiento'] ?? '');
  $direccion       = trim($_POST['direccion'] ?? '');
  $telefono        = trim($_POST['telefono'] ?? '');
  $estado_civil    = trim($_POST['estado_civil'] ?? '');

  $genero = trim($_POST['genero'] ?? '');
  $img_dni_base64 = null;  // Inicializar
  $token_qr = bin2hex(random_bytes(16));

  // Procesar imagen DNI si se subió
  if (isset($_FILES['img_dni']) && $_FILES['img_dni']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['img_dni'];
    $maxSize = 2 * 1024 * 1024;  // 2MB
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if ($file['size'] > $maxSize) {
      back_with('status=error&msg=Imagen%20muy%20grande.%20M%C3%A1ximo%202MB');
    }
    if (!in_array($file['type'], $allowedTypes)) {
      back_with('status=error&msg=Tipo%20de%20imagen%20no%20permitido.%20Usa%20JPEG,%20PNG%20o%20GIF');
    } 
    // Convertir a base64
    $img_dni_base64 = 'data:' . $file['type'] . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
  }


  if ($nombre===''||$apellido===''||$email===''||$password===''||$nro_documento===''||$tipo_documento===''){
    back_with('status=error&msg='.rawurlencode('Completá todos los campos obligatorios'));
  }

  // Re-verificar que el documento exista en afiliados y esté activo
  $af = fetch_one($conn,"
    SELECT estado FROM ".T_AFILIADOS." 
    WHERE numero_documento=? 
    LIMIT 1
  ",[$nro_documento],'s');
  
  if(!$af){ 
    back_with('status=error&msg='.rawurlencode('El documento ya no existe en AFILIADOS')); 
  }
  if($af['estado'] !== 'activo'){
    back_with('status=error&msg='.rawurlencode('El afiliado está inactivo'));
  }

  // Email duplicado
  $dup = fetch_one($conn,"SELECT 1 FROM ".T_USUARIO." WHERE email=? LIMIT 1",[$email],'s');
  if ($dup){ 
    back_with('status=error&msg='.rawurlencode('El email ya está registrado')); 
  }

  try{
    $conn->begin_transaction();
    // Llamar al procedimiento almacenado para insertar usuario y paciente
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $st = $conn->prepare("
    CALL insertar_usuario_paciente(
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
        @p_id_paciente
    )
");


   $st->bind_param(
    'ssssssssssssss',
    $nombre,
    $apellido,
    $email,
    $hash,
    $activo,          // Puede ir como 's' sin problema
    $genero,
    $tipo_documento,
    $nro_documento,
    $fecha_nacimiento,
    $direccion,
    $telefono,
    $estado_civil,
    $token_qr,
    $img_dni_base64
);


    $ok = $st->execute();
    $st->close();

$res = $conn->query("SELECT @p_id_paciente AS id_paciente");
$id_paciente = $res->fetch_assoc()['id_paciente'];

    if(!$ok) throw new Exception('No se pudo crear el paciente');
      $conn->commit();
      back_with('status=created');
  }catch(Throwable $e){
    $conn->rollback();
    back_with('status=error&msg='.rawurlencode($e->getMessage()));
  }
}

// ===== Actualizar paciente =====
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form_action'] ?? '')==='update'){
  $id_usuario = (int)($_POST['id_usuario'] ?? 0);
  $nombre   = trim($_POST['nombre'] ?? '');
  $apellido = trim($_POST['apellido'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');
  $activo   = isset($_POST['activo']) ? 1 : 0;
  
  $tipo_documento  = trim($_POST['tipo_documento'] ?? '');
  $nro_documento   = trim($_POST['nro_documento'] ?? '');
  $fecha_nacimiento= trim($_POST['fecha_nacimiento'] ?? '');
  $direccion       = trim($_POST['direccion'] ?? '');
  $telefono        = trim($_POST['telefono'] ?? '');
  $estado_civil    = trim($_POST['estado_civil'] ?? '');

  $genero = trim($_POST['genero'] ?? '');
  $img_dni_base64 = null;  // Inicializar

  // Procesar imagen DNI si se subió una nueva
  if (isset($_FILES['img_dni']) && $_FILES['img_dni']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['img_dni'];
    $maxSize = 2 * 1024 * 1024;  // 2MB
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

    if ($file['size'] > $maxSize) {
      back_with('status=error&msg=Imagen%20muy%20grande.%20M%C3%A1ximo%202MB');
    }
    if (!in_array($file['type'], $allowedTypes)) {
      back_with('status=error&msg=Tipo%20de%20imagen%20no%20permitido.%20Usa%20JPEG,%20PNG%20o%20GIF');
    }

    // Convertir a base64
    $img_dni_base64 = 'data:' . $file['type'] . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
  }

  if(!$id_usuario || $nombre===''||$apellido===''||$email===''||$nro_documento===''||$tipo_documento===''){
    back_with('status=error&msg='.rawurlencode('Datos incompletos'));
  }

  $dup = fetch_one($conn,"SELECT 1 FROM ".T_USUARIO." WHERE email=? AND id_usuario<>? LIMIT 1",[$email,$id_usuario],'si');
  if ($dup){ back_with('status=error&msg='.rawurlencode('Email ya en uso')); }

  try{
    $conn->begin_transaction();

    // UPDATE de usuarios: incluir genero siempre, img_dni solo si hay nueva
    $updateUsuarioSql = "UPDATE ".T_USUARIO." SET nombre=?, apellido=?, email=?, activo=?, genero=?";
    $params = [$nombre, $apellido, $email, $activo, $genero];
    $types = 'sssis';

    if($password !== ''){
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $updateUsuarioSql .= ", password_hash=?";
      $params[] = $hash;
      $types .= 's';
    }

    if($img_dni_base64 !== null){
      $updateUsuarioSql .= ", img_dni=?";
      $params[] = $img_dni_base64;
      $types .= 's';  // Base64 es string
    }

    $updateUsuarioSql .= " WHERE id_usuario=? AND id_rol=1";
    $params[] = $id_usuario;
    $types .= 'i';

    $st = $conn->prepare($updateUsuarioSql);
    $st->bind_param($types, ...$params);
    $ok = $st->execute();
    $st->close();
    if(!$ok) throw new Exception('No se pudo actualizar usuario');

    // UPDATE de pacientes (sin cambios)
    $st=$conn->prepare("UPDATE ".T_PACIENTE."
                          SET tipo_documento=?, fecha_nacimiento=?, direccion=?, telefono=?, email=?, estado_civil=?
                        WHERE id_usuario=?");
    $st->bind_param('ssssssi',$tipo_documento,$fecha_nacimiento,$direccion,$telefono,$email,$estado_civil,$id_usuario);
    $ok=$st->execute(); 
    $st->close();
    if(!$ok) throw new Exception('No se pudo actualizar paciente');

    $conn->commit();
    back_with('status=updated');
  }catch(Throwable $e){
    $conn->rollback();
    back_with('status=error&msg='.rawurlencode($e->getMessage()));
  }
}

// ===== Eliminar =====
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form_action'] ?? '')==='delete'){
  $id_usuario=(int)($_POST['id_usuario'] ?? 0);
  if(!$id_usuario) back_with('status=error');
  try{
    $conn->begin_transaction();
    // Borrado lógico: marcar como inactivo
    $st=$conn->prepare("UPDATE ".T_USUARIO." SET activo=0 WHERE id_usuario=? AND id_rol=1");
    $st->bind_param('i',$id_usuario);
    $ok=$st->execute(); 
    $st->close();
    $conn->commit();
    back_with('status='.($ok?'deleted':'error')); 
  }catch(Throwable $e){
    $conn->rollback();
    back_with('status=error&msg='.rawurlencode($e->getMessage()));
  }
}

// ===== Carga para edición =====
$edit = null;
$editAfiliado = null;
if ($action==='edit' && $id>0){
  $edit = fetch_one($conn, "
  SELECT 
    u.id_usuario, u.nombre, u.apellido, u.email, u.activo, u.genero, u.img_dni,
    p.tipo_documento, p.nro_documento, p.fecha_nacimiento, p.direccion, p.telefono, p.estado_civil
  FROM ".T_PACIENTE." p
  JOIN ".T_USUARIO." u ON u.id_usuario=p.id_usuario
  WHERE u.id_usuario=? AND u.id_rol=1
  LIMIT 1
",[$id],'i');
  
  if($edit){
    // Cargar datos del afiliado para mostrar info
    $editAfiliado = fetch_one($conn, "
      SELECT numero_afiliado, cobertura_salud, seccional, tipo_beneficiario, estado, cursa_estudios
      FROM ".T_AFILIADOS."
      WHERE numero_documento=?
      LIMIT 1
    ", [$edit['nro_documento']], 's');
  }else{
    $action='list';
  }
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
<link rel="stylesheet" href="/css/administrativo.css">  
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
input:read-only{background:#f3f4f6;color:#6b7280}
.form-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
.highlight-box{background:#fef3c7;border:2px solid #f59e0b;padding:12px;border-radius:8px;margin-bottom:12px}
.info-box{background:#dbeafe;border:2px solid #3b82f6;padding:12px;border-radius:8px;margin-bottom:12px}
.verify-step{text-align:center;padding:40px 20px}
.verify-step input{max-width:400px;margin:0 auto 16px;font-size:1.1rem;text-align:center}
.info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;font-size:.9rem}
.info-item{padding:8px;background:#f9fafb;border-radius:6px}
.info-item strong{display:block;color:#6b7280;font-size:.8rem;margin-bottom:2px}
</style>
</head>
<body>
<!-- ===== NAV nuevo ===== -->
<?php include('navAdministrativo.php'); ?>

<!-- NAV viejo
<nav>
  <div class="nav-inner">
    <div class="nav-links">
      <a href="principalAdministrativo.php"><i class="fa fa-house"></i> Inicio</a>
    </div>
    <div class="nav-links">
      <span style="color:#333;font-weight:bold">Bienvenido, <?= esc($nombreAdmin) ?></span>
      <a class="btn" href="../../Logica/General/cerrarSesion.php"><i class="fa fa-right-from-bracket"></i> Cerrar sesión</a>
    </div>
  </div>
</nav>
-->

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
        <input type="text" id="buscador" name="q" value="<?= esc($search) ?>" placeholder="Buscar nombre, email o documento" style="min-width:280px"/>
        <a class="btn btn-sm" href="abmPacientes.php?action=verify"><i class="fa fa-user-plus"></i> Nuevo paciente</a>
      </form>
      <a class="btn-outline btn-sm" href="principalAdministrativo.php"><i class="fa fa-arrow-left"></i> Volver</a>
    <?php else: ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-sm" href="abmPacientes.php"><i class="fa fa-list"></i> Volver al listado</a>
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

  <?php elseif ($action==='verify'): ?>
    <!-- PASO 1: Verificar número de afiliado -->
    <div class="card">
      <div class="verify-step">
        <i class="fa fa-id-card" style="font-size:4rem;color:var(--brand);margin-bottom:20px"></i>
        <h2 style="margin-bottom:12px">Validar afiliado</h2>
        <p style="color:#6b7280;margin-bottom:28px">Ingresá el número de afiliado para verificar si está registrado en el sistema</p>
        
        <form method="post">
          <input type="hidden" name="form_action" value="verify_afiliado"/>
          <input type="text" name="numero_afiliado" placeholder="Ej: 123456789" required autofocus/>
          <div class="form-actions" style="justify-content:center">
            <a class="btn-outline btn-sm" href="abmPacientes.php"><i class="fa fa-xmark"></i> Cancelar</a>
            <button class="btn" type="submit"><i class="fa fa-search"></i> Verificar afiliado</button>
          </div>
        </form>
      </div>
    </div>

  <?php elseif ($action==='new'): ?>
     <?php
    // Proteger acceso directo - validar parámetros requeridos
    if(!qget('numero_afiliado') || !qget('nro_documento')){
      back_with('status=error&msg='.rawurlencode('Debe validar el número de afiliado primero'));
    }
    ?>
    <!-- PASO 2: Formulario completo (con datos pre-cargados) -->
    <div class="card">
      <div class="highlight-box">
        <strong><i class="fa fa-check-circle"></i> Afiliado validado correctamente</strong>
        <div class="info-grid" style="margin-top:12px">
          <div class="info-item">
            <strong>N° Afiliado</strong>
            <?= esc(qget('numero_afiliado')) ?>
          </div>
          <div class="info-item">
            <strong>N° Documento</strong>
            <?= esc(qget('nro_documento')) ?>
          </div>
          <div class="info-item">
            <strong>Obra Social</strong>
            <?= esc(qget('obra_social')) ?>
          </div>
          <div class="info-item">
            <strong>Tipo Beneficiario</strong>
            <?= esc(ucwords(str_replace('_',' ',qget('tipo_beneficiario')))) ?>
          </div>
          <?php if(qget('seccional')): ?>
          <div class="info-item">
            <strong>Seccional</strong>
            <?= esc(qget('seccional')) ?>
          </div>
          <?php endif; ?>
          <?php if(qget('cursa_estudios')==='1'): ?>
          <div class="info-item">
            <strong>Cursa Estudios</strong>
            Sí
          </div>
          <?php endif; ?>
        </div>
      </div>

      <h2 style="margin-bottom:10px"><i class="fa fa-hospital-user"></i> Completar datos del paciente</h2>
      <form method="post" autocomplete="off" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="form_action" value="create"/>
        <input type="hidden" name="nro_documento" value="<?= esc(qget('nro_documento')) ?>"/>

        <!-- Usuario -->
        <div><label>Nombre *</label><input type="text" name="nombre" required autofocus></div>
        <div><label>Apellido *</label><input type="text" name="apellido" required></div>
        <div class="full"><label>Email *</label><input type="email" name="email" required></div>
        <div><label>Contraseña *</label><input type="password" name="password" required></div>
        <div>
          <label>Género</label>
          <select name="genero">
            <option value="">Seleccionar...</option>
            <option value="Masculino">Masculino</option>
            <option value="Femenino">Femenino</option>
            <option value="Otro">Otro</option>
          </select>
        </div>
        <div class="full">
          <label>Imagen DNI (opcional)</label>
          <input type="file" name="img_dni" accept="image/*" onchange="previewImage(event)">
          <small style="color:#6b7280;font-size:.8rem">Sube una imagen del DNI (máx. 2MB)</small>
          <img id="preview" style="max-width:200px;margin-top:10px;display:none;" alt="Vista previa">
        </div>
        <div class="full"><label><input type="checkbox" name="activo" checked required> Activo</label></div>


        <!-- Paciente -->
        <div>
          <label>Tipo documento *</label>
          <select name="tipo_documento" required>
            <option value="">Seleccionar…</option>
            <?php foreach($TIPOS_DOC as $td): ?>
              <option value="<?= esc($td) ?>" <?= ($td==='DNI'?'selected':'') ?>><?= esc($td) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>N° documento *</label>
          <input type="text" value="<?= esc(qget('nro_documento')) ?>" readonly>
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

        <div class="form-actions full">
          <a class="btn-outline btn-sm" href="abmPacientes.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Crear paciente</button>
        </div>
      </form>
    </div>

  <?php elseif ($action==='edit' && $edit): ?>
    <!-- EDICIÓN -->
    <div class="card">
      <?php if($editAfiliado): ?>
        <div class="info-box">
          <strong><i class="fa fa-info-circle"></i> Información del afiliado</strong>
          <div class="info-grid" style="margin-top:12px">
            <div class="info-item">
              <strong>N° Afiliado</strong>
              <?= esc($editAfiliado['numero_afiliado']) ?>
            </div>
            <div class="info-item">
              <strong>Obra Social</strong>
              <?= esc($editAfiliado['cobertura_salud']) ?>
            </div>
            <div class="info-item">
              <strong>Tipo Beneficiario</strong>
              <?= esc(ucwords(str_replace('_',' ',$editAfiliado['tipo_beneficiario']))) ?>
            </div>
            <div class="info-item">
              <strong>Estado</strong>
              <span class="badge <?= $editAfiliado['estado']==='activo'?'on':'off' ?>">
                <?= esc(ucfirst($editAfiliado['estado'])) ?>
              </span>
            </div>
            <?php if($editAfiliado['seccional']): ?>
            <div class="info-item">
              <strong>Seccional</strong>
              <?= esc($editAfiliado['seccional']) ?>
            </div>
            <?php endif; ?>
            <?php if($editAfiliado['cursa_estudios']): ?>
            <div class="info-item">
              <strong>Cursa Estudios</strong>
              Sí
            </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <h2 style="margin-bottom:10px"><i class="fa fa-user-pen"></i> Modificar paciente</h2>
      <form method="post" autocomplete="off" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="form_action" value="update">
        <input type="hidden" name="id_usuario" value="<?= (int)$edit['id_usuario'] ?>">
        <input type="hidden" name="nro_documento" value="<?= esc($edit['nro_documento']) ?>">

        <div><label>Nombre *</label><input type="text" name="nombre" value="<?= esc($edit['nombre']) ?>" required></div>
        <div><label>Apellido *</label><input type="text" name="apellido" value="<?= esc($edit['apellido']) ?>" required></div>
        <div class="full"><label>Email *</label><input type="email" name="email" value="<?= esc($edit['email']) ?>" required></div>
        <div><label>Nueva contraseña (opcional)</label><input type="password" name="password" placeholder="Dejar en blanco para no cambiar"></div>
        <div>
          <label>Género</label>
          <select name="genero">
            <option value="">Seleccionar...</option>
            <option value="Masculino" <?= ($edit['genero'] === 'Masculino') ? 'selected' : '' ?>>Masculino</option>
            <option value="Femenino" <?= ($edit['genero'] === 'Femenino') ? 'selected' : '' ?>>Femenino</option>
            <option value="Otro" <?= ($edit['genero'] === 'Otro') ? 'selected' : '' ?>>Otro</option>
          </select>
        </div>
        <div class="full">
          <label>Imagen DNI (opcional)</label>
          <input type="file" name="img_dni" accept="image/*" onchange="previewImage(event)">
          <?php if ($edit['img_dni']): ?>
            <p><small>Imagen actual:</small></p>
            <img src="<?= esc($edit['img_dni']) ?>" style="max-width:200px;" alt="DNI actual">
          <?php endif; ?>
          <small style="color:#6b7280;font-size:.8rem">Sube una nueva imagen para reemplazar (máx. 2MB)</small>
          <img id="preview" style="max-width:200px;margin-top:10px;display:none;" alt="Vista previa">
        </div>
        <div class="full"><label><input type="checkbox" name="activo" <?= ((int)$edit['activo']===1)?'checked':'' ?>> Activo</label></div>

        <div>
          <label>Tipo documento *</label>
          <select name="tipo_documento" required>
            <option value="">Seleccionar…</option>
            <?php foreach($TIPOS_DOC as $td): ?>
              <option value="<?= esc($td) ?>" <?= ($edit['tipo_documento']===$td?'selected':'') ?>><?= esc($td) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>N° documento *</label>
          <input type="text" value="<?= esc($edit['nro_documento'] ?? '') ?>" readonly style="background:#f3f4f6">
          <small style="color:#6b7280;font-size:.8rem">No se puede modificar el documento</small>
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

        <div class="form-actions full">
          <a class="btn-outline btn-sm" href="abmPacientes.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar cambios</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

</main>

<script>
  function previewImage(event) {
    const file = event.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('preview').src = e.target.result;
        document.getElementById('preview').style.display = 'block';
      };
      reader.readAsDataURL(file);
    }
  }

  const buscador = document.getElementById('buscador');
const tbody = document.querySelector('tbody');

buscador.addEventListener('input', async () => {
  const q = buscador.value.trim();
  const response = await fetch('/interfaces/buscarPacientes.php?q=' + encodeURIComponent(q));
  const html = await response.text();
  tbody.innerHTML = html;
});

</script>
</body>
</html>