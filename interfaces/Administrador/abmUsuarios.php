<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 3; // Admin
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
$nombreAdmin = $_SESSION['nombre'] ?? 'Admin';

$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// ===== Helpers =====
function post($k,$d=null){return isset($_POST[$k])?trim($_POST[$k]):$d;}
function redirect_self(){ header("Location: ".$_SERVER['PHP_SELF']); exit; }
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===== Acciones (crear/editar/toggle) =====
$action = $_POST['action'] ?? '';

if ($action === 'create_user') {
  // Campos básicos
  $nombre   = post('nombre','');
  $apellido = post('apellido','');
  $email    = post('email','');
  $password = post('password','');
  $id_rol   = (int)post('id_rol',1); // 1=Paciente, 2=Medico, 3=Admin, 4=Tecnico
  $activo   = (int)post('activo',1);

  if ($nombre && $apellido && $email && $password && in_array($id_rol,[1,2,3,4],true)) {
    // Crear usuario
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO usuario (nombre,apellido,email,password_hash,id_rol,activo) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('ssssii', $nombre, $apellido, $email, $hash, $id_rol, $activo);
    if ($stmt->execute()) {
      $id_usuario = $stmt->insert_id;

      // Si es Paciente -> crear registro en pacientes
      if ($id_rol === 1) {
        $stmt2 = $conn->prepare("INSERT INTO pacientes (id_usuario) VALUES (?)");
        $stmt2->bind_param('i', $id_usuario);
        $stmt2->execute();
        $stmt2->close();
      }
      // Si es Médico -> crear registro en medicos (opcional matrícula)
      if ($id_rol === 2) {
        $matricula = post('matricula', null);
        $telefono  = post('telefono', null);
        $stmt2 = $conn->prepare("INSERT INTO medicos (id_usuario,matricula,telefono) VALUES (?,?,?)");
        $stmt2->bind_param('iss', $id_usuario, $matricula, $telefono);
        $stmt2->execute();
        $stmt2->close();
      }
    }
    $stmt->close();
  }
  redirect_self();
}

if ($action === 'update_user') {
  $id_usuario = (int)post('id_usuario');
  $id_rol     = (int)post('id_rol');
  $activo     = (int)post('activo',1);

  // Actualizar datos básicos del usuario (nombre/apellido/email opcionales)
  $nombre   = post('nombre', null);
  $apellido = post('apellido', null);
  $email    = post('email', null);

  // Si viene password nuevo
  $password = post('password', '');
  if ($password !== '') {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE usuario SET password_hash=? WHERE id_usuario=?");
    $stmt->bind_param('si', $hash, $id_usuario);
    $stmt->execute();
    $stmt->close();
  }

  // Update de campos editables
  $stmt = $conn->prepare("UPDATE usuario SET nombre=COALESCE(?,nombre), apellido=COALESCE(?,apellido), email=COALESCE(?,email), id_rol=?, activo=? WHERE id_usuario=?");
  $stmt->bind_param('sssiii', $nombre, $apellido, $email, $id_rol, $activo, $id_usuario);
  $stmt->execute(); $stmt->close();

  // Si cambió a Médico y no existe en medicos -> crear
  if ($id_rol === 2) {
    $matricula = post('matricula', null);
    $telefono  = post('telefono', null);
    $exists = $conn->query("SELECT 1 FROM medicos WHERE id_usuario=".$id_usuario)->num_rows>0;
    if ($exists) {
      // actualizar matrícula/teléfono si vinieron
      if ($matricula!==null || $telefono!==null){
        $stmt2 = $conn->prepare("UPDATE medicos SET matricula=COALESCE(?,matricula), telefono=COALESCE(?,telefono) WHERE id_usuario=?");
        $stmt2->bind_param('ssi', $matricula, $telefono, $id_usuario);
        $stmt2->execute(); $stmt2->close();
      }
    } else {
      $stmt2 = $conn->prepare("INSERT INTO medicos (id_usuario,matricula,telefono) VALUES (?,?,?)");
      $stmt2->bind_param('iss', $id_usuario, $matricula, $telefono);
      $stmt2->execute(); $stmt2->close();
    }
  }

  // Si cambió a Paciente y no existe en pacientes -> crear
  if ($id_rol === 1) {
    $exists = $conn->query("SELECT 1 FROM pacientes WHERE id_usuario=".$id_usuario)->num_rows>0;
    if (!$exists){
      $stmt2 = $conn->prepare("INSERT INTO pacientes (id_usuario) VALUES (?)");
      $stmt2->bind_param('i', $id_usuario);
      $stmt2->execute(); $stmt2->close();
    }
  }

  redirect_self();
}

if ($action === 'toggle_activo') {
  $id_usuario = (int)post('id_usuario');
  $activo     = (int)post('activo'); // viene el estado nuevo
  $stmt = $conn->prepare("UPDATE usuario SET activo=? WHERE id_usuario=?");
  $stmt->bind_param('ii', $activo, $id_usuario);
  $stmt->execute(); $stmt->close();
  redirect_self();
}

// ===== Datos para la vista =====
$roles = [];
$r = $conn->query("SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol ASC");
while($row=$r->fetch_assoc()){ $roles[$row['id_rol']] = $row['nombre_rol']; }

// Listado de usuarios con rol + si es médico, su matrícula
$usuarios = [];
$sql = "
  SELECT 
    u.id_usuario, u.nombre, u.apellido, u.email, u.id_rol, u.activo,
    r.nombre_rol,
    m.id_medico, m.matricula, m.telefono
  FROM usuario u
  LEFT JOIN roles r   ON r.id_rol=u.id_rol
  LEFT JOIN medicos m ON m.id_usuario=u.id_usuario
  ORDER BY u.fecha_creacion DESC
";
$res = $conn->query($sql);
while($row=$res->fetch_assoc()){ $usuarios[] = $row; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestión de Usuarios</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ===== Estilos compartidos con el dashboard ===== */
*{margin:0;padding:0;box-sizing:border-box}
:root{ --brand:#1e88e5; --brand-dark:#1565c0; --ok:#22c55e; --warn:#f59e0b; --bad:#ef4444; --bgcard:rgba(255,255,255,.94); --txt:#1f2937; --muted:#6b7280; --border:#e5e7eb; }
body{ font-family:Arial,Helvetica,sans-serif; background:url("https://i.pinimg.com/1200x/9b/e2/12/9be212df4fc8537ddc31c3f7fa147b42.jpg") no-repeat center/cover fixed; color:var(--txt); }
nav{position:sticky;top:0;z-index:50;background:#fff;border-bottom:1px solid var(--border)}
.nav-inner{max-width:1200px;margin:0 auto;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.nav-left,.nav-right{display:flex;align-items:center;gap:16px}
nav a{color:var(--brand);text-decoration:none;font-weight:700}
nav a:hover{text-decoration:underline}
.btn{border:none;border-radius:8px;padding:8px 14px;cursor:pointer;background:var(--brand);color:#fff;font-weight:700}
.btn:hover{background:var(--brand-dark)}
.container{max-width:1200px;margin:0 auto;padding:28px 20px 40px}
h1{color:#f7fafc;text-shadow:0 1px 3px rgba(0,0,0,.5);font-size:2rem;font-weight:800;margin-bottom:18px}
.card{background:var(--bgcard);border-radius:16px;padding:16px;box-shadow:0 8px 18px rgba(0,0,0,.12);border:1px solid rgba(0,0,0,.03);margin-bottom:16px}
.grid{display:grid;gap:16px}
.grid.cols-2{grid-template-columns:2fr 3fr}
@media (max-width:960px){.grid.cols-2{grid-template-columns:1fr}}
label{font-weight:700;display:block;margin:8px 0 4px}
input,select{width:100%;padding:10px;border:1px solid var(--border);border-radius:10px}
.table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
.table th{background:#f8fafc;text-align:left;color:#111827;padding:10px;border-bottom:1px solid var(--border)}
.table td{padding:10px;border-bottom:1px solid var(--border)}
.table tr:last-child td{border-bottom:none}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.78rem;color:#fff;font-weight:700}
.badge.activo{background:var(--ok)}
.badge.inactivo{background:#9ca3af}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.small{font-size:.9rem;color:var(--muted)}
</style>
</head>
<body>

<nav>
  <div class="nav-inner">
    <div class="nav-left">
      <a href="/interfaces/Administrador/principalAdmi.php"><i class="fa fa-house"></i> Inicio</a>
    </div>
    <div class="nav-right">
      <span style="color:#374151;font-weight:700">Bienvenido, <?= esc($nombreAdmin) ?></span>
      <a class="btn" href="../../Logica/General/cerrarSesion.php"><i class="fa fa-right-from-bracket"></i> Cerrar sesión</a>
    </div>
  </div>
</nav>

<main class="container">
  <h1>Gestión de Usuarios</h1>

  <!-- Crear usuario -->
  <div class="card">
    <h2 style="margin-bottom:10px"><i class="fa fa-user-plus"></i> Crear nuevo usuario</h2>
    <form method="post" class="grid cols-2">
      <input type="hidden" name="action" value="create_user">
      <div>
        <label>Nombre</label>
        <input name="nombre" required>
      </div>
      <div>
        <label>Apellido</label>
        <input name="apellido" required>
      </div>
      <div>
        <label>Email</label>
        <input type="email" name="email" required>
      </div>
      <div>
        <label>Contraseña</label>
        <input type="password" name="password" required>
      </div>
      <div>
        <label>Rol</label>
        <select name="id_rol" required>
          <?php foreach($roles as $id=>$rol){ ?>
            <option value="<?= (int)$id ?>"><?= esc($rol) ?></option>
          <?php } ?>
        </select>
        <div class="small">1=Paciente, 2=Médico, 3=Administrador, 4=Tecnico</div>
      </div>
      <div>
        <label>Activo</label>
        <select name="activo">
          <option value="1">Sí</option>
          <option value="0">No</option>
        </select>
      </div>
      <!-- Campos específicos Médico (opcionales, solo si elegís rol 2) -->
      <div>
        <label>Matrícula (si es Médico)</label>
        <input name="matricula" placeholder="ej. 123456">
      </div>
      <div>
        <label>Teléfono (si es Médico)</label>
        <input name="telefono" placeholder="ej. 11-5555-5555">
      </div>

      <div style="grid-column:1 / -1; display:flex; gap:10px; margin-top:8px">
        <button class="btn" type="submit"><i class="fa fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>

  <!-- Listado -->
  <div class="card">
    <h2 style="margin-bottom:10px"><i class="fa fa-users"></i> Usuarios</h2>
    <table class="table">
      <thead>
        <tr>
          <th>#</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Matrícula</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($usuarios)): ?>
          <tr><td colspan="7" style="color:#666">No hay usuarios cargados.</td></tr>
        <?php else: foreach ($usuarios as $u): ?>
          <tr>
            <td><?= (int)$u['id_usuario'] ?></td>
            <td><?= esc($u['apellido'].', '.$u['nombre']) ?></td>
            <td><?= esc($u['email']) ?></td>
            <td><?= esc($u['nombre_rol'] ?? '-') ?></td>
            <td>
              <span class="badge <?= ((int)$u['activo']===1 ? 'activo':'inactivo') ?>">
                <?= ((int)$u['activo']===1 ? 'Activo':'Inactivo') ?>
              </span>
            </td>
            <td><?= esc($u['matricula'] ?? '-') ?></td>
            <td class="actions">
              <!-- Editar básico (rol/activo + datos y matrícula si médico) -->
              <form method="post" style="display:inline-flex; gap:6px; flex-wrap:wrap">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                <input name="nombre"   placeholder="Nombre"   value="<?= esc($u['nombre']) ?>" style="width:120px">
                <input name="apellido" placeholder="Apellido" value="<?= esc($u['apellido']) ?>" style="width:120px">
                <input name="email"    placeholder="Email"    value="<?= esc($u['email']) ?>" style="width:180px">
                <select name="id_rol">
                  <?php foreach($roles as $id=>$rol){ ?>
                    <option value="<?= (int)$id ?>" <?= ((int)$u['id_rol']===(int)$id?'selected':'') ?>><?= esc($rol) ?></option>
                  <?php } ?>
                </select>
                <select name="activo">
                  <option value="1" <?= ((int)$u['activo']===1?'selected':'') ?>>Activo</option>
                  <option value="0" <?= ((int)$u['activo']===0?'selected':'') ?>>Inactivo</option>
                </select>
                <?php if ((int)$u['id_rol']===2 || $u['matricula']!==null): ?>
                  <input name="matricula" placeholder="Matrícula" value="<?= esc($u['matricula']) ?>" style="width:120px">
                  <input name="telefono"  placeholder="Teléfono"  value="<?= esc($u['telefono']) ?>"  style="width:120px">
                <?php endif; ?>
                <input type="password" name="password" placeholder="Nueva clave (opcional)" style="width:150px">
                <button class="btn" type="submit" title="Guardar cambios"><i class="fa fa-save"></i></button>
              </form>

              <!-- Activar/Desactivar rápido -->
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="toggle_activo">
                <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                <input type="hidden" name="activo" value="<?= ((int)$u['activo']===1?0:1) ?>">
                <button class="btn" type="submit" title="Alternar activo">
                  <i class="fa <?= ((int)$u['activo']===1?'fa-toggle-on':'fa-toggle-off') ?>"></i>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</main>
</body>
</html>
