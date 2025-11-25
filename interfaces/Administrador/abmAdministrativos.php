<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ===== Seguridad / Sesión =====
$rol_requerido = 3; // Admin
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$nombreAdmin = $_SESSION['nombre'] ?? 'Admin';

// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '-03:00'");

// ===== Helpers =====
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function back_with($qs){ header('Location: abmAdministrativos.php?'.$qs); exit; }
function qget($k,$d=null){ return isset($_GET[$k])?$_GET[$k]:$d; }

// ===== UI State / Flash =====
$action = qget('action','list'); // list | new | edit
$search = trim(qget('q',''));
$id     = (int)qget('id',0);

$status = qget('status');
$msg    = qget('msg');
$flashText = [
  'created'=>'Administrativo creado con éxito.',
  'updated'=>'Administrativo modificado con éxito.',
  'deleted'=>'Administrativo eliminado con éxito.',
  'error'  => ($msg ?: 'Ocurrió un error. Intentalo nuevamente.')
][$status] ?? null;
$flashKind = [
  'created'=>'success','updated'=>'success','deleted'=>'warning','error'=>'danger'
][$status] ?? 'success';

// ===== Acciones (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form_action'] ?? '';

    if ($form === 'create') {
        $nombre   = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $activo   = isset($_POST['activo']) ? 1 : 0;
        $telefono = trim($_POST['telefono'] ?? '');
        $genero   = trim($_POST['genero'] ?? '');
        $dni      = trim($_POST['dni'] ?? '');
        $direccion= trim($_POST['direccion'] ?? '');

        if ($nombre === '' || $apellido === '' || $email === '' || $password === '' || $dni === '' || $direccion === '') {
            back_with('status=error&msg=' . rawurlencode('Completá todos los campos requeridos'));
        }

        // Email duplicado
        $s = $conn->prepare("SELECT 1 FROM usuarios WHERE email=? LIMIT 1");
        $s->bind_param('s', $email);
        $s->execute();
        if ($s->get_result()->num_rows > 0) {
            $s->close();
            back_with('status=error&msg=Email%20ya%20registrado');
        }
        $s->close();

        try {
            $conn->begin_transaction();

            // Crear administrativo
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $s = $conn->prepare("CALL sp_crear_administrativo(?, ?, ?, ?, ?, ?, ?, ?)");
            $s->bind_param('ssssssss', $nombre, $apellido, $email, $hash, $genero, $dni, $telefono, $direccion);
            $ok = $s->execute();
            $s->close();

            if (!$ok) throw new Exception('No se pudo crear el administrativo');

            $conn->commit();
            back_with('status=created');

        } catch (Throwable $e) {
            $conn->rollback();
            back_with('status=error&msg=' . rawurlencode($e->getMessage()));
        }

    } elseif ($form === 'update') {
        $id_usuario = (int)($_POST['id_usuario'] ?? 0);
        $nombre     = trim($_POST['nombre'] ?? '');
        $apellido   = trim($_POST['apellido'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = trim($_POST['password'] ?? '');
        $telefono   = trim($_POST['telefono'] ?? '');
        $genero     = trim($_POST['genero'] ?? '');
        $dni        = trim($_POST['dni'] ?? '');
        $direccion  = trim($_POST['direccion'] ?? '');

        if (!$id_usuario || $nombre === '' || $apellido === '' || $email === '') {
            back_with('status=error&msg=Datos%20incompletos');
        }

        // Email duplicado
        $s = $conn->prepare("SELECT 1 FROM usuarios WHERE email=? AND id_usuario<>? LIMIT 1");
        $s->bind_param('si', $email, $id_usuario);
        $s->execute();
        if ($s->get_result()->num_rows > 0) {
            $s->close();
            back_with('status=error&msg=Email%20ya%20en%20uso');
        }
        $s->close();

        try {
            $conn->begin_transaction();

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $s = $conn->prepare("
                    UPDATE usuarios 
                    SET nombre=?, apellido=?, email=?, password_hash=?, genero=? 
                    WHERE id_usuario=?
                ");
                $s->bind_param('sssssi', $nombre, $apellido, $email, $hash, $genero, $id_usuario);
            } else {
                $s = $conn->prepare("
                    UPDATE usuarios 
                    SET nombre=?, apellido=?, email=?, genero=? 
                    WHERE id_usuario=?
                ");
                $s->bind_param('ssssi', $nombre, $apellido, $email, $genero, $id_usuario);
            }
            $ok = $s->execute();
            $s->close();
            if (!$ok) throw new Exception('No se pudo actualizar el usuario');

            $s = $conn->prepare("
                UPDATE administrativos 
                SET dni=?, telefono=?, direccion=? 
                WHERE id_usuario=?
            ");
            $s->bind_param('sssi', $dni, $telefono, $direccion, $id_usuario);
            $ok = $s->execute();
            $s->close();
            if (!$ok) throw new Exception('No se pudo actualizar los datos administrativos');

            $conn->commit();
            back_with('status=updated');

        } catch (Throwable $e) {
            $conn->rollback();
            back_with('status=error&msg=' . rawurlencode($e->getMessage()));
        }

    } elseif ($form === 'delete') {
        $id_usuario = (int)($_POST['id_usuario'] ?? 0);
        if (!$id_usuario) back_with('status=error');

        try {
            $conn->begin_transaction();

            $s = $conn->prepare("
                UPDATE usuarios u
                JOIN administrativos a ON a.id_usuario = u.id_usuario
                SET u.activo=0, a.activo=0
                WHERE u.id_usuario=? AND u.id_rol=5
            ");
            $s->bind_param('i', $id_usuario);
            $ok = $s->execute();
            $s->close();

            $conn->commit();
            back_with('status=' . ($ok ? 'deleted' : 'error'));

        } catch (Throwable $e) {
            $conn->rollback();
            back_with('status=error&msg=' . rawurlencode($e->getMessage()));
        }
    }
}


// ===== Carga edición =====
$edit = null;

if ($action === 'edit' && $id > 0) {
    $s = $conn->prepare("
        SELECT 
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.email,
            u.activo,
            u.genero,
            a.dni,
            a.telefono,
            a.direccion
        FROM usuarios u
        LEFT JOIN administrativos a ON a.id_usuario = u.id_usuario
        WHERE u.id_usuario = ? AND u.id_rol = 5
        LIMIT 1
    ");
    $s->bind_param('i', $id);
    $s->execute();
    $edit = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$edit) {
        $action = 'list';
    }
}


// ===== Listado =====
$rows = [];

if ($action === 'list') {
    if ($search !== '') {
        $like = '%' . $search . '%';
        $s = $conn->prepare("
            SELECT 
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.email,
                u.activo,
                u.fecha_creacion,
                a.dni,
                a.telefono,
                a.direccion
            FROM usuarios u
            LEFT JOIN administrativos a ON a.id_usuario = u.id_usuario
            WHERE u.id_rol = 5
              AND (u.nombre LIKE ? 
                   OR u.apellido LIKE ? 
                   OR u.email LIKE ? 
                   OR a.dni LIKE ? 
                   OR a.telefono LIKE ?)
            ORDER BY u.apellido, u.nombre
            LIMIT 200
        ");
        $s->bind_param('sssss', $like, $like, $like, $like, $like);
    } else {
        $s = $conn->prepare("
            SELECT 
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.email,
                u.activo,
                u.fecha_creacion,
                a.dni,
                a.telefono,
                a.direccion
            FROM usuarios u
            LEFT JOIN administrativos a ON a.id_usuario = u.id_usuario
            WHERE u.id_rol = 5
            ORDER BY u.apellido, u.nombre
            LIMIT 200
        ");
    }

    $s->execute();
    if ($r = $s->get_result()) {
        while ($row = $r->fetch_assoc()) {
            $rows[] = $row;
        }
    }
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
  <h1>ABM Administrativos</h1>

  <?php if ($flashText): ?>
    <div class="card" style="padding:12px;border-left:4px solid <?= $flashKind==='danger'?'#ef4444':($flashKind==='warning'?'#f59e0b':'#22c55e') ?>">
      <strong><?= esc($flashText) ?></strong>
    </div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
    <?php if ($action==='list'): ?>
      <form method="get" action="abmAdministrativos.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="action" value="list"/>
        <input type="text" id="buscador" placeholder="Buscar nombre, email, DNI o teléfono" style="min-width:280px"/>
        <a class="btn btn-sm" href="abmAdministrativos.php?action=new"><i class="fa fa-user"></i> Nuevo administrativo</a>
      </form>
      <a class="btn-outline btn-sm" href="principalAdmi.php"><i class="fa fa-arrow-left"></i> Volver</a>
    <?php else: ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-sm" href="abmAdministrativos.php"><i class="fa fa-list"></i> Volver al listado</a>
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
            <th>DNI</th>
            <th>Teléfono</th>
            <th>Dirección</th>
            <th>Estado</th>
            <th style="width:180px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" style="color:#666">No hay administrativos cargados.</td></tr>
          <?php else: foreach($rows as $a): ?>
            <tr>
              <td><?= (int)$a['id_usuario'] ?></td>
              <td><?= esc($a['apellido'].', '.$a['nombre']) ?></td>
              <td><?= esc($a['email']) ?></td>
              <td><?= esc($a['dni'] ?? '-') ?></td>
              <td><?= esc($a['telefono'] ?? '-') ?></td>
              <td><?= esc($a['direccion'] ?? '-') ?></td>
              <td><?= (int)$a['activo'] ? '<span class="badge on">Activo</span>' : '<span class="badge off">Inactivo</span>' ?></td>
              <td>
                <a class="btn-outline btn-sm" href="abmAdministrativos.php?action=edit&id=<?= (int)$a['id_usuario'] ?>"><i class="fa fa-pen"></i> Modificar</a>
                <form style="display:inline" method="post" onsubmit="return confirm('¿Eliminar este administrativo?')">
                  <input type="hidden" name="form_action" value="delete"/>
                  <input type="hidden" name="id_usuario" value="<?= (int)$a['id_usuario'] ?>"/>
                  <button class="btn-danger btn-sm" type="submit"><i class="fa fa-trash"></i> Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($action==='new' || ($action==='edit' && $edit)): ?>
    <div class="card">
      <h2><i class="fa fa-user"></i> <?= $action==='new' ? 'Nuevo administrativo' : 'Modificar administrativo' ?></h2>
      <form method="post" autocomplete="off">
        <input type="hidden" name="form_action" value="<?= $action==='new' ? 'create' : 'update' ?>">
        <?php if($action==='edit'): ?>
          <input type="hidden" name="id_usuario" value="<?= (int)$edit['id_usuario'] ?>">
        <?php endif; ?>
        
        <div class="form-grid">
          <div><label>Nombre *</label><input type="text" name="nombre" value="<?= esc($edit['nombre'] ?? '') ?>" required></div>
          <div><label>Apellido *</label><input type="text" name="apellido" value="<?= esc($edit['apellido'] ?? '') ?>" required></div>
          <div><label>Email *</label><input type="email" name="email" value="<?= esc($edit['email'] ?? '') ?>" required></div>
          <div><label><?= $action==='new' ? 'Contraseña *' : 'Nueva contraseña (opcional)' ?></label>
            <input type="password" name="password" <?= $action==='new' ? 'required' : '' ?> placeholder="<?= $action==='edit' ? 'Dejar en blanco para no cambiar' : '' ?>">
          </div>
          <div><label>DNI *</label><input type="text" name="dni" value="<?= esc($edit['dni'] ?? '') ?>" required></div>
          <div><label>Teléfono</label><input type="text" name="telefono" value="<?= esc($edit['telefono'] ?? '') ?>"></div>
          <div><label>Dirección</label><input type="text" name="direccion" value="<?= esc($edit['direccion'] ?? '') ?>"></div>
          <div>
            <label>Género</label>
            <select name="genero">
                <option value="" <?= empty($edit['genero']) ? 'selected' : '' ?>>Seleccione</option>
                <option value="Masculino" <?= (isset($edit['genero']) && $edit['genero']=='Masculino')?'selected':'' ?>>Masculino</option>
                <option value="Femenino" <?= (isset($edit['genero']) && $edit['genero']=='Femenino')?'selected':'' ?>>Femenino</option>
                <option value="Otro" <?= (isset($edit['genero']) && $edit['genero']=='Otro')?'selected':'' ?>>Otro</option>
            </select>
        </div>
          <div><label><input type="checkbox" name="activo" <?= ((int)($edit['activo'] ?? 1)===1)?'checked':'' ?> style="width:auto;margin-right:6px"> Activo</label></div>
        </div>

        <div class="form-actions">
          <a class="btn-outline btn-sm" href="abmAdministrativos.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar <?= $action==='edit' ? 'cambios' : '' ?></button>
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
  const response = await fetch('buscarAdministrativos.php?q=' + encodeURIComponent(q));
  const html = await response.text();
  tbody.innerHTML = html;
});
</script>

</body>
</html>