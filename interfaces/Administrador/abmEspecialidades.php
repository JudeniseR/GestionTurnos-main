<?php
ini_set('display_errors', 1);
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
function back_with($qs){ header('Location: abmEspecialidades.php?' . $qs); exit; }
function qget($k,$d=null){ return isset($_GET[$k])?$_GET[$k]:$d; }

// ===== UI State / Flash =====
$action = qget('action','list'); // list | new | edit
$search = trim(qget('q',''));
$id     = (int)qget('id',0);

$status = qget('status');
$msg    = qget('msg');
$flashText = [
  'created'=>'Especialidad creada con éxito.',
  'updated'=>'Especialidad modificada con éxito.',
  'deleted'=>'Especialidad eliminada con éxito.',
  'error'  => ($msg ?: 'Ocurrió un error. Intentalo nuevamente.')
][$status] ?? null;
$flashKind = [
  'created'=>'success','updated'=>'success','deleted'=>'warning','error'=>'danger'
][$status] ?? 'success';

// ===== Acciones (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form_action'] ?? '';

    // --- Crear ---
    if ($form === 'create') {
        $nombre = trim($_POST['nombre_especialidad'] ?? '');

        if ($nombre === '') {
            back_with('status=error&msg=' . rawurlencode('El nombre es obligatorio'));
        }

        // Verificar duplicado
        $s = $conn->prepare("SELECT 1 FROM especialidades WHERE nombre_especialidad=? LIMIT 1");
        $s->bind_param('s', $nombre);
        $s->execute();
        if ($s->get_result()->num_rows > 0) {
            $s->close();
            back_with('status=error&msg=' . rawurlencode('Ya existe una especialidad con ese nombre'));
        }
        $s->close();

        $s = $conn->prepare("INSERT INTO especialidades (nombre_especialidad) VALUES (?)");
        $s->bind_param('s', $nombre);
        $ok = $s->execute();
        $s->close();

        back_with('status=' . ($ok ? 'created' : 'error'));
    }

    // --- Actualizar ---
    elseif ($form === 'update') {
        $id_especialidad = (int)($_POST['id_especialidad'] ?? 0);
        $nombre = trim($_POST['nombre_especialidad'] ?? '');

        if (!$id_especialidad || $nombre === '') {
            back_with('status=error&msg=' . rawurlencode('Datos incompletos'));
        }

        // Duplicado
        $s = $conn->prepare("SELECT 1 FROM especialidades WHERE nombre_especialidad=? AND id_especialidad<>? LIMIT 1");
        $s->bind_param('si', $nombre, $id_especialidad);
        $s->execute();
        if ($s->get_result()->num_rows > 0) {
            $s->close();
            back_with('status=error&msg=' . rawurlencode('Ya existe una especialidad con ese nombre'));
        }
        $s->close();

        $s = $conn->prepare("UPDATE especialidades SET nombre_especialidad=? WHERE id_especialidad=?");
        $s->bind_param('si', $nombre, $id_especialidad);
        $ok = $s->execute();
        $s->close();

        back_with('status=' . ($ok ? 'updated' : 'error'));
    }

    // --- Eliminar ---
    elseif ($form === 'delete') {
        $id_especialidad = (int)($_POST['id_especialidad'] ?? 0);
        if (!$id_especialidad) back_with('status=error');

        $s = $conn->prepare("DELETE FROM especialidades WHERE id_especialidad=?");
        $s->bind_param('i', $id_especialidad);
        $ok = $s->execute();
        $s->close();

        back_with('status=' . ($ok ? 'deleted' : 'error'));
    }
}

// ===== Carga edición =====
$edit = null;
if ($action === 'edit' && $id > 0) {
    $s = $conn->prepare("SELECT * FROM especialidades WHERE id_especialidad=? LIMIT 1");
    $s->bind_param('i', $id);
    $s->execute();
    $edit = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$edit) $action = 'list';
}

// ===== Listado =====
$rows = [];
if ($action === 'list') {
    if ($search !== '') {
        $like = '%' . $search . '%';
        $s = $conn->prepare("SELECT * FROM especialidades WHERE nombre_especialidad LIKE ? ORDER BY nombre_especialidad");
        $s->bind_param('s', $like);
    } else {
        $s = $conn->prepare("SELECT * FROM especialidades ORDER BY nombre_especialidad");
    }

    $s->execute();
    $r = $s->get_result();
    while ($row = $r->fetch_assoc()) $rows[] = $row;
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
  <h1>ABM Especialidades</h1>

  <?php if ($flashText): ?>
    <div class="card" style="padding:12px;border-left:4px solid <?= $flashKind==='danger'?'#ef4444':($flashKind==='warning'?'#f59e0b':'#22c55e') ?>">
      <strong><?= esc($flashText) ?></strong>
    </div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
    <?php if ($action==='list'): ?>
      <form method="get" action="abmEspecialidades.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="action" value="list"/>
        <input type="text" id="buscador" name="q" value="<?= esc($search) ?>" placeholder="Buscar especialidad..." style="min-width:280px"/>
        <a class="btn btn-sm" href="abmEspecialidades.php?action=new"><i class="fa fa-plus"></i> Nueva especialidad</a>
      </form>
      <a class="btn-outline btn-sm" href="principalAdmi.php"><i class="fa fa-arrow-left"></i> Volver</a>
    <?php else: ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-sm" href="abmEspecialidades.php"><i class="fa fa-list"></i> Volver al listado</a>
        <a class="btn-outline btn-sm" href="principalAdmi.php"><i class="fa fa-house"></i> Ir al principal</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($action==='list'): ?>
    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Nombre de la especialidad</th>
            <th style="width:180px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="3" style="color:#666">No hay especialidades cargadas.</td></tr>
          <?php else: foreach($rows as $e): ?>
            <tr>
              <td><?= (int)$e['id_especialidad'] ?></td>
              <td><?= esc($e['nombre_especialidad']) ?></td>
              <td>
                <a class="btn-outline btn-sm" href="abmEspecialidades.php?action=edit&id=<?= (int)$e['id_especialidad'] ?>"><i class="fa fa-pen"></i> Modificar</a>
                <form style="display:inline" method="post" onsubmit="return confirm('¿Eliminar esta especialidad?')">
                  <input type="hidden" name="form_action" value="delete"/>
                  <input type="hidden" name="id_especialidad" value="<?= (int)$e['id_especialidad'] ?>"/>
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
      <h2><i class="fa fa-stethoscope"></i> <?= $action==='new' ? 'Nueva especialidad' : 'Modificar especialidad' ?></h2>
      <form method="post" autocomplete="off">
        <input type="hidden" name="form_action" value="<?= $action==='new' ? 'create' : 'update' ?>">
        <?php if($action==='edit'): ?>
          <input type="hidden" name="id_especialidad" value="<?= (int)$edit['id_especialidad'] ?>">
        <?php endif; ?>

        <div class="form-grid">
          <div>
            <label>Nombre de la especialidad *</label>
            <input type="text" name="nombre_especialidad" value="<?= esc($edit['nombre_especialidad'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-actions">
          <a class="btn-outline btn-sm" href="abmEspecialidades.php"><i class="fa fa-xmark"></i> Cancelar</a>
          <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar <?= $action==='edit' ? 'cambios' : '' ?></button>
        </div>
      </form>
    </div>
  <?php endif; ?>
</main>

<script>
const buscador = document.getElementById('buscador');
const tbody = document.querySelector('tbody');

if (buscador && tbody) {
  buscador.addEventListener('input', async () => {
    const q = buscador.value.trim();
    const response = await fetch('buscarEspecialidades.php?q=' + encodeURIComponent(q));
    const html = await response.text();
    tbody.innerHTML = html;
  });
}
</script>

</body>
</html>
