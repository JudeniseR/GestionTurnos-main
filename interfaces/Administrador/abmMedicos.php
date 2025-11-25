<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 3; // Admin
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$nombreAdmin = $_SESSION['nombre'] ?? 'Administrativo';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '-03:00'");

// ===== Helpers =====
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function back_with($qs){ header('Location: abmMedicos.php?'.$qs); exit; }
function qget($k,$d=null){ return isset($_GET[$k])?$_GET[$k]:$d; }

// ===== UI State / Flash =====
$action = qget('action','list'); // list | new | edit
$search = trim(qget('q',''));
$id     = (int)qget('id',0);

$status = qget('status');
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

// ===== Cargar catálogos =====
$especialidades = [];
$recursos = [];
$sedes = [];

// Obtener especialidades
$result = $conn->query("SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad");
while($row = $result->fetch_assoc()) {
  $especialidades[] = $row;
}

// Obtener sedes
$result = $conn->query("SELECT id_sede, nombre FROM sedes ORDER BY nombre");
while($row = $result->fetch_assoc()) {
  $sedes[] = $row;
}

// No necesitamos cargar recursos existentes para este formulario

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
    $genero = trim($_POST['genero'] ?? '');
    
    // Especialidades y datos del recurso
    $especialidades_sel = $_POST['especialidades'] ?? [];
    $nombre_recurso = trim($_POST['nombre_recurso'] ?? '');
    $id_sede = (int)($_POST['id_sede'] ?? 0);

    if ($nombre===''||$apellido===''||$email===''||$password===''||$matricula===''){
      back_with('status=error&msg='.rawurlencode('Completá todos los campos requeridos'));
    }

    if (empty($especialidades_sel)) {
      back_with('status=error&msg='.rawurlencode('Seleccioná al menos una especialidad'));
    }

    // email duplicado
    $s=$conn->prepare("SELECT 1 FROM usuarios WHERE email=? LIMIT 1");
    $s->bind_param('s',$email); $s->execute();
    if ($s->get_result()->num_rows>0){ $s->close(); back_with('status=error&msg=Email%20ya%20registrado'); }
    $s->close();

    // matricula duplicada
    $s=$conn->prepare("SELECT 1 FROM medicos WHERE matricula=? LIMIT 1");
    $s->bind_param('s',$matricula); $s->execute();
    if ($s->get_result()->num_rows>0){ $s->close(); back_with('status=error&msg=Matrícula%20ya%20registrada'); }
    $s->close();

    try{
      $conn->begin_transaction();

      // Llamar al procedimiento almacenado
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $s = $conn->prepare("CALL insertar_usuario_medico(?, ?, ?, ?, 2, ?, ?, NULL, ?, ?)");
      $s->bind_param('ssssssss', $nombre, $apellido, $email, $hash, $activo, $genero, $matricula, $telefono);
      $ok = $s->execute();
      $s->close();
      if(!$ok) throw new Exception('No se pudo crear el médico');

      // Obtener el id_medico recién creado
      $result = $conn->query("SELECT id_medico FROM medicos WHERE matricula='$matricula' LIMIT 1");
      $row = $result->fetch_assoc();
      $id_medico = $row['id_medico'];

      // Insertar especialidades
      $stmt_esp = $conn->prepare("INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES (?, ?)");
      foreach($especialidades_sel as $id_esp) {
        $id_esp = (int)$id_esp;
        $stmt_esp->bind_param('ii', $id_medico, $id_esp);
        $stmt_esp->execute();
      }
      $stmt_esp->close();

      // Crear recurso si se proporcionó una sede (sin requerir nombre manual)
if ($id_sede > 0) {
  // Generar nombre automático: "Recurso Médico - [ID del médico]"
  $nombre_recurso_auto = "Recurso Médico - " . $id_medico;
  
  // Insertar el recurso
  $stmt_rec = $conn->prepare("INSERT INTO recursos (nombre, tipo, id_sede) VALUES (?, 'medico', ?)");
  $stmt_rec->bind_param('si', $nombre_recurso_auto, $id_sede);
  $stmt_rec->execute();
  $id_recurso = $conn->insert_id;
  $stmt_rec->close();
  
  // Asociar el recurso con el médico
  $stmt_mr = $conn->prepare("INSERT INTO medico_recursos (id_medico, id_recurso) VALUES (?, ?)");
  $stmt_mr->bind_param('ii', $id_medico, $id_recurso);
  $stmt_mr->execute();
  $stmt_mr->close();
}

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
    $genero = trim($_POST['genero'] ?? '');
    
    $especialidades_sel = $_POST['especialidades'] ?? [];
    // $nombre_recurso = trim($_POST['nombre_recurso'] ?? '');
    $id_sede = (int)($_POST['id_sede'] ?? 0);

    if(!$id_usuario || $nombre===''||$apellido===''||$email===''||$matricula===''){
      back_with('status=error&msg=Datos%20incompletos');
    }

    if (empty($especialidades_sel)) {
      back_with('status=error&msg='.rawurlencode('Seleccioná al menos una especialidad'));
    }

    // email en uso por otro
    $s=$conn->prepare("SELECT 1 FROM usuarios WHERE email=? AND id_usuario<>? LIMIT 1");
    $s->bind_param('si',$email,$id_usuario); 
    $s->execute();
    if ($s->get_result()->num_rows>0){ $s->close(); back_with('status=error&msg=Email%20ya%20en%20uso'); }
    $s->close();

    // matricula en uso por otro
    $s=$conn->prepare("SELECT id_usuario FROM medicos WHERE matricula=? LIMIT 1");
    $s->bind_param('s',$matricula);
    $s->execute();
    $result = $s->get_result();
    if ($result->num_rows > 0) {
      $row = $result->fetch_assoc();
      if ($row['id_usuario'] != $id_usuario) {
        $s->close();
        back_with('status=error&msg=Matrícula%20ya%20en%20uso');
      }
    }
    $s->close();

    try{
      $conn->begin_transaction();

      // Actualizar usuario
      if($password!==''){
        $hash=password_hash($password,PASSWORD_BCRYPT);
        $s=$conn->prepare("UPDATE usuarios SET nombre=?,apellido=?,email=?,password_hash=?,activo=?,genero=?,id_rol=2 WHERE id_usuario=?");
        $s->bind_param('ssssssi',$nombre,$apellido,$email,$hash,$activo,$genero,$id_usuario);
      }else{
        $s=$conn->prepare("UPDATE usuarios SET nombre=?,apellido=?,email=?,activo=?,genero=?,id_rol=2 WHERE id_usuario=?");
        $s->bind_param('sssssi',$nombre,$apellido,$email,$activo,$genero,$id_usuario);
      }
      $ok=$s->execute(); 
      $s->close();
      if(!$ok) throw new Exception('No se pudo actualizar usuario');

      // Actualizar medicos
      $s=$conn->prepare("SELECT id_medico FROM medicos WHERE id_usuario=? LIMIT 1");
      $s->bind_param('i',$id_usuario); 
      $s->execute();
      $result = $s->get_result();
      $exists = $result->num_rows > 0;
      $id_medico = null;
      if ($exists) {
        $row = $result->fetch_assoc();
        $id_medico = $row['id_medico'];
      }
      $s->close();

      if ($exists){
        $s=$conn->prepare("UPDATE medicos SET matricula=?, telefono=? WHERE id_usuario=?");
        $s->bind_param('ssi',$matricula,$telefono,$id_usuario);
      }else{
        $s=$conn->prepare("INSERT INTO medicos (id_usuario,matricula,telefono) VALUES (?,?,?)");
        $s->bind_param('iss',$id_usuario,$matricula,$telefono);
      }
      $ok=$s->execute(); 
      $s->close();
      if(!$ok) throw new Exception('No se pudo actualizar datos del médico');

      // Si se acababa de crear el registro de medico, obtener su id
      if (!$exists) {
        $result = $conn->query("SELECT id_medico FROM medicos WHERE id_usuario=$id_usuario LIMIT 1");
        $row = $result->fetch_assoc();
        $id_medico = $row['id_medico'];
      }

      // Actualizar especialidades (eliminar todas y reinsertar)
      $conn->query("DELETE FROM medico_especialidad WHERE id_medico=$id_medico");
      $stmt_esp = $conn->prepare("INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES (?, ?)");
      foreach($especialidades_sel as $id_esp) {
        $id_esp = (int)$id_esp;
        $stmt_esp->bind_param('ii', $id_medico, $id_esp);
        $stmt_esp->execute();
      }
      $stmt_esp->close();

      // Actualizar recurso
// Primero eliminamos la asociación anterior si existe
$conn->query("DELETE FROM medico_recursos WHERE id_medico=$id_medico");

// Si hay una sede seleccionada, crear o actualizar el recurso automáticamente
if ($id_sede > 0) {
  // Verificar si ya existe un recurso para este médico
  $result = $conn->query("
    SELECT r.id_recurso 
    FROM recursos r
    INNER JOIN medico_recursos mr ON mr.id_recurso = r.id_recurso
    WHERE mr.id_medico = $id_medico
    LIMIT 1
  ");
  
  if ($result && $result->num_rows > 0) {
    // Actualizar recurso existente (solo sede, nombre ya es automático)
    $row = $result->fetch_assoc();
    $id_recurso = $row['id_recurso'];
    $stmt_rec = $conn->prepare("UPDATE recursos SET id_sede=? WHERE id_recurso=?");
    $stmt_rec->bind_param('ii', $id_sede, $id_recurso);
    $stmt_rec->execute();
    $stmt_rec->close();
  } else {
    // Crear nuevo recurso con nombre automático
    $nombre_recurso_auto = "Recurso Médico - " . $id_medico;
    $stmt_rec = $conn->prepare("INSERT INTO recursos (nombre, tipo, id_sede) VALUES (?, 'medico', ?)");
    $stmt_rec->bind_param('si', $nombre_recurso_auto, $id_sede);
    $stmt_rec->execute();
    $id_recurso = $conn->insert_id;
    $stmt_rec->close();
  }
  
  // Asociar el recurso con el médico
  $stmt_mr = $conn->prepare("INSERT INTO medico_recursos (id_medico, id_recurso) VALUES (?, ?)");
  $stmt_mr->bind_param('ii', $id_medico, $id_recurso);
  $stmt_mr->execute();
  $stmt_mr->close();
}

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

      // Borrado lógico: marcar como inactivo
      $s=$conn->prepare("UPDATE usuarios SET activo=0 WHERE id_usuario=? AND id_rol=2");
      $s->bind_param('i',$id_usuario);
      $ok=$s->execute(); 
      $s->close();

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
$edit_especialidades = [];
$edit_recurso = null;

if ($action==='edit' && $id>0){
  $s=$conn->prepare("
    SELECT u.id_usuario,u.nombre,u.apellido,u.email,u.activo,u.genero,
           m.id_medico,m.matricula,m.telefono
    FROM usuarios u
    LEFT JOIN medicos m ON m.id_usuario=u.id_usuario
    WHERE u.id_usuario=? AND u.id_rol=2
    LIMIT 1
  ");
  $s->bind_param('i',$id); 
  $s->execute();
  $edit=$s->get_result()->fetch_assoc();
  $s->close();
  
  if(!$edit) {
    $action='list';
  } else {
    $id_medico = $edit['id_medico'];
    
    // Cargar especialidades del médico
    $result = $conn->query("SELECT id_especialidad FROM medico_especialidad WHERE id_medico=$id_medico");
    while($row = $result->fetch_assoc()) {
      $edit_especialidades[] = $row['id_especialidad'];
    }
    
    // Cargar recurso del médico (solo uno)
    $result = $conn->query("
      SELECT r.id_recurso, r.nombre, r.id_sede 
      FROM recursos r
      INNER JOIN medico_recursos mr ON mr.id_recurso = r.id_recurso
      WHERE mr.id_medico = $id_medico
      LIMIT 1
    ");
    if ($row = $result->fetch_assoc()) {
      $edit_recurso = $row;
    }
  }
}

// ===== Listado =====
$rows=[];
if ($action==='list'){
  if ($search!==''){
    $like='%'.$search.'%';
    $s=$conn->prepare("
      SELECT u.id_usuario,u.nombre,u.apellido,u.email,u.activo,u.fecha_creacion,
             m.matricula,m.telefono,
             GROUP_CONCAT(DISTINCT e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ', ') as especialidades
      FROM usuarios u
      LEFT JOIN medicos m ON m.id_usuario=u.id_usuario
      LEFT JOIN medico_especialidad me ON me.id_medico=m.id_medico
      LEFT JOIN especialidades e ON e.id_especialidad=me.id_especialidad
      WHERE u.id_rol=2
        AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR m.matricula LIKE ? OR m.telefono LIKE ?)
      GROUP BY u.id_usuario
      ORDER BY u.apellido,u.nombre
      LIMIT 200
    ");
    $s->bind_param('sssss',$like,$like,$like,$like,$like);
} else {
    $s=$conn->prepare("
      SELECT u.id_usuario,u.nombre,u.apellido,u.email,u.activo,u.fecha_creacion,
             m.matricula,m.telefono,
             GROUP_CONCAT(DISTINCT e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ', ') as especialidades
      FROM usuarios u
      LEFT JOIN medicos m ON m.id_usuario=u.id_usuario
      LEFT JOIN medico_especialidad me ON me.id_medico=m.id_medico
      LEFT JOIN especialidades e ON e.id_especialidad=me.id_especialidad
      WHERE u.id_rol=2
      GROUP BY u.id_usuario
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
<link rel="stylesheet" href="/css/administrativo.css">  
<style>
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
.container{padding:32px 18px;max-width:1400px;margin:0 auto}
h1{color:#f5f8fa;text-shadow:1px 1px 3px rgba(0,0,0,.5);margin-bottom:22px;font-size:2.1rem}
h2{margin-bottom:16px;color:#111}
.card{background:var(--bgcard);backdrop-filter:blur(3px);border-radius:16px;padding:16px;box-shadow:0 8px 16px rgba(0,0,0,.12);margin-bottom:18px;border:1px solid rgba(0,0,0,.03)}
.table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
.table th,.table td{padding:10px;border-bottom:1px solid #e8e8e8;text-align:left}
.table thead th{background:#f8fafc;color:#111}
.badge{padding:4px 8px;border-radius:999px;font-size:.78rem;color:#fff;display:inline-block}
.badge.on{background:var(--ok)} .badge.off{background:#9ca3af}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.form-grid .full{grid-column:1 / -1}
label{display:block;font-weight:700;margin-bottom:6px}
input[type="text"],input[type="email"],input[type="password"],select{width:100%;padding:10px;border:1px solid var(--border);border-radius:10px}
.form-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:16px}
.small{color:#6b7280;font-size:.9rem}
.checkbox-group{max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:12px;background:#fff}
.checkbox-item{margin-bottom:8px}
.checkbox-item label{font-weight:400;display:flex;align-items:center;gap:8px}
.checkbox-item input[type="checkbox"]{width:auto;margin:0}
</style>
</head>
<body>
<!-- ===== NAV nuevo ===== -->
<?php include('navAdministrador.php'); ?>

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
  <input type="text" id="buscador" name="q" value="<?= esc($search) ?>" placeholder="Buscar nombre, email, matrícula o teléfono" style="min-width:280px"/>
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
            <th>Especialidades</th>
            <th>Estado</th>
            <th style="width:220px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" style="color:#666">No hay médicos cargados.</td></tr>
          <?php else: foreach($rows as $m): ?>
            <tr>
              <td><?= (int)$m['id_usuario'] ?></td>
              <td><?= esc($m['apellido'].', '.$m['nombre']) ?></td>
              <td><?= esc($m['email']) ?></td>
              <td><?= esc($m['matricula'] ?? '-') ?></td>
              <td><small><?= esc($m['especialidades'] ?? 'Sin especialidad') ?></small></td>
              <td><?= (int)$m['activo'] ? '<span class="badge on">Activo</span>' : '<span class="badge off">Inactivo</span>' ?></td>
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
      <h2><i class="fa fa-user-doctor"></i> Nuevo médico</h2>
      <form method="post" autocomplete="off">
        <input type="hidden" name="form_action" value="create"/>
        
        <div class="form-grid">
          <div><label>Nombre *</label><input type="text" name="nombre" required></div>
          <div><label>Apellido *</label><input type="text" name="apellido" required></div>
          <div><label>Email *</label><input type="email" name="email" required></div>
          <div><label>Contraseña *</label><input type="password" name="password" required></div>
          <div><label>Matrícula *</label><input type="text" name="matricula" required></div>
          <div><label>Teléfono</label><input type="text" name="telefono"></div>
          <div><label>Género</label>
            <select name="genero">
              <option value="">Seleccionar...</option>
              <option value="Masculino">Masculino</option>
              <option value="Femenino">Femenino</option>
              <option value="Otro">Otro</option>
            </select>
          </div>
          <div><label><input type="checkbox" name="activo" checked style="width:auto;margin-right:6px"> Activo</label></div>
          
          <div class="full">
            <label>Especialidades * (seleccioná al menos una)</label>
            <div class="checkbox-group">
              <?php foreach($especialidades as $esp): ?>
                <div class="checkbox-item">
                  <label>
                    <input type="checkbox" name="especialidades[]" value="<?= $esp['id_especialidad'] ?>">
                    <?= esc($esp['nombre_especialidad']) ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="full">
  <h3 style="margin-top:8px;margin-bottom:8px;font-size:1.1rem">Recurso médico</h3>
  <p class="small" style="margin-bottom:12px">Seleccioná la sede para crear automáticamente un recurso médico asociado</p>
  <div style="display:grid;grid-template-columns:1fr;gap:12px"> <!-- Cambié a 1 columna ya que solo queda sede -->
    <div>
      <label>Sede</label>
      <select name="id_sede">
        <option value="">Seleccionar sede...</option>
        <?php foreach($sedes as $sede): ?>
          <option value="<?= $sede['id_sede'] ?>"><?= esc($sede['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

        <div class="form-actions">
          <a class="btn-outline btn-sm" href="abmMedicos.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar</button>
        </div>
      </form>
    </div>

  <?php elseif ($action==='edit' && $edit): ?>
    <div class="card">
      <h2><i class="fa fa-user-pen"></i> Modificar médico</h2>
      <form method="post" autocomplete="off">
        <input type="hidden" name="form_action" value="update">
        <input type="hidden" name="id_usuario" value="<?= (int)$edit['id_usuario'] ?>">
        
        <div class="form-grid">
          <div><label>Nombre *</label><input type="text" name="nombre" value="<?= esc($edit['nombre']) ?>" required></div>
          <div><label>Apellido *</label><input type="text" name="apellido" value="<?= esc($edit['apellido']) ?>" required></div>
          <div><label>Email *</label><input type="email" name="email" value="<?= esc($edit['email']) ?>" required></div>
          <div><label>Nueva contraseña (opcional)</label><input type="password" name="password" placeholder="Dejar en blanco para no cambiar"></div>
          <div><label>Matrícula *</label><input type="text" name="matricula" value="<?= esc($edit['matricula'] ?? '') ?>" required></div>
          <div><label>Teléfono</label><input type="text" name="telefono" value="<?= esc($edit['telefono'] ?? '') ?>"></div>
          <div><label>Género</label>
            <select name="genero">
              <option value="">Seleccionar...</option>
              <option value="Masculino" <?= ($edit['genero'] === 'Masculino') ? 'selected' : '' ?>>Masculino</option>
              <option value="Femenino" <?= ($edit['genero'] === 'Femenino') ? 'selected' : '' ?>>Femenino</option>
              <option value="Otro" <?= ($edit['genero'] === 'Otro') ? 'selected' : '' ?>>Otro</option>
            </select>
          </div>
          <div><label><input type="checkbox" name="activo" <?= ((int)$edit['activo']===1)?'checked':'' ?> style="width:auto;margin-right:6px"> Activo</label></div>
          
          <div class="full">
            <label>Especialidades * (seleccioná al menos una)</label>
            <div class="checkbox-group">
              <?php foreach($especialidades as $esp): ?>
                <div class="checkbox-item">
                  <label>
                    <input type="checkbox" name="especialidades[]" value="<?= $esp['id_especialidad'] ?>" 
                      <?= in_array($esp['id_especialidad'], $edit_especialidades) ? 'checked' : '' ?>>
                    <?= esc($esp['nombre_especialidad']) ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="full">
  <h3 style="margin-top:8px;margin-bottom:8px;font-size:1.1rem">Recurso médico</h3>
  <p class="small" style="margin-bottom:12px">Seleccioná la sede para asociar o actualizar automáticamente el recurso médico</p>
  <div style="display:grid;grid-template-columns:1fr;gap:12px"> <!-- Solo una columna para sede -->
    <div>
      <label>Sede</label>
      <select name="id_sede">
        <option value="">Seleccionar sede...</option>
        <?php foreach($sedes as $sede): ?>
          <option value="<?= $sede['id_sede'] ?>" <?= (isset($edit_recurso['id_sede']) && $edit_recurso['id_sede'] == $sede['id_sede']) ? 'selected' : '' ?>>
            <?= esc($sede['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

        <div class="form-actions">
          <a class="btn-outline btn-sm" href="abmMedicos.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar cambios</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

</main>

<script>
  const buscador = document.getElementById('buscador');
const tbody = document.querySelector('tbody');

buscador.addEventListener('input', async () => {
  const q = buscador.value.trim();
  const response = await fetch('/interfaces/buscarMedicos.php?q=' + encodeURIComponent(q));
  const html = await response.text();
  tbody.innerHTML = html;
});
</script>

</body>
</html>