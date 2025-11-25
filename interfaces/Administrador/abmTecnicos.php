<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 3; // Admin
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$nombreAdmin = $_SESSION['nombre'] ?? 'Administrativo';

// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// ===== Helpers =====
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function back_with($qs){ header('Location: abmTecnicos.php?'.$qs); exit; }
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

// ===== Catálogos =====
$estudios = fetch_rows($conn, "SELECT id_estudio, nombre FROM estudios ORDER BY nombre");

// ===== UI State / Flash =====
$action = qget('action','list'); // list | new | edit
$search = trim(qget('q',''));
$id     = (int)qget('id',0);

$status = qget('status'); // created | updated | deleted | reactivated | error
$msg    = qget('msg');
$flashText = [
  'created'=>'Técnico creado con éxito.',
  'updated'=>'Técnico modificado con éxito.',
  'deleted'=>'Técnico eliminado con éxito.',
  'reactivated'=>'Técnico reactivado con éxito.',
  'error'  => ($msg ?: 'Ocurrió un error. Intentalo nuevamente.')
][$status] ?? null;
$flashKind = [
  'created'=>'success',
  'updated'=>'success',
  'deleted'=>'warning',
  'reactivated'=>'success',
  'error'=>'danger'
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
    $genero = trim($_POST['genero'] ?? '');
    // $recurso_nombre = trim($_POST['recurso_nombre'] ?? '');
    //$id_estudios = $_POST['id_estudios'] ?? [];
    //$id_estudios = array_map('intval', $id_estudios);
    $id_estudios = (int)($_POST['id_estudios'] ?? 0); // CAMBIAR: de array a int
    $img_dni_base64 = null;

    // Procesar imagen DNI
    if (isset($_FILES['img_dni']) && $_FILES['img_dni']['error'] === UPLOAD_ERR_OK) {
      $file = $_FILES['img_dni'];
      $maxSize = 2 * 1024 * 1024;
      $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
      if ($file['size'] > $maxSize) {
        back_with('status=error&msg=Imagen%20muy%20grande.%20M%C3%A1ximo%202MB');
      }
      if (!in_array($file['type'], $allowedTypes)) {
        back_with('status=error&msg=Tipo%20de%20imagen%20no%20permitido.%20Usa%20JPEG,%20PNG%20o%20GIF');
      }
      $img_dni_base64 = 'data:' . $file['type'] . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
    }

    if ($nombre===''||$apellido===''||$email===''||$password===''){
  back_with('status=error&msg='.rawurlencode('Completá nombre, apellido, email y contraseña'));
}

    if ($id_estudios === 0) {
  back_with('status=error&msg='.rawurlencode('Seleccioná un estudio'));
}

    // Email duplicado
    $s=$conn->prepare("SELECT 1 FROM usuarios WHERE email=? LIMIT 1");
    $s->bind_param('s',$email); $s->execute();
    if ($s->get_result()->num_rows>0){ $s->close(); back_with('status=error&msg=Email%20ya%20registrado'); }
    $s->close();

    try{
      $conn->begin_transaction();

      // Llamar al SP con el nombre del recurso
      $hash = password_hash($password, PASSWORD_BCRYPT);
      // Llamar al SP SIN el nombre del recurso (asumiendo que el SP crea el usuario sin recurso)
$st = $conn->prepare("CALL insertar_usuario_tecnico(?, ?, ?, ?, ?, ?, ?, NULL)"); // CAMBIAR: quitar $recurso_nombre, poner NULL
$st->bind_param('ssssiss', $nombre, $apellido, $email, $hash, $activo, $genero, $img_dni_base64);
$st->execute();

// Obtener los IDs devueltos por el SP
$result = $st->get_result();
if($result && $row = $result->fetch_assoc()){
  $id_usuario = $row['id_usuario'];
  $id_tecnico = $row['id_tecnico'];
}
$st->close();

if(!isset($id_tecnico) || !$id_tecnico) throw new Exception('No se pudo crear el técnico');

// AGREGAR: Generar nombre automático y crear recurso con tipo 'tecnico'
$recurso_nombre_auto = "Recurso Técnico - " . $id_tecnico;
$st_rec = $conn->prepare("INSERT INTO recursos (nombre, tipo) VALUES (?, 'tecnico')");
$st_rec->bind_param('s', $recurso_nombre_auto);
$st_rec->execute();
$id_recurso = $conn->insert_id;
$st_rec->close();

// AGREGAR: Asociar recurso al técnico
$st_mr = $conn->prepare("UPDATE tecnicos SET id_recurso = ? WHERE id_tecnico = ?");
$st_mr->bind_param('ii', $id_recurso, $id_tecnico);
$st_mr->execute();
$st_mr->close();

// Insertar en tecnico_estudio (CAMBIAR: usar int en lugar de foreach)
$st = $conn->prepare("INSERT INTO tecnico_estudio (id_tecnico, id_estudio) VALUES (?, ?)");
$st->bind_param('ii', $id_tecnico, $id_estudios); // $id_estudios es int
$st->execute();
$st->close();

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
    $genero = trim($_POST['genero'] ?? '');
    // $recurso_nombre = trim($_POST['recurso_nombre'] ?? '');
    // $id_estudios = $_POST['id_estudios'] ?? [];
    //$id_estudios = array_map('intval', $id_estudios);
    $id_estudios = (int)($_POST['id_estudios'] ?? 0);
    $img_dni_base64 = null;

    // Procesar imagen DNI
    if (isset($_FILES['img_dni']) && $_FILES['img_dni']['error'] === UPLOAD_ERR_OK) {
      $file = $_FILES['img_dni'];
      $maxSize = 2 * 1024 * 1024;
      $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
      if ($file['size'] > $maxSize) {
        back_with('status=error&msg=Imagen%20muy%20grande.%20M%C3%A1ximo%202MB');
      }
      if (!in_array($file['type'], $allowedTypes)) {
        back_with('status=error&msg=Tipo%20de%20imagen%20no%20permitido.%20Usa%20JPEG,%20PNG%20o%20GIF');
      }
      $img_dni_base64 = 'data:' . $file['type'] . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
    }

    if(!$id_usuario || $nombre===''||$apellido===''||$email===''){
  back_with('status=error&msg=Datos%20incompletos');
}

if ($id_estudios === 0) {
  back_with('status=error&msg='.rawurlencode('Seleccioná un estudio'));
}

    // Email en uso
    $s=$conn->prepare("SELECT 1 FROM usuarios WHERE email=? AND id_usuario<>? LIMIT 1");
    $s->bind_param('si',$email,$id_usuario); $s->execute();
    if ($s->get_result()->num_rows>0){ $s->close(); back_with('status=error&msg=Email%20ya%20en%20uso'); }
    $s->close();

    try{
      $conn->begin_transaction();

      // UPDATE usuarios
      $updateUsuarioSql = "UPDATE usuarios SET nombre=?, apellido=?, email=?, activo=?, genero=?";
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
        $types .= 's';
      }

      $updateUsuarioSql .= " WHERE id_usuario=? AND id_rol=4";
      $params[] = $id_usuario;
      $types .= 'i';

      $st = $conn->prepare($updateUsuarioSql);
      $st->bind_param($types, ...$params);
      $ok = $st->execute();
      $st->close();
      if(!$ok) throw new Exception('No se pudo actualizar usuario');

      // Actualizar tecnico_estudio (CAMBIAR: usar int en lugar de foreach)
$st = $conn->prepare("DELETE FROM tecnico_estudio WHERE id_tecnico=(SELECT id_tecnico FROM tecnicos WHERE id_usuario=?)");
$st->bind_param('i', $id_usuario);
$st->execute();
$st->close();
$st = $conn->prepare("INSERT INTO tecnico_estudio (id_tecnico, id_estudio) VALUES ((SELECT id_tecnico FROM tecnicos WHERE id_usuario=?), ?)");
$st->bind_param('ii', $id_usuario, $id_estudios); // $id_estudios es int
$st->execute();
$st->close();

      $conn->commit();
      back_with('status=updated');
    }catch(Throwable $e){
      $conn->rollback();
      back_with('status=error&msg='.rawurlencode($e->getMessage()));
    }
  }

  if ($form==='delete'){
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    
    if(!$id_usuario){
      back_with('status=error&msg=ID%20inv%C3%A1lido');
    }

    try{
      $conn->begin_transaction();

      // Borrado lógico: marcar como inactivo
      $st = $conn->prepare("UPDATE usuarios SET activo = 0 WHERE id_usuario = ? AND id_rol = 4");
      $st->bind_param('i', $id_usuario);
      $ok = $st->execute();
      $st->close();

      if(!$ok) throw new Exception('No se pudo desactivar el técnico');

      $conn->commit();
      back_with('status=deleted');
    }catch(Throwable $e){
      $conn->rollback();
      back_with('status=error&msg='.rawurlencode($e->getMessage()));
    }
  }

  // EN CASO DE QUERER AGREGAR LA FUNCION DE REACTICAR UNA CUENTA QUE FUE REGISTRADA ANTERIORMENTE
  //if ($form==='reactivate'){
  //  $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    
  //  if(!$id_usuario){
  //    back_with('status=error&msg=ID%20inv%C3%A1lido');
  //  }

  //  try{
  //    $conn->begin_transaction();

      // Reactivar técnico
  //    $st = $conn->prepare("UPDATE usuarios SET activo = 1 WHERE id_usuario = ? AND id_rol = 4");
  //    $st->bind_param('i', $id_usuario);
  //    $ok = $st->execute();
  //    $st->close();

  //    if(!$ok) throw new Exception('No se pudo reactivar el técnico');

  //    $conn->commit();
  //    back_with('status=reactivated');
  //  }catch(Throwable $e){
  //    $conn->rollback();
  //    back_with('status=error&msg='.rawurlencode($e->getMessage()));
  //  }
  //}

} // 👈 CIERRA el if ($_SERVER['REQUEST_METHOD']==='POST')

// ===== Carga edición =====
$edit = null;
$editEstudios = [];
if ($action==='edit' && $id>0){
  $edit = fetch_one($conn, "
    SELECT u.id_usuario,u.nombre,u.apellido,u.email,u.activo,u.genero,u.img_dni,
           t.id_tecnico, r.nombre AS recurso_nombre
    FROM usuarios u
    LEFT JOIN tecnicos t ON t.id_usuario=u.id_usuario
    LEFT JOIN recursos r ON t.id_recurso=r.id_recurso
    WHERE u.id_usuario=? AND u.id_rol=4
    LIMIT 1
  ",[$id],'i');
  
  if($edit){
    $editEstudios = fetch_rows($conn, "
  SELECT id_estudio FROM tecnico_estudio WHERE id_tecnico=?
", [$edit['id_tecnico']], 'i');
$editEstudios = !empty($editEstudios) ? (int)$editEstudios[0]['id_estudio'] : 0; // CAMBIAR: tomar el primero como int
  }else{
    $action='list';
  }
}

// ===== Listado =====
$rows=[];
if ($action==='list'){
  if ($search!==''){
    $like='%'.$search.'%';
    $s=$conn->prepare("
      SELECT u.id_usuario,u.nombre,u.apellido,u.email,u.activo,u.fecha_creacion,
             t.id_tecnico
      FROM usuarios u
      LEFT JOIN tecnicos t ON t.id_usuario=u.id_usuario
      WHERE u.id_rol=4 AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ?)
      ORDER BY u.apellido,u.nombre
      LIMIT 200
    ");
    $s->bind_param('sss',$like,$like,$like);
  } else {
    $s=$conn->prepare("
      SELECT u.id_usuario,u.nombre,u.apellido,u.email,u.activo,u.fecha_creacion,
             t.id_tecnico
      FROM usuarios u
      LEFT JOIN tecnicos t ON t.id_usuario=u.id_usuario
      WHERE u.id_rol=4
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
<title>ABM Técnicos | Gestión de turnos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="/css/administrativo.css">  
<style>
/* ===== Estilos en línea con tus otras pantallas ===== */
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
  <h1>ABM Técnicos</h1>

  <?php if ($flashText): ?>
    <div class="card" style="padding:12px;border-left:4px solid <?= $flashKind==='danger'?'#ef4444':($flashKind==='warning'?'#f59e0b':'#22c55e') ?>">
      <strong><?= esc($flashText) ?></strong>
    </div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
    <?php if ($action==='list'): ?>
      <form id="searchForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <input type="text" id="buscador" name="q" placeholder="Buscar nombre, apellido o email" style="min-width:280px"/>
  <a class="btn btn-sm" href="abmTecnicos.php?action=new"><i class="fa fa-user-gear"></i> Nuevo técnico</a>
</form>

      <a class="btn-outline btn-sm" href="principalAdmi.php"><i class="fa fa-arrow-left"></i> Volver</a>
    <?php else: ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-sm" href="abmTecnicos.php"><i class="fa fa-list"></i> Volver al listado</a>
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
            <th>#Técnico</th>
            <th>Apellido y Nombre</th>
            <th>Email</th>
            <th>Estado</th>
            <th>Creación</th>
            <th style="width:220px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" style="color:#666">No hay técnicos cargados.</td></tr>
          <?php else: foreach($rows as $t): ?>
            <tr>
              <td><?= (int)$t['id_usuario'] ?></td>
              <td><?= esc($t['id_tecnico'] ?? '-') ?></td>
              <td><?= esc($t['apellido'].', '.$t['nombre']) ?></td>
              <td><?= esc($t['email']) ?></td>
              <td><?= (int)$t['activo'] ? '<span class="badge on">Activo</span>' : '<span class="badge off">Inactivo</span>' ?></td>
              <td><?= esc($t['fecha_creacion'] ?? '-') ?></td>
              <td>
                <a class="btn-outline btn-sm" href="abmTecnicos.php?action=edit&id=<?= (int)$t['id_usuario'] ?>"><i class="fa fa-pen"></i> Modificar</a>
                <form style="display:inline" method="post" onsubmit="return confirm('¿Eliminar este técnico?')">
                  <input type="hidden" name="form_action" value="delete"/>
                  <input type="hidden" name="id_usuario" value="<?= (int)$t['id_usuario'] ?>"/>
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
      <h2 style="margin-bottom:10px"><i class="fa fa-user-gear"></i> Nuevo técnico</h2>
      <form method="post" autocomplete="off" enctype="multipart/form-data">
        <input type="hidden" name="form_action" value="create"/>
        <div class="form-grid">
          <div><label>Nombre</label><input type="text" name="nombre" required></div>
          <div><label>Apellido</label><input type="text" name="apellido" required></div>
          <div class="full"><label>Email</label><input type="email" name="email" required></div>
          <div><label>Contraseña</label><input type="password" name="password" required></div>
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
          <!-- QUITAR: Todo el div de Recurso -->
<div class="full">
  <label>Estudios</label>
  <select name="id_estudios" required> <!-- CAMBIAR: quitar multiple y [] -->
    <option value="">Seleccionar estudio...</option>
    <?php foreach($estudios as $e): ?>
      <option value="<?= (int)$e['id_estudio'] ?>"><?= esc($e['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <!-- QUITAR: El small de múltiples selecciones -->
</div>
          <div class="full"><label><input type="checkbox" name="activo" checked> Activo</label></div>
        </div>
        <div class="form-actions">
          <a class="btn-outline btn-sm" href="abmTecnicos.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar</button>
        </div>
      </form>
    </div>

  <?php elseif ($action==='edit' && $edit): ?>
    <div class="card">
      <h2 style="margin-bottom:10px"><i class="fa fa-user-pen"></i> Modificar técnico</h2>
      <form method="post" autocomplete="off" enctype="multipart/form-data">
        <input type="hidden" name="form_action" value="update">
        <input type="hidden" name="id_usuario" value="<?= (int)$edit['id_usuario'] ?>">
        <div class="form-grid">
          <div><label>Nombre</label><input type="text" name="nombre" value="<?= esc($edit['nombre']) ?>" required></div>
          <div><label>Apellido</label><input type="text" name="apellido" value="<?= esc($edit['apellido']) ?>" required></div>
          <div class="full"><label>Email</label><input type="email" name="email" value="<?= esc($edit['email']) ?>" required></div>
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
          <!-- QUITAR: Todo el div de Recurso -->
<div class="full">
  <label>Estudios</label>
  <select name="id_estudios" required> <!-- CAMBIAR: quitar multiple y [] -->
    <option value="">Seleccionar estudio...</option>
    <?php foreach($estudios as $e): ?>
      <option value="<?= (int)$e['id_estudio'] ?>" <?= ($editEstudios == $e['id_estudio']) ? 'selected' : '' ?>><?= esc($e['nombre']) ?></option> <!-- CAMBIAR: comparación con == en lugar de in_array -->
    <?php endforeach; ?>
  </select>
  <!-- QUITAR: El small de múltiples selecciones -->
</div>
          <div class="full"><label><input type="checkbox" name="activo" <?= ((int)$edit['activo']===1)?'checked':'' ?>> Activo</label></div>
        </div>
        <div class="form-actions">
          <a class="btn-outline btn-sm" href="abmTecnicos.php"><i class="fa fa-xmark"></i> Cancelar</a>
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
  const response = await fetch('/interfaces/buscarTecnicos.php?q=' + encodeURIComponent(q));
  const html = await response.text();
  tbody.innerHTML = html;
});
</script>
</body>
</html>

