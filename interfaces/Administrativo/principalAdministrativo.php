<?php
// ======= Seguridad / Sesión =======
$rol_requerido = 5; // 5 = Administrativo
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
$nombre = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Administrativo';

// ======= Conexión =======
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '-03:00'");

// ======= Helpers =======
function fetch_scalar(mysqli $conn, string $sql, array $params = [], string $types = '') {
    $val = 0;
    if ($stmt = $conn->prepare($sql)) {
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_row()) { $val = (int)$row[0]; }
        }
        $stmt->close();
    }
    return $val;
}
function fetch_rows(mysqli $conn, string $sql, array $params = [], string $types = '') {
    $out = [];
    if ($stmt = $conn->prepare($sql)) {
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($res && $row = $res->fetch_assoc()) { $out[] = $row; }
        }
        $stmt->close();
    }
    return $out;
}

// ======= KPIs =======
$kpi_total_turnos = fetch_scalar($conn, "SELECT COUNT(*) FROM turnos");
$kpi_pendientes   = fetch_scalar($conn, "SELECT COUNT(*) 
    FROM turnos t 
    JOIN estados e ON t.id_estado = e.id_estado 
    WHERE e.nombre_estado = 'pendiente'");
$kpi_confirmados  = fetch_scalar($conn, "SELECT COUNT(*) 
    FROM turnos t 
    JOIN estados e ON t.id_estado = e.id_estado 
    WHERE e.nombre_estado = 'confirmado'");
$kpi_atendidos    = fetch_scalar($conn, "SELECT COUNT(*) 
    FROM turnos t 
    JOIN estados e ON t.id_estado = e.id_estado 
    WHERE e.nombre_estado = 'atendido'");
$kpi_cancelados   = fetch_scalar($conn, "SELECT COUNT(*) 
    FROM turnos t 
    JOIN estados e ON t.id_estado = e.id_estado 
    WHERE e.nombre_estado = 'cancelado'");

    // Total de médicos
$kpi_medicos = fetch_scalar($conn, "
    SELECT COUNT(*) 
    FROM medicos m
    JOIN usuarios u ON u.id_usuario = m.id_usuario
");

// Total de pacientes
$kpi_pacientes = fetch_scalar($conn, "
    SELECT COUNT(*) 
    FROM pacientes p
    JOIN usuarios u ON u.id_usuario = p.id_usuario
");


// ======= Turnos de hoy (top 5) =======
$turnos_hoy = fetch_rows(
    $conn,
    "SELECT t.id_turno, t.fecha, t.hora, e.nombre_estado,
            CONCAT(up.apellido, ', ', up.nombre) AS paciente,
            COALESCE(esp.nombre_especialidad, '-') AS especialidad,
            um.nombre AS nombre_med, um.apellido AS apellido_med
     FROM turnos t
     LEFT JOIN pacientes p              ON p.id_paciente = t.id_paciente
     LEFT JOIN usuarios   up             ON up.id_usuario = p.id_usuario
     LEFT JOIN medicos   m              ON m.id_medico   = t.id_medico
     LEFT JOIN usuarios   um             ON um.id_usuario = m.id_usuario
     LEFT JOIN medico_especialidad me   ON me.id_medico = m.id_medico
     LEFT JOIN especialidades esp       ON esp.id_especialidad = me.id_especialidad
     LEFT JOIN estados e                 ON e.id_estado = t.id_estado
     WHERE DATE(t.fecha) = CURDATE()
     ORDER BY t.hora ASC
     LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Panel principal | Gestión de turnos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ===== Reset / Base ===== */
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --brand:#1e88e5; --brand-dark:#1565c0;
  --ok:#22c55e; --warn:#f59e0b; --bad:#ef4444;
  --bgcard: rgba(255,255,255,.92);
}
body{
  font-family: Arial, sans-serif;
  background: url("https://i.pinimg.com/1200x/9b/e2/12/9be212df4fc8537ddc31c3f7fa147b42.jpg") no-repeat center center fixed;
  background-size: cover;
  color:#222;
}

/* ===== Navbar ===== */
nav{
  background:#fff; padding:12px 28px;
  box-shadow:0 4px 10px rgba(0,0,0,.08);
  position:sticky; top:0; z-index:10;
}
.nav-inner{display:flex;align-items:center;justify-content:space-between}
.nav-links{display:flex;gap:20px;align-items:center}
nav a{color:var(--brand);text-decoration:none;font-weight:bold}
nav a:hover{text-decoration:underline}
.search{display:flex;gap:8px;align-items:center}
.search input{
  padding:8px 10px;border:1px solid #ddd;border-radius:8px;min-width:240px
}
.btn{
  border:none;border-radius:8px;background:var(--brand);color:#fff;
  padding:8px 14px; cursor:pointer; font-weight:bold
}
.btn:hover{background:var(--brand-dark)}

/* ===== Layout ===== */
.container{padding:32px 18px;max-width:1200px;margin:0 auto}
h1{
  color:#f5f8fa; text-shadow:1px 1px 3px rgba(0,0,0,.5);
  margin-bottom:22px; font-size:2.1rem
}
.grid{display:grid;gap:18px}
.grid.kpis{grid-template-columns:repeat(6,minmax(160px,1fr))}
@media (max-width:1100px){.grid.kpis{grid-template-columns:repeat(3,1fr)}}
@media (max-width:640px){.grid.kpis{grid-template-columns:repeat(2,1fr)}}

.card{
  background:var(--bgcard); backdrop-filter: blur(3px);
  border-radius:16px; padding:16px;
  box-shadow:0 8px 16px rgba(0,0,0,.12);
}
.kpi{display:flex;gap:12px;align-items:center}
.kpi .icon{
  width:46px;height:46px;display:grid;place-items:center;border-radius:12px;background:#eef6ff;color:var(--brand);font-size:20px
}
.kpi .txt small{display:block;color:#666}
.kpi .txt strong{font-size:1.4rem}

.section{
  margin-top:22px; display:grid; gap:18px;
  grid-template-columns: 2fr 1fr;
}
@media (max-width:960px){.section{grid-template-columns:1fr}}
.section h2{font-size:1.2rem;margin-bottom:8px;color:#133}

.table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
.table th,.table td{padding:10px;border-bottom:1px solid #e8e8e8;text-align:left}
.table thead th{background:#f8fafc;color:#111}
.badge{padding:4px 8px;border-radius:999px;font-size:.78rem;color:#fff;display:inline-block}
.badge.pendiente{background:var(--warn)}
.badge.confirmado{background:var(--brand-dark)}
.badge.atendido{background:var(--ok)}
.badge.cancelado{background:var(--bad)}

.actions{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
@media (max-width:640px){.actions{grid-template-columns:1fr}}
.action-btn{
  display:flex;align-items:center;gap:10px;justify-content:center;
  padding:14px;border-radius:12px;background:#0ea5e9;color:#fff;text-decoration:none;font-weight:bold
}
.action-btn .fa{font-size:18px}
.action-btn.alt{background:#10b981}
.action-btn.warn{background:#f59e0b}
.action-btn.gray{background:#6b7280}
.action-btn.purple{background:#7c3aed}
.action-btn.indigo{background:#4f46e5}

.footer{margin-top:16px;color:#eee;text-align:center;font-size:.85rem}
</style>
</head>
<body>

<!-- ===== NAV nuevo ===== -->
<?php include('navAdministrativo.php'); ?>

  <!-- ===== NAV viejo ===== 
  <nav>
    <div class="nav-inner">
      <div class="nav-links">
        <a href="principalAdministrativo.php">Inicio</a>
        <div class="search">
          <input type="text" placeholder="Buscar paciente, médico o turno..." />
          <button class="btn" type="button">Buscar</button>
        </div>
      </div>
      <div class="nav-links">
        <span style="color:#333;font-weight:bold">Bienvenido, <?= htmlspecialchars($nombre) ?></span>
        <a class="btn" href="../../Logica/General/cerrarSesion.php" title="Salir"><i class="fa fa-right-from-bracket"></i> Cerrar sesión</a>
      </div>
    </div>
  </nav>
  -->

  <!-- ===== MAIN ===== -->
  <main class="container">
    <h1> Panel Principal </h1>

    <!-- KPIs -->
    <div class="grid kpis">
      <div class="card kpi"><div class="icon"><i class="fa fa-calendar-check"></i></div>
        <div class="txt"><small>Total turnos</small><strong><?= $kpi_total_turnos ?></strong></div></div>
      <div class="card kpi"><div class="icon"><i class="fa fa-clock"></i></div>
        <div class="txt"><small>Pendientes</small><strong><?= $kpi_pendientes ?></strong></div></div>
      <div class="card kpi"><div class="icon"><i class="fa fa-circle-check"></i></div>
        <div class="txt"><small>Confirmados</small><strong><?= $kpi_confirmados ?></strong></div></div>
      <div class="card kpi"><div class="icon"><i class="fa fa-user-doctor"></i></div>
        <div class="txt"><small>Médicos</small><strong><?= $kpi_medicos ?></strong></div></div>
      <div class="card kpi"><div class="icon"><i class="fa fa-users"></i></div>
        <div class="txt"><small>Pacientes</small><strong><?= $kpi_pacientes ?></strong></div></div>
      <div class="card kpi"><div class="icon"><i class="fa fa-check-double"></i></div>
        <div class="txt"><small>Atendidos / Cancelados</small>
          <strong><?= $kpi_atendidos ?> / <?= $kpi_cancelados ?></strong>
        </div></div>
    </div>

    <!-- Supervisión + Acciones -->
    <div class="section">
      <!-- Turnos hoy -->
      <div class="card">
        <h2><i class="fa fa-calendar-day"></i> Próximos turnos de hoy</h2>
        <table class="table">
          <thead><tr><th>Hora</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Estado</th></tr></thead>
          <tbody>
          <?php if (empty($turnos_hoy)): ?>
            <tr><td colspan="5" style="color:#666">No hay turnos para hoy.</td></tr>
          <?php else: foreach ($turnos_hoy as $t): 
            $estado = strtolower($t['nombre_estado'] ?? ''); ?>
            <tr>
              <td><?= htmlspecialchars(substr($t['hora'] ?? '',0,5)) ?></td>
              <td><?= htmlspecialchars($t['paciente'] ?? '-') ?></td>
              <td><?= htmlspecialchars(($t['apellido_med'] ?? '').', '.($t['nombre_med'] ?? '')) ?></td>
              <td><?= htmlspecialchars($t['especialidad'] ?? '-') ?></td>
              <td><span class="badge <?= $estado ?>"><?= htmlspecialchars($t['nombre_estado'] ?? '-') ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
        <div style="margin-top:10px;display:flex;gap:10px;">
          <a class="btn" href="turnosListado.php"><i class="fa fa-list"></i> Ver listado global</a>
        </div>
      </div>

     
      <div class="card">
        <h2><i class="fa fa-bolt"></i> Acciones rápidas</h2>
        <div class="actions" style="margin-top:8px">
 <!--   
<a class="action-btn" href="abmMedicos.php" title="ABM de Médicos">
  <i class="fa fa-user-doctor"></i> Gestionar Médicos
</a> 
-->

<a class="action-btn alt" href="abmPacientes.php" title="ABM de Pacientes">
  <i class="fa fa-user-injured"></i> Gestionar Pacientes
</a>

 <!-- 
<a class="action-btn purple" href="abmTecnicos.php" title="ABM de Técnicos">
  <i class="fa fa-user-cog"></i> Gestionar Técnicos
</a>
-->

<a class="action-btn indigo" href="gestionarTurnos.php" title="Turnos / Feriados / Excepciones">
  <i class="fa fa-calendar-alt"></i> Gestionar Turnos
</a>

                    
        </div>
      </div>
    </div>

    <div class="footer">Clínica AP · Panel Administrador</div>
  </main>
</body>
</html>
