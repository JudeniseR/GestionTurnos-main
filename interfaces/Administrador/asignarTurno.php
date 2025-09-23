<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 3; // Admin
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
$nombre = $_SESSION['nombre'] ?? 'Admin';

// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// ===== Helpers =====
function fetch_rows(mysqli $conn, string $sql, array $params = [], string $types = '') {
  $out = [];
  if ($st = $conn->prepare($sql)) {
    if ($types && $params) $st->bind_param($types, ...$params);
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
    if ($types && $params) $st->bind_param($types, ...$params);
    if ($st->execute()) {
      $r = $st->get_result();
      if ($r && $row = $r->fetch_row()) $val = (int)$row[0];
    }
    $st->close();
  }
  return $val;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function time_add_minutes(string $hhmm, int $mins): string {
  [$h,$m] = array_map('intval', explode(':', $hhmm));
  $t = ($h*60 + $m) + $mins;
  $H = floor($t/60); $M = $t%60;
  return sprintf('%02d:%02d', $H, $M);
}
function time_less(string $a, string $b): bool { return strcmp($a, $b) < 0; }

// ===== Datos base =====
$pacientes = fetch_rows($conn, "
  SELECT p.id_paciente, CONCAT(u.apellido, ', ', u.nombre) AS paciente
  FROM pacientes p
  JOIN usuario u ON u.id_usuario = p.id_usuario
  ORDER BY u.apellido, u.nombre");

$especialidades = fetch_rows($conn, "
  SELECT id_especialidad, nombre_especialidad
  FROM especialidades
  ORDER BY nombre_especialidad");

// ===== Filtros (GET) =====
$hoy = date('Y-m-d');
$id_paciente      = $_GET['id_paciente']      ?? '';
$id_especialidad  = $_GET['id_especialidad']  ?? '';
$id_medico        = $_GET['id_medico']        ?? '';
$fecha            = $_GET['fecha']            ?? $hoy;
$desde_hora       = $_GET['desde_hora']       ?? '08:00';
$hasta_hora       = $_GET['hasta_hora']       ?? '18:00';
$duracion_min     = isset($_GET['duracion_min']) && ctype_digit($_GET['duracion_min']) ? (int)$_GET['duracion_min'] : 30;
if ($duracion_min <= 0) $duracion_min = 30;

// ===== Médicos (opcional filtro por especialidad) =====
$paramsM = []; $typesM = ''; $joinME = '';
if ($id_especialidad !== '' && ctype_digit($id_especialidad)) {
  $joinME = "JOIN medico_especialidad me ON me.id_medico = m.id_medico AND me.id_especialidad = ?";
  $paramsM[] = (int)$id_especialidad; $typesM .= 'i';
}
$medicos = fetch_rows($conn, "
  SELECT m.id_medico, CONCAT(u.apellido, ', ', u.nombre) AS medico
  FROM medicos m
  JOIN usuario u ON u.id_usuario = m.id_usuario
  $joinME
  ORDER BY u.apellido, u.nombre
", $paramsM, $typesM);

// ===== POST: crear turno =====
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $p_id_paciente = (int)($_POST['id_paciente'] ?? 0);
  $p_id_medico   = (int)($_POST['id_medico'] ?? 0);
  $p_fecha       = $_POST['fecha'] ?? '';
  $p_hora        = $_POST['hora'] ?? '';

  if ($p_id_paciente<=0 || $p_id_medico<=0 || !$p_fecha || !$p_hora) {
    $error = "Faltan datos para crear el turno.";
  } else {
    // Chequear colisión (mismo médico/fecha/hora, no cancelado)
    $ocupado = fetch_scalar($conn, "
      SELECT COUNT(*)
      FROM turnos t
      JOIN estado e ON e.id_estado = t.id_estado
      WHERE t.id_medico = ? AND DATE(t.fecha) = ? AND t.hora = ? AND e.nombre_estado <> 'cancelado'
    ", [$p_id_medico, $p_fecha, $p_hora], 'iss');

    if ($ocupado > 0) {
      $error = "El horario seleccionado ya está ocupado.";
    } else {
      // Obtener id_estado 'pendiente'
      $rowPend = fetch_rows($conn, "SELECT id_estado FROM estado WHERE nombre_estado='pendiente' LIMIT 1");
      $id_estado = (int)($rowPend[0]['id_estado'] ?? 0);
      if ($id_estado <= 0) { $error = "No se encontró el estado 'pendiente'."; }
      else {
        if ($st = $conn->prepare("INSERT INTO turnos (id_paciente, id_medico, fecha, hora, id_estado) VALUES (?,?,?,?,?)")) {
          $st->bind_param('iissi', $p_id_paciente, $p_id_medico, $p_fecha, $p_hora, $id_estado);
          if ($st->execute()) {
            header("Location: turnosListado.php?ok=created");
            exit;
          } else {
            $error = "Error al guardar el turno.";
          }
          $st->close();
        } else {
          $error = "No se pudo preparar la consulta.";
        }
      }
    }
  }
}

// ===== Generar slots disponibles (GET) =====
$slots = [];
if ($id_medico !== '' && ctype_digit($id_medico) && $fecha) {
  // Horas ya ocupadas ese día por ese médico (no cancelados)
  $ocupadas = fetch_rows($conn, "
    SELECT TIME_FORMAT(t.hora, '%H:%i') AS hora
    FROM turnos t
    JOIN estado e ON e.id_estado = t.id_estado
    WHERE t.id_medico = ? AND DATE(t.fecha) = ? AND e.nombre_estado <> 'cancelado'
  ", [(int)$id_medico, $fecha], 'is');
  $lookup = array_flip(array_column($ocupadas, 'hora'));

  // (Opcional) Traer feriados/excepciones del médico/fecha si tenés esa tabla y excluir rangos

  // Generador de slots
  $h = $desde_hora;
  while (time_less($h, $hasta_hora)) {
    // Excluir si ya ocupado
    if (!isset($lookup[$h])) $slots[] = $h;
    $h = time_add_minutes($h, $duracion_min);
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Asignar turno - Administrador</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ===== Estilo consistente con tus vistas ===== */
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --brand:#1e88e5; --brand-dark:#1565c0;
  --ok:#22c55e; --warn:#f59e0b; --bad:#ef4444;
  --bgcard: rgba(255,255,255,.92);
}
body{
  font-family: Arial, sans-serif;
  background: url("https://i.pinimg.com/1200x/9b/e2/12/9be212df4fc8537ddc31c3f7fa147b42.jpg") no-repeat center center fixed;
  background-size: cover; color:#222;
}
nav{
  background:#fff; padding:12px 28px;
  box-shadow:0 4px 10px rgba(0,0,0,.08);
  position:sticky; top:0; z-index:10;
}
.nav-inner{display:flex;align-items:center;justify-content:space-between}
.nav-links{display:flex;gap:20px;align-items:center}
nav a{color:var(--brand);text-decoration:none;font-weight:bold}
nav a:hover{text-decoration:underline}
.btn{
  border:none;border-radius:8px;background:var(--brand);color:#fff;
  padding:8px 14px; cursor:pointer; font-weight:bold; text-decoration:none; display:inline-flex; align-items:center; gap:8px
}
.btn:hover{background:var(--brand-dark)}
.btn.gray{background:#6b7280}
.btn.alt{background:#10b981}
.container{padding:24px 18px;max-width:900px;margin:0 auto}
h1{
  color:#f5f8fa; text-shadow:1px 1px 3px rgba(0,0,0,.5);
  margin-bottom:16px; font-size:1.8rem
}
.card{
  background:var(--bgcard); backdrop-filter: blur(3px);
  border-radius:16px; padding:14px;
  box-shadow:0 8px 16px rgba(0,0,0,.12);
  margin-bottom:14px;
}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media (max-width:780px){.grid2{grid-template-columns:1fr}}
label{font-size:.9rem;color:#333}
input,select{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:10px;background:#fff}
.section-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;font-size:.95rem}
.table th,.table td{padding:8px 10px;border-bottom:1px solid #e8e8e8;text-align:left}
.table thead th{background:#f8fafc;color:#111}
.slot-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
@media (max-width:780px){.slot-grid{grid-template-columns:repeat(2,1fr)}}
.slot{
  display:flex;align-items:center;justify-content:center;
  background:#eef6ff;border:1px solid #dbeafe;border-radius:10px;
  padding:10px;font-weight:700
}
.slot button{
  all:unset; cursor:pointer; width:100%; text-align:center;
}
.alert{padding:12px 14px;border-radius:10px;margin:10px 0;font-weight:600}
.alert-red{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.badge{padding:4px 8px;border-radius:999px;font-size:.78rem;color:#fff;display:inline-block;background:var(--brand-dark)}
.backbar{display:flex;gap:10px;margin-bottom:10px}
</style>
</head>
<body>
  <!-- NAV -->
  <nav>
    <div class="nav-inner">
      <div class="nav-links">
        <a href="principalAdmi.php">Inicio</a>
        <a href="abmUsuarios.php">Usuarios</a>
        <a href="abmMedicos.php">Médicos</a>
        <a href="agendaCalendario.php">Agenda</a>
        <a href="reportes.php">Reportes</a>
      </div>
      <div class="nav-links">
        <span style="color:#333;font-weight:bold">Bienvenido, <?= h($nombre) ?></span>
        <a class="btn" href="../../Logica/General/cerrarSesion.php"><i class="fa fa-right-from-bracket"></i> Cerrar sesión</a>
      </div>
    </div>
  </nav>

  <!-- MAIN -->
  <main class="container">
    <div class="backbar">
      <a class="btn gray" href="principalAdmi.php"><i class="fa fa-house"></i> Inicio</a>
      <span class="badge"><i class="fa fa-hand-pointer"></i> Asignar turno</span>
    </div>

    <h1>Asignar turno</h1>

    <?php if ($error): ?>
      <div class="alert alert-red"><i class="fa fa-triangle-exclamation"></i> <?= h($error) ?></div>
    <?php endif; ?>

    <!-- Filtros / selección -->
    <div class="card">
      <div class="section-title"><h2><i class="fa fa-filter"></i> Selección</h2></div>
      <form method="get">
        <div class="grid2">
          <div>
            <label>Paciente</label>
            <select name="id_paciente" required>
              <option value="">Elegir paciente...</option>
              <?php foreach($pacientes as $p): ?>
                <option value="<?= (int)$p['id_paciente'] ?>" <?= ($id_paciente!=='' && (int)$id_paciente===(int)$p['id_paciente'])?'selected':'' ?>>
                  <?= h($p['paciente']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Especialidad (opcional)</label>
            <select name="id_especialidad" onchange="this.form.submit()">
              <option value="">Todas</option>
              <?php foreach($especialidades as $e): ?>
                <option value="<?= (int)$e['id_especialidad'] ?>" <?= ($id_especialidad!=='' && (int)$id_especialidad===(int)$e['id_especialidad'])?'selected':'' ?>>
                  <?= h($e['nombre_especialidad']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Médico</label>
            <select name="id_medico" required onchange="this.form.submit()">
              <option value="">Elegir médico...</option>
              <?php foreach($medicos as $m): ?>
                <option value="<?= (int)$m['id_medico'] ?>" <?= ($id_medico!=='' && (int)$id_medico===(int)$m['id_medico'])?'selected':'' ?>>
                  <?= h($m['medico']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Fecha</label>
            <input type="date" name="fecha" value="<?= h($fecha) ?>" onchange="this.form.submit()" required>
          </div>
          <div>
            <label>Desde (hora)</label>
            <input type="time" name="desde_hora" value="<?= h($desde_hora) ?>">
          </div>
          <div>
            <label>Hasta (hora)</label>
            <input type="time" name="hasta_hora" value="<?= h($hasta_hora) ?>">
          </div>
          <div>
            <label>Duración (min)</label>
            <select name="duracion_min">
              <?php foreach([10,15,20,30,45,60] as $opt): ?>
                <option value="<?= $opt ?>" <?= $duracion_min===$opt?'selected':'' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
          <button class="btn" type="submit"><i class="fa fa-magnifying-glass"></i> Buscar disponibilidad</button>
          <a class="btn gray" href="asignarTurno.php"><i class="fa fa-eraser"></i> Limpiar</a>
        </div>
      </form>
    </div>

    <!-- Slots disponibles -->
    <div class="card">
      <div class="section-title">
        <h2><i class="fa fa-clock"></i> Horarios disponibles</h2>
      </div>

      <?php if ($id_paciente==='' || $id_medico==='' || !$fecha): ?>
        <p style="color:#555">Elegí paciente, médico y fecha para ver disponibilidad.</p>
      <?php else: ?>
        <?php if (empty($slots)): ?>
          <p style="color:#555">No hay horarios disponibles con los filtros actuales.</p>
        <?php else: ?>
          <div class="slot-grid">
            <?php foreach($slots as $hslot): ?>
              <form method="post">
                <input type="hidden" name="id_paciente" value="<?= (int)$id_paciente ?>">
                <input type="hidden" name="id_medico" value="<?= (int)$id_medico ?>">
                <input type="hidden" name="fecha" value="<?= h($fecha) ?>">
                <input type="hidden" name="hora" value="<?= h($hslot) ?>">
                <div class="slot">
                  <button type="submit" title="Asignar <?= h($hslot) ?>"><?= h($hslot) ?></button>
                </div>
              </form>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div style="text-align:center;color:#eee;font-size:.85rem;margin-top:6px">Clínica AP · Asignar Turno</div>
  </main>
</body>
</html>
