<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 3; // Admin
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
$nombre = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Admin';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// ===== Helpers =====
function fetch_rows(mysqli $conn, string $sql, array $params = [], string $types = '') {
  $out = [];
  if ($st = $conn->prepare($sql)) {
    if (!empty($params)) { $st->bind_param($types, ...$params); }
    if ($st->execute()) {
      $r = $st->get_result();
      while ($r && $row = $r->fetch_assoc()) $out[] = $row;
    }
    $st->close();
  }
  return $out;
}
function fetch_scalar(mysqli $conn, string $sql, array $params = [], string $types = '') {
  $val = 0;
  if ($st = $conn->prepare($sql)) {
    if (!empty($params)) { $st->bind_param($types, ...$params); }
    if ($st->execute()) {
      $r = $st->get_result();
      if ($r && $row = $r->fetch_row()) $val = (int)$row[0];
    }
    $st->close();
  }
  return $val;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ====== Filtros (default) ======
$hoy = date('Y-m-d');
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? $hoy;
$id_medico = $_GET['id_medico'] ?? '';
$id_especialidad = $_GET['id_especialidad'] ?? '';

// ====== Datos para selects ======
$medicos = fetch_rows($conn,
  "SELECT m.id_medico, CONCAT(u.apellido, ', ', u.nombre) AS medico
   FROM medicos m 
   JOIN usuarios u ON u.id_usuario = m.id_usuario
   WHERE u.activo = 1
   ORDER BY u.apellido, u.nombre");

$especialidades = fetch_rows($conn,
  "SELECT id_especialidad, nombre_especialidad 
   FROM especialidades ORDER BY nombre_especialidad");

// ====== WHERE dinámico ======
$where = " WHERE DATE(t.fecha) BETWEEN ? AND ? ";
$params = [$desde, $hasta];
$types  = "ss";

$joinME = "";
if ($id_medico !== '' && ctype_digit($id_medico)) {
  $where .= " AND t.id_medico = ? ";
  $params[] = (int)$id_medico;
  $types   .= "i";
}
if ($id_especialidad !== '' && ctype_digit($id_especialidad)) {
  $joinME = " JOIN medico_especialidad mef ON mef.id_medico = t.id_medico AND mef.id_especialidad = ? ";
  $params[] = (int)$id_especialidad;
  $types   .= "i";
}

// ====== Consultas ======
// 1) Turnos por especialidad
$sql_esp = "
SELECT esp.id_especialidad, esp.nombre_especialidad AS especialidad, COUNT(*) AS total
FROM turnos t
JOIN medicos m ON m.id_medico = t.id_medico
JOIN usuarios u ON u.id_usuario = m.id_usuario
JOIN medico_especialidad me ON me.id_medico = m.id_medico
JOIN especialidades esp ON esp.id_especialidad = me.id_especialidad
$joinME
$where AND u.activo = 1
GROUP BY esp.id_especialidad, esp.nombre_especialidad
ORDER BY total DESC";
$rows_esp = fetch_rows($conn, $sql_esp, $params, $types);

// 2) Turnos por médico
$sql_med = "
SELECT m.id_medico, CONCAT(u.apellido, ', ', u.nombre) AS medico, COUNT(*) AS total
FROM turnos t
JOIN medicos m ON m.id_medico = t.id_medico
JOIN usuarios u ON u.id_usuario = m.id_usuario
$joinME
$where AND u.activo = 1
GROUP BY m.id_medico, medico
ORDER BY total DESC";
$rows_med = fetch_rows($conn, $sql_med, $params, $types);

// 3) Totales y cancelados
$totales = fetch_scalar($conn, "SELECT COUNT(*) FROM turnos t $joinME $where", $params, $types);

$sql_can = "
SELECT COUNT(*) 
FROM turnos t 
JOIN estados e ON e.id_estado = t.id_estado
$joinME
$where AND e.nombre_estado='cancelado'";
$cancelados = fetch_scalar($conn, $sql_can, $params, $types);

$tasa = $totales > 0 ? round(($cancelados / $totales) * 100, 2) : 0.0;

// 4) Horas populares
$sql_horas = "
SELECT DATE_FORMAT(t.hora, '%H:00') AS hora, COUNT(*) AS total
FROM turnos t
$joinME
$where
GROUP BY DATE_FORMAT(t.hora, '%H:00')
ORDER BY hora";
$rows_horas = fetch_rows($conn, $sql_horas, $params, $types);

// 5) Distribución por estado
$sql_estados = "
SELECT e.nombre_estado, COUNT(*) AS total
FROM turnos t
JOIN estados e ON e.id_estado = t.id_estado
$joinME
$where
GROUP BY e.nombre_estado
ORDER BY total DESC";
$rows_estados = fetch_rows($conn, $sql_estados, $params, $types);

// ===== Export CSV (opcional) =====
if (isset($_GET['export']) && in_array($_GET['export'], ['esp','med','horas','estados'])) {
  $fname = "reporte_" . $_GET['export'] . "_" . $desde . "_a_" . $hasta . ".csv";
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename='.$fname);
  $out = fopen('php://output', 'w');
  if ($_GET['export']==='esp') {
    fputcsv($out, ['Especialidad','Total']);
    foreach ($rows_esp as $r) fputcsv($out, [$r['especialidad'], $r['total']]);
  } elseif ($_GET['export']==='med') {
    fputcsv($out, ['Médico','Total']);
    foreach ($rows_med as $r) fputcsv($out, [$r['medico'], $r['total']]);
  } elseif ($_GET['export']==='horas') {
    fputcsv($out, ['Hora','Total']);
    foreach ($rows_horas as $r) fputcsv($out, [$r['hora'], $r['total']]);
  } else /* estados */ {
    fputcsv($out, ['Estado','Total']);
    foreach ($rows_estados as $r) fputcsv($out, [$r['nombre_estado'], $r['total']]);
  }
  fclose($out);
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Reportes | Gestión de turnos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="/css/administrativo.css">  
<style>
/* estilos (igualados a tus otras vistas) */
*{margin:0;padding:0;box-sizing:border-box}
:root{--brand:#1e88e5;--brand-dark:#1565c0;--ok:#22c55e;--warn:#f59e0b;--bad:#ef4444;--bgcard:rgba(255,255,255,.92)}
body{font-family:Arial,sans-serif;background:url("https://i.pinimg.com/1200x/9b/e2/12/9be212df4fc8537ddc31c3f7fa147b42.jpg") no-repeat center center fixed;background-size:cover;color:#222}
nav{background:#fff;padding:12px 28px;box-shadow:0 4px 10px rgba(0,0,0,.08);position:sticky;top:0;z-index:10}
.nav-inner{display:flex;align-items:center;justify-content:space-between}
.nav-links{display:flex;gap:20px;align-items:center}
nav a{color:var(--brand);text-decoration:none;font-weight:bold}
nav a:hover{text-decoration:underline}
.btn{border:none;border-radius:8px;background:var(--brand);color:#fff;padding:8px 14px;cursor:pointer;font-weight:bold;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
.btn:hover{background:var(--brand-dark)}
.btn.gray{background:#6b7280}
.link{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;background:#10b981;color:#fff;text-decoration:none;font-weight:bold}
.link:hover{filter:brightness(.95)}
.container{padding:24px 18px;max-width:900px;margin:0 auto}
h1{color:#f5f8fa;text-shadow:1px 1px 3px rgba(0,0,0,.5);margin-bottom:12px;font-size:1.8rem}
.card{background:var(--bgcard);backdrop-filter:blur(3px);border-radius:16px;padding:14px;box-shadow:0 8px 16px rgba(0,0,0,.12);margin-bottom:14px}
.section-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;font-size:.95rem}
.table th,.table td{padding:8px 10px;border-bottom:1px solid #e8e8e8;text-align:left}
.table thead th{background:#f8fafc;color:#111}
.filters{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
@media (max-width:720px){.filters{grid-template-columns:1fr}}
label{font-size:.9rem;color:#333}
input,select{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:10px;background:#fff}
.actions{display:flex;gap:8px;justify-content:flex-end;margin-top:8px}
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.kpi{background:#eef6ff;border:1px solid #dbeafe;border-radius:12px;padding:10px;text-align:center}
.kpi small{display:block;color:#333;margin-bottom:4px}
.kpi b{font-size:1.2rem;color:#0b4a9f}
.backbar{display:flex;gap:10px;margin-bottom:10px}
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
      <span style="color:#333;font-weight:bold">
    Bienvenido, <?=  htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>
</span>
      <a class="btn" href="../../Logica/General/cerrarSesion.php"><i class="fa fa-right-from-bracket"></i> Cerrar sesión</a>
    </div>
  </div>
</nav>-->


  <main class="container">
    <div class="backbar">
      <a class="btn gray" href="principalAdmi.php"><i class="fa fa-house"></i> Volver al inicio</a>
    </div>

    <h1><i class="fa fa-chart-line"></i> Reportes</h1>

    <div class="card">
      <form method="get">
        <div class="filters">
          <div><label>Desde</label><input type="date" name="desde" value="<?= h($desde) ?>"></div>
          <div><label>Hasta</label><input type="date" name="hasta" value="<?= h($hasta) ?>"></div>
          <div>
            <label>Médico (opcional)</label>
            <select name="id_medico">
              <option value="">Todos</option>
              <?php foreach($medicos as $m): ?>
                <option value="<?= (int)$m['id_medico'] ?>" <?= ($id_medico!=='' && (int)$id_medico===(int)$m['id_medico'])?'selected':'' ?>><?= h($m['medico']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Especialidad (opcional)</label>
            <select name="id_especialidad">
              <option value="">Todas</option>
              <?php foreach($especialidades as $e): ?>
                <option value="<?= (int)$e['id_especialidad'] ?>" <?= ($id_especialidad!=='' && (int)$id_especialidad===(int)$e['id_especialidad'])?'selected':'' ?>><?= h($e['nombre_especialidad']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="actions">
          <button class="btn" type="submit"><i class="fa fa-filter"></i> Aplicar</button>
          <a class="btn gray" href="reportes.php"><i class="fa fa-eraser"></i> Limpiar</a>
        </div>
      </form>
    </div>

    <div class="kpis">
      <div class="kpi"><small>Total turnos</small><b><?= (int)$totales ?></b></div>
      <div class="kpi"><small>Cancelados</small><b><?= (int)$cancelados ?></b></div>
      <div class="kpi"><small>Tasa cancelación</small><b><?= number_format($tasa,2) ?>%</b></div>
    </div>

    <div class="card">
      <div class="section-title">
        <h2><i class="fa fa-stethoscope"></i> Turnos por especialidad</h2>
        <a class="link" href="?<?= http_build_query(array_merge($_GET,['export'=>'esp'])) ?>"><i class="fa fa-file-csv"></i> CSV</a>
      </div>
      <table class="table">
        <thead><tr><th>Especialidad</th><th>Total</th></tr></thead>
        <tbody>
        <?php if (empty($rows_esp)): ?>
          <tr><td colspan="2" style="color:#666">Sin datos para el rango/filtrado.</td></tr>
        <?php else: foreach($rows_esp as $r): ?>
          <tr><td><?= h($r['especialidad']) ?></td><td><?= (int)$r['total'] ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="section-title">
        <h2><i class="fa fa-user-doctor"></i> Turnos por médico</h2>
        <a class="link" href="?<?= http_build_query(array_merge($_GET,['export'=>'med'])) ?>"><i class="fa fa-file-csv"></i> CSV</a>
      </div>
      <table class="table">
        <thead><tr><th>Médico</th><th>Total</th></tr></thead>
        <tbody>
        <?php if (empty($rows_med)): ?>
          <tr><td colspan="2" style="color:#666">Sin datos para el rango/filtrado.</td></tr>
        <?php else: foreach($rows_med as $r): ?>
          <tr><td><?= h($r['medico']) ?></td><td><?= (int)$r['total'] ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="section-title">
        <h2><i class="fa fa-clock"></i> Horarios más solicitados</h2>
        <a class="link" href="?<?= http_build_query(array_merge($_GET,['export'=>'horas'])) ?>"><i class="fa fa-file-csv"></i> CSV</a>
      </div>
      <table class="table">
        <thead><tr><th>Hora</th><th>Total</th></tr></thead>
        <tbody>
        <?php if (empty($rows_horas)): ?>
          <tr><td colspan="2" style="color:#666">Sin datos para el rango/filtrado.</td></tr>
        <?php else: foreach($rows_horas as $r): ?>
          <tr><td><?= h($r['hora']) ?></td><td><?= (int)$r['total'] ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="section-title">
        <h2><i class="fa fa-layer-group"></i> Distribución por estado</h2>
        <a class="link" href="?<?= http_build_query(array_merge($_GET,['export'=>'estados'])) ?>"><i class="fa fa-file-csv"></i> CSV</a>
      </div>
      <table class="table">
        <thead><tr><th>Estado</th><th>Total</th></tr></thead>
        <tbody>
        <?php if (empty($rows_estados)): ?>
          <tr><td colspan="2" style="color:#666">Sin datos para el rango/filtrado.</td></tr>
        <?php else: foreach($rows_estados as $r): ?>
          <tr><td><?= h($r['nombre_estado']) ?></td><td><?= (int)$r['total'] ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div style="text-align:center;color:#eee;font-size:.85rem;margin-top:6px">Clínica AP · Reportes</div>
  </main>
</body>
</html>