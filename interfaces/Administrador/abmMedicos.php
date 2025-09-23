<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 3; // Admin
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// ===== Utilidades =====
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect_status($status, $extra=''){
  // IMPORTANTE: no hagas echo/var_dump antes de este header
  $q = 'status='.rawurlencode($status);
  if ($extra) $q .= '&'.$extra;
  header('Location: abmUsuarios.php?'.$q);
  exit;
}

// ===== Flash / Alertas por querystring =====
$status = $_GET['status'] ?? null; // created | updated | deleted | error
$msg    = $_GET['msg'] ?? null;
$messages = [
  'created' => 'Usuario creado con éxito.',
  'updated' => 'Usuario modificado con éxito.',
  'deleted' => 'Usuario eliminado con éxito.',
  'error'   => $msg ?: 'Ocurrió un error. Inténtalo nuevamente.',
];
$kinds = [
  'created' => 'success',
  'updated' => 'success',
  'deleted' => 'warning',
  'error'   => 'danger',
];
$flashText = $messages[$status] ?? null;
$flashKind = $kinds[$status] ?? 'success';

// ===== Modo de pantalla =====
$action = $_GET['action'] ?? 'list';  // list | new | edit
$search = trim($_GET['q'] ?? '');
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ===== Roles para selects =====
$roles = [];
if ($r = $conn->query("SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol DESC")) {
  while ($row = $r->fetch_assoc()) $roles[] = $row;
  $r->close();
}

// ===== Handlers POST (crear / editar / eliminar) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form_action = $_POST['form_action'] ?? '';

  // CREAR
  if ($form_action === 'create') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $id_rol   = (int)($_POST['id_rol'] ?? 1);
    $activo   = isset($_POST['activo']) ? 1 : 0;

    if ($nombre==='' || $apellido==='' || $email==='' || $password==='') {
      redirect_status('error','msg=Campos%20obligatorios');
    }

    // Email duplicado
    $s = $conn->prepare("SELECT 1 FROM usuario WHERE email=? LIMIT 1");
    $s->bind_param('s',$email); $s->execute();
    if ($s->get_result()->num_rows>0) { $s->close(); redirect_status('error','msg=Email%20ya%20registrado'); }
    $s->close();

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $s = $conn->prepare("INSERT INTO usuario (nombre, apellido, email, password_hash, id_rol, activo) VALUES (?,?,?,?,?,?)");
    $s->bind_param('ssssii',$nombre,$apellido,$email,$hash,$id_rol,$activo);
    $ok = $s->execute(); $s->close();

    redirect_status($ok?'created':'error');
  }

  // EDITAR
  if ($form_action === 'update') {
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? ''); // opcional
    $id_rol   = (int)($_POST['id_rol'] ?? 1);
    $activo   = isset($_POST['activo']) ? 1 : 0;

    if (!$id_usuario || $nombre==='' || $apellido==='' || $email==='') {
      redirect_status('error','msg=Datos%20incompletos');
    }

    // email en uso por otro
    $s = $conn->prepare("SELECT 1 FROM usuario WHERE email=? AND id_usuario<>? LIMIT 1");
    $s->bind_param('si',$email,$id_usuario); $s->execute();
    if ($s->get_result()->num_rows>0) { $s->close(); redirect_status('error','msg=Email%20ya%20en%20uso'); }
    $s->close();

    if ($password!=='') {
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $s = $conn->prepare("UPDATE usuario SET nombre=?, apellido=?, email=?, password_hash=?, id_rol=?, activo=? WHERE id_usuario=?");
      $s->bind_param('ssssiii',$nombre,$apellido,$email,$hash,$id_rol,$activo,$id_usuario);
    } else {
      $s = $conn->prepare("UPDATE usuario SET nombre=?, apellido=?, email=?, id_rol=?, activo=? WHERE id_usuario=?");
      $s->bind_param('sssiii',$nombre,$apellido,$email,$id_rol,$activo,$id_usuario);
    }
    $ok = $s->execute(); $s->close();
    redirect_status($ok?'updated':'error');
  }

  // ELIMINAR
  if ($form_action === 'delete') {
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    if (!$id_usuario) redirect_status('error');

    $ok = false;
    try {
      $s = $conn->prepare("DELETE FROM usuario WHERE id_usuario=?");
      $s->bind_param('i',$id_usuario);
      $ok = $s->execute();
      $s->close();
    } catch (Throwable $e) { $ok = false; }

    redirect_status($ok?'deleted':'error');
  }
}

// ===== Carga para edición =====
$editUser = null;
if ($action==='edit' && $id>0) {
  $s = $conn->prepare("SELECT id_usuario, nombre, apellido, email, id_rol, activo FROM usuario WHERE id_usuario=?");
  $s->bind_param('i',$id); $s->execute();
  $editUser = $s->get_result()->fetch_assoc();
  $s->close();
  if (!$editUser) $action='list';
}

// ===== Listado =====
$usuarios = [];
if ($action==='list') {
  if ($search!=='') {
    $like = '%'.$search.'%';
    $s = $conn->prepare("
      SELECT u.id_usuario, u.nombre, u.apellido, u.email, r.nombre_rol, u.activo, u.fecha_creacion
      FROM usuario u
      LEFT JOIN roles r ON r.id_rol=u.id_rol
      WHERE u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ?
      ORDER BY u.apellido,u.nombre
      LIMIT 200
    ");
    $s->bind_param('sss',$like,$like,$like);
  } else {
    $s = $conn->prepare("
      SELECT u.id_usuario, u.nombre, u.apellido, u.email, r.nombre_rol, u.activo, u.fecha_creacion
      FROM usuario u
      LEFT JOIN roles r ON r.id_rol=u.id_rol
      ORDER BY u.apellido,u.nombre
      LIMIT 200
    ");
  }
  $s->execute();
  $r = $s->get_result();
  while ($r && $row=$r->fetch_assoc()) $usuarios[]=$row;
  $s->close();
}

$debug = isset($_GET['debug']) ? true : false;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>ABM Usuarios</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{ --brand:#1e88e5; --brand-dark:#1565c0; --border:#e5e7eb; --ok:#16a34a; --warn:#f59e0b; --bad:#ef4444; }
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f5f7fb;color:#111}
.container{max-width:1100px;margin:18px auto;padding:0 14px}

/* Tarjetas separadas */
.card{background:#fff;border:1px solid var(--border);border-radius:14px;box-shadow:0 10px 22px rgba(0,0,0,.06);padding:14px;margin:16px 0}
.card.narrow{max-width:720px;margin:16px auto}

/* Toolbar */
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px}
.btn{border:none;border-radius:10px;background:var(--brand);color:#fff;padding:8px 12px;cursor:pointer;font-weight:700}
.btn:hover{background:var(--brand-dark)}
.btn-outline{background:#fff;color:#111;border:1px solid var(--border)}
.btn-danger{background:var(--bad); color:#fff}
.btn-sm{font-size:.9rem;padding:6px 10px;height:auto;display:inline-flex;align-items:center;gap:6px}

.search{display:flex;gap:8px;align-items:center}
.search input{padding:8px 10px;border:1px solid var(--border);border-radius:10px;min-width:260px}

/* Tabla */
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid var(--border);text-align:left}
.table th{background:#f8fafc}
.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-weight:700;font-size:.78rem}
.badge.on{background:#ecfdf5;color:#065f46;border:1px solid #10b981}
.badge.off{background:#fef2f2;color:#7f1d1d;border:1px solid #ef4444}

/* Alertas */
.alert{position:relative;border-radius:12px;padding:10px 12px;margin:0;display:flex;gap:10px;align-items:flex-start;border:1px solid transparent}
.alert .icon{margin-top:2px}
.alert .close{margin-left:auto;background:transparent;border:none;cursor:pointer;font-size:1rem;line-height:1}
.alert.success{background:#ecfdf5;border-color:#10b981;color:#065f46}
.alert.warning{background:#fff7ed;border-color:#f59e0b;color:#7c2d12}
.alert.danger{background:#fef2f2;border-color:#ef4444;color:#7f1d1d}

/* Form */
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.form-grid .full{grid-column:1 / -1}
label{display:block;font-weight:700;margin-bottom:6px}
input[type="text"], input[type="email"], input[type="password"], select{
  width:100%;padding:10px;border:1px solid var(--border);border-radius:10px
}
.form-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}

/* Debug */
.debug{font-family:ui-monospace,Consolas,monospace;background:#111;color:#f5f5f5;border-radius:10px;padding:10px;white-space:pre-wrap}
</style>
</head>
<body>
<div class="container">

  <?php if ($debug): ?>
    <div class="card">
      <div class="debug">
Acción actual: <?= esc($action) . "\n" ?>
Usuarios listados: <?= count($usuarios) . "\n" ?>
GET: <?= esc(json_encode($_GET, JSON_UNESCAPED_UNICODE)) . "\n" ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="card">
    <div class="toolbar">
      <div style="display:flex; gap:8px">
        <button class="btn-outline btn-sm" type="button" onclick="history.back()">
          <i class="fa fa-arrow-left"></i> Volver
        </button>
        <a class="btn btn-sm" href="principalAdmi.php">
          <i class="fa fa-home"></i> Ir al Principal
        </a>
      </div>

      <?php if ($action==='list'): ?>
        <form class="search" method="get" action="abmUsuarios.php">
          <input type="hidden" name="action" value="list"/>
          <input type="text" name="q" value="<?= esc($search) ?>" placeholder="Buscar por nombre, apellido o email"/>
          <button class="btn-outline btn-sm" type="submit"><i class="fa fa-search"></i> Buscar</button>
          <a class="btn btn-sm" href="abmUsuarios.php?action=new"><i class="fa fa-user-plus"></i> Nuevo usuario</a>
        </form>
      <?php else: ?>
        <div>
          <a class="btn btn-sm" href="abmUsuarios.php"><i class="fa fa-list"></i> Volver al listado</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Alertas -->
  <?php if ($flashText): ?>
    <div class="card">
      <div class="alert <?= esc($flashKind) ?>" id="flash">
        <span class="icon">
          <?php if ($flashKind==='success'): ?>
            <i class="fa fa-circle-check"></i>
          <?php elseif ($flashKind==='warning'): ?>
            <i class="fa fa-triangle-exclamation"></i>
          <?php else: ?>
            <i class="fa fa-circle-xmark"></i>
          <?php endif; ?>
        </span>
        <div><strong><?= esc($flashText) ?></strong></div>
        <button class="close" aria-label="Cerrar" onclick="dismissFlash()"><i class="fa fa-xmark"></i></button>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($action==='list'): ?>

    <!-- LISTADO con Modificar / Eliminar -->
    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Apellido y Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Creación</th>
            <th style="width:220px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($usuarios)): ?>
            <tr>
              <td colspan="7" style="color:#666">
                No hay usuarios. 
                <a class="btn btn-sm" href="abmUsuarios.php?action=new" style="margin-left:8px"><i class="fa fa-user-plus"></i> Crear primero</a>
              </td>
            </tr>
          <?php else: foreach($usuarios as $u): ?>
            <tr>
              <td><?= (int)$u['id_usuario'] ?></td>
              <td><?= esc($u['apellido'].', '.$u['nombre']) ?></td>
              <td><?= esc($u['email']) ?></td>
              <td><?= esc($u['nombre_rol'] ?? '-') ?></td>
              <td><?= (int)$u['activo'] ? '<span class="badge on">Activo</span>' : '<span class="badge off">Inactivo</span>' ?></td>
              <td><?= esc($u['fecha_creacion']) ?></td>
              <td>
                <!-- MODIFICAR -->
                <a class="btn-outline btn-sm" href="abmUsuarios.php?action=edit&id=<?= (int)$u['id_usuario'] ?>">
                  <i class="fa fa-pen"></i> Modificar
                </a>
                <!-- ELIMINAR -->
                <form style="display:inline" method="post" onsubmit="return confirm('¿Eliminar este usuario?')">
                  <input type="hidden" name="form_action" value="delete"/>
                  <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>"/>
                  <button class="btn-danger btn-sm" type="submit">
                    <i class="fa fa-trash"></i> Eliminar
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($action==='new'): ?>

    <!-- ALTA -->
    <div class="card narrow">
      <h3 style="margin:6px 0 12px 0"><i class="fa fa-user-plus"></i> Nuevo usuario</h3>
      <form method="post" autocomplete="off">
        <input type="hidden" name="form_action" value="create"/>
        <div class="form-grid">
          <div>
            <label>Nombre</label>
            <input type="text" name="nombre" required/>
          </div>
          <div>
            <label>Apellido</label>
            <input type="text" name="apellido" required/>
          </div>
          <div class="full">
            <label>Email</label>
            <input type="email" name="email" required/>
          </div>
          <div>
            <label>Contraseña</label>
            <input type="password" name="password" required/>
          </div>
          <div>
            <label>Rol</label>
            <select name="id_rol" required>
              <?php foreach($roles as $r): ?>
                <option value="<?= (int)$r['id_rol'] ?>"><?= esc($r['nombre_rol']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="full">
            <label><input type="checkbox" name="activo" checked/> Activo</label>
          </div>
        </div>
        <div class="form-actions">
          <a class="btn-outline btn-sm" href="abmUsuarios.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar</button>
        </div>
      </form>
    </div>

  <?php elseif ($action==='edit' && $editUser): ?>

    <!-- EDICIÓN -->
    <div class="card narrow">
      <h3 style="margin:6px 0 12px 0"><i class="fa fa-user-pen"></i> Modificar usuario</h3>
      <form method="post" autocomplete="off">
        <input type="hidden" name="form_action" value="update"/>
        <input type="hidden" name="id_usuario" value="<?= (int)$editUser['id_usuario'] ?>"/>
        <div class="form-grid">
          <div>
            <label>Nombre</label>
            <input type="text" name="nombre" value="<?= esc($editUser['nombre']) ?>" required/>
          </div>
          <div>
            <label>Apellido</label>
            <input type="text" name="apellido" value="<?= esc($editUser['apellido']) ?>" required/>
          </div>
          <div class="full">
            <label>Email</label>
            <input type="email" name="email" value="<?= esc($editUser['email']) ?>" required/>
          </div>
          <div>
            <label>Nueva contraseña (opcional)</label>
            <input type="password" name="password" placeholder="Dejar en blanco para no cambiar"/>
          </div>
          <div>
            <label>Rol</label>
            <select name="id_rol" required>
              <?php foreach($roles as $r): ?>
                <option value="<?= (int)$r['id_rol'] ?>" <?= ((int)$editUser['id_rol']===(int)$r['id_rol'])?'selected':'' ?>>
                  <?= esc($r['nombre_rol']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="full">
            <label><input type="checkbox" name="activo" <?= ((int)$editUser['activo']===1)?'checked':'' ?>/> Activo</label>
          </div>
        </div>
        <div class="form-actions">
          <a class="btn-outline btn-sm" href="abmUsuarios.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar cambios</button>
        </div>
      </form>
    </div>

  <?php endif; ?>

</div>

<script>
// Cierre suave de la alerta
function dismissFlash(){
  const el = document.getElementById('flash');
  if (!el) return;
  el.style.transition = 'opacity .2s ease';
  el.style.opacity = '0';
  setTimeout(()=> el.remove(), 220);
}
// Auto-ocultar a los 4s
document.addEventListener('DOMContentLoaded', ()=>{
  const el = document.getElementById('flash');
  if (el) setTimeout(dismissFlash, 4000);
});
</script>
</body>
</html>
