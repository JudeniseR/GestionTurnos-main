<?php 
// ===== Seguridad / Sesión =====
$rol_requerido = 2; // Médico
require_once('_boot_medico.php');
require_once('../../Logica/General/verificarSesion.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$id_medico = $_SESSION['id_medico'] ?? null;
$nombre    = $_SESSION['nombre']   ?? '';
$apellido  = $_SESSION['apellido'] ?? '';

$displayRight = trim(mb_strtoupper($apellido) . ', ' . mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'));
$displayHello = trim(mb_convert_case($nombre . ' ' . $apellido, MB_CASE_TITLE, 'UTF-8'));

// ===== Datos desde la BD =====
require_once('../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// KPIs de HOY
$hoy = date('Y-m-d');
$kpi_totales = $kpi_confirmados = $kpi_atendidos = $kpi_cancelados = 0;

function scalar(mysqli $c, string $sql, array $bind = [], string $types = ''): int {
  $v = 0;
  if ($st = $c->prepare($sql)) {
    if ($bind) { $st->bind_param($types, ...$bind); }
    $st->execute();
    if ($r = $st->get_result()) { $row = $r->fetch_row(); $v = (int)($row[0] ?? 0); }
    $st->close();
  }
  return $v;
}

$kpi_totales = scalar($conn,
  "SELECT COUNT(*) FROM turnos WHERE id_medico=? AND DATE(fecha)=?",
  [$id_medico, $hoy], 'is'
);

// Si existe tabla estado, usamos su nombre_estado (confirmado/atendido/cancelado)
$hasEstado = ($conn->query("SHOW TABLES LIKE 'estado'")->num_rows) > 0;
if ($hasEstado) {
  $kpi_confirmados = scalar($conn,
    "SELECT COUNT(*) FROM turnos t JOIN estado e ON e.id_estado=t.id_estado
     WHERE t.id_medico=? AND DATE(t.fecha)=? AND e.nombre_estado='confirmado'",
    [$id_medico, $hoy], 'is');
  $kpi_atendidos = scalar($conn,
    "SELECT COUNT(*) FROM turnos t JOIN estado e ON e.id_estado=t.id_estado
     WHERE t.id_medico=? AND DATE(t.fecha)=? AND e.nombre_estado='atendido'",
    [$id_medico, $hoy], 'is');
  $kpi_cancelados = scalar($conn,
    "SELECT COUNT(*) FROM turnos t JOIN estado e ON e.id_estado=t.id_estado
     WHERE t.id_medico=? AND DATE(t.fecha)=? AND e.nombre_estado='cancelado'",
    [$id_medico, $hoy], 'is');
} else {
  // Fallback por IDs de estado (ajustar si difiere)
  $kpi_confirmados = scalar($conn,
    "SELECT COUNT(*) FROM turnos WHERE id_medico=? AND DATE(fecha)=? AND id_estado=2",
    [$id_medico, $hoy], 'is');
  $kpi_atendidos = scalar($conn,
    "SELECT COUNT(*) FROM turnos WHERE id_medico=? AND DATE(fecha)=? AND id_estado=3",
    [$id_medico, $hoy], 'is');
  $kpi_cancelados = scalar($conn,
    "SELECT COUNT(*) FROM turnos WHERE id_medico=? AND DATE(fecha)=? AND id_estado=4",
    [$id_medico, $hoy], 'is');
}

// Próximos turnos (solo FUTUROS)
$nowDate = date('Y-m-d');
$nowTime = date('H:i:s');

$sqlProx = "
  SELECT
    t.id_turno,
    DATE(t.fecha)   AS f,
    TIME_FORMAT(t.hora, '%H:%i') AS hhmm,
    t.id_paciente,
    COALESCE(CONCAT(u.apellido, ', ', u.nombre), '') AS paciente,
    COALESCE(p.nro_documento, '') AS dni,
    ".($hasEstado ? "e.nombre_estado" : "CASE t.id_estado
        WHEN 2 THEN 'confirmado'
        WHEN 3 THEN 'atendido'
        WHEN 4 THEN 'cancelado'
        ELSE 'pendiente' END")." AS estado
  FROM turnos t
  LEFT JOIN pacientes p ON p.id_paciente=t.id_paciente
  LEFT JOIN usuario   u ON u.id_usuario=p.id_usuario
  ".($hasEstado ? "LEFT JOIN estado e ON e.id_estado=t.id_estado" : "")."
  WHERE t.id_medico=?
    AND (
      DATE(t.fecha) > ?
      OR (DATE(t.fecha) = ? AND TIME(t.hora) >= ?)
    )
  ORDER BY DATE(t.fecha) ASC, TIME(t.hora) ASC
  LIMIT 8
";
$prox = [];
if ($st = $conn->prepare($sqlProx)) {
  $st->bind_param('isss', $id_medico, $nowDate, $nowDate, $nowTime);
  $st->execute();
  $rs = $st->get_result();
  while ($r = $rs->fetch_assoc()) { $prox[] = $r; }
  $st->close();
}

// Mapeo de estado -> clase/etiqueta (solo para timeline)
function pillFor(string $estado): array {
  $e = strtolower(trim($estado));
  if ($e === 'confirmado') return ['pill--ok',     'Confirmado', 'fa-check'];
  if ($e === 'atendido')   return ['pill--ok',     'Atendido',   'fa-user-doctor'];
  if ($e === 'cancelado')  return ['pill--danger', 'Cancelado',  'fa-xmark'];
  return ['pill--warn', 'Pendiente', 'fa-hourglass-half'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Médico</title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    :root{ --primary:#1e88e5; --primary-600:#1565c0; --text:#1f2937; --muted:#6b7280; --card:#fff; --shadow:0 10px 25px rgba(0,0,0,.08); --radius:16px; }
    *{box-sizing:border-box}
    
    body{
      margin:0;
      font-family:"Inter",system-ui,Segoe UI,Roboto,Arial,sans-serif;
      color:var(--text);
      background: url("../../assets/img/fondo-medico.png") no-repeat center center fixed;
      background-size: cover;
    }

    .topbar{ position:sticky; top:0; z-index:1000; background:rgba(255,255,255,.95); backdrop-filter:blur(6px); border-bottom:1px solid #e5e7eb; }
    .navbar{ max-width:1280px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; padding:16px 48px; gap:32px; }
    .nav-left{display:flex; align-items:center; gap:36px;}
    .brand{ color:#1e88e5; font-weight:800; text-decoration:none; display:flex; align-items:center; gap:10px; }
    .nav-link{ color:#1e88e5; text-decoration:none; font-weight:700; } .nav-link:hover{text-decoration:underline}
    .nav-center{flex:1; display:flex; justify-content:center;}
    .nav-search{ display:flex; gap:10px; } .nav-search input{ width:320px; max-width:42vw; padding:10px 12px; border:1px solid #d1d5db; border-radius:10px; outline:none; background:#fff; }
    .nav-search button{ border:none; padding:10px 14px; border-radius:10px; background:#1e88e5; color:#fff; font-weight:700; cursor:pointer; }
    .nav-search button:hover{ background:#1565c0; }
    .nav-right{display:flex; align-items:center; gap:16px;}
    .logout{ color:#1e88e5; text-decoration:none; font-weight:700; } .logout:hover{text-decoration:underline}
    .user{ display:flex; align-items:center; gap:10px; } .user-name{ font-weight:800; white-space:nowrap; color:#1f2937; }
    .user-avatar{ width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; background:#e8f1fb; color:#1e88e5; border:1px solid #c7ddfc; }

    .wrap{ max-width:1200px; margin:28px auto; padding:0 24px 48px; }
    .hero{ color:#fff; text-shadow:0 1px 3px rgba(0,0,0,.35); margin:24px 0 10px; }
    .hero h1{font-size:34px; margin:0 0 6px} .hero .sub{opacity:.9; font-weight:500}

    /* KPIs estilo “tarjeta” */
    .kpis{ display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin:22px 0 26px; }
    .kpi{ background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); padding:18px; display:flex; align-items:center; gap:12px; }
    .kpi i{font-size:22px; color:#1e88e5}
    .kpi .label{font-size:12px; color:var(--muted)}
    .kpi .value{font-size:24px; font-weight:700}

    .grid{ display:grid; grid-template-columns:2fr 1.1fr; gap:18px; }
    .card{ background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); padding:22px; } .card h2{margin:0 0 14px; font-size:20px}

    .actions{ display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
    .action{ border:1px solid #e5e7eb; border-radius:18px; padding:22px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:space-between; gap:14px; min-height:160px; transition:.15s; background:linear-gradient(#fff,#fafafa); }
    .action:hover{ transform:translateY(-4px); box-shadow:var(--shadow); border-color:#d1d5db }
    .action i{font-size:42px; color:#1e88e5} .action .desc{color:var(--muted); font-size:14px}
    .btn{ display:inline-flex; align-items:center; gap:8px; background:#1e88e5; color:#fff; border:none; border-radius:10px; padding:9px 14px; font-weight:700; cursor:pointer; width:100%; justify-content:center; transition:.15s; }
    .btn:hover{ background:#1565c0; transform:translateY(-1px) }

    .timeline{display:flex; flex-direction:column; gap:10px}
    .tl-item{ display:flex; align-items:center; justify-content:space-between; gap:10px; border:1px solid #e5e7eb; padding:12px 14px; border-radius:12px; background:#fff; }
    .tl-left{display:flex; align-items:center; gap:10px}
    .time{font-weight:700; width:64px}
    .name{font-weight:600} .muted{color:#6b7280; font-size:13px}
    .pill{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px }
    .pill--ok{background:#e8f7ee; color:#166534; border:1px solid #a7f3d0}
    .pill--warn{background:#fff7ed; color:#7c2d12; border:1px solid #fed7aa}
    .pill--danger{background:#fee2e2; color:#7f1d1d; border:1px solid #fecaca}

    .legend{display:flex; gap:14px; align-items:center; flex-wrap:wrap; font-size:13px; color:#6b7280}
    .dot{width:12px;height:12px;border-radius:3px;display:inline-block} .dot--free{background:#16a34a} .dot--busy{background:#dc2626} .dot--past{background:#9ca3af}

    @media (max-width:960px){ .kpis{grid-template-columns:repeat(2,1fr)} .grid{grid-template-columns:1fr} }
    @media (max-width:800px){ .navbar{padding:14px 20px} .nav-center{display:none} }
  </style>
</head>
<body>
  <!-- NAV -->
  <header class="topbar">
    <nav class="navbar" aria-label="Barra de navegación">
      <div class="nav-left">
        <a class="brand" href="principalMed.php"><i class="fa-solid fa-stethoscope"></i> Inicio</a>
        <a class="nav-link" href="agenda.php">Agenda</a>
        <a class="nav-link" href="turnos.php">Gestionar turnos</a>
      </div>

      <div class="nav-center">
        <form class="nav-search" action="buscar.php" method="get" role="search">
          <input type="text" name="q" placeholder="Buscar paciente, DNI o turno (#)..." aria-label="Buscar">
          <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
        </form>
      </div>

      <div class="nav-right">
        <a class="logout" href="../../Logica/General/cerrarSesion.php" title="Cerrar sesión">Cerrar sesión</a>
        <div class="user" title="Médico">
          <span class="user-name"><?= htmlspecialchars($displayRight ?: 'MÉDICO') ?></span>
          <span class="user-avatar" aria-hidden="true"><i class="fa-solid fa-user-doctor"></i></span>
        </div>
      </div>
    </nav>
  </header>

  <!-- CONTENIDO -->
  <main class="wrap" role="main">
    <section class="hero" aria-label="Bienvenida">
      <h1>Hola, <?= htmlspecialchars($displayHello ?: 'Médico') ?></h1>
      <div class="sub">Resumen del día y accesos rápidos a tu agenda y turnos.</div>
    </section>

    <!-- KPIs (números desde BD) -->
    <section class="kpis" aria-label="Indicadores del día">
      <div class="kpi">
        <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
        <div>
          <div class="label">Turnos hoy</div>
          <div class="value"><?= (int)$kpi_totales ?></div>
        </div>
      </div>

      <div class="kpi">
        <i class="fa-solid fa-user-check" aria-hidden="true"></i>
        <div>
          <div class="label">Confirmados</div>
          <div class="value"><?= (int)$kpi_confirmados ?></div>
        </div>
      </div>

      <div class="kpi">
        <i class="fa-solid fa-user-doctor" aria-hidden="true"></i>
        <div>
          <div class="label">Atendidos</div>
          <div class="value"><?= (int)$kpi_atendidos ?></div>
        </div>
      </div>

      <div class="kpi">
        <i class="fa-solid fa-ban" aria-hidden="true"></i>
        <div>
          <div class="label">Cancelados</div>
          <div class="value"><?= (int)$kpi_cancelados ?></div>
        </div>
      </div>
    </section>

    <div class="grid">
      <!-- Acciones -->
      <section class="card" aria-label="Accesos rápidos">
        <h2>Acciones rápidas</h2>
        <div class="actions">
          <article class="action" role="button" tabindex="0"
                   onclick="location.href='agenda.php'"
                   onkeypress="if(event.key==='Enter') location.href='agenda.php'">
            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
            <div>
              <h3 style="margin:0 0 6px;">Gestionar Agenda</h3>
              <p class="desc">Definí horarios, bloqueos y disponibilidad.</p>
            </div>
            <button class="btn" aria-label="Ir a gestionar agenda">Ir a Agenda</button>
          </article>

          <article class="action" role="button" tabindex="0"
                   onclick="location.href='turnos.php'"
                   onkeypress="if(event.key==='Enter') location.href='turnos.php'">
            <i class="fa-solid fa-list-check" aria-hidden="true"></i>
            <div>
              <h3 style="margin:0 0 6px;">Gestionar Turnos</h3>
              <p class="desc">Revisá pacientes, estados y observaciones.</p>
            </div>
            <button class="btn" aria-label="Ir a gestionar turnos">Ir a Turnos</button>
          </article>
        </div>

        <div class="legend" aria-label="Leyenda de estados" style="margin-top:16px">
          <span><span class="dot dot--free"></span> Disponible</span>
          <span><span class="dot dot--busy"></span> Ocupado/Bloqueado</span>
          <span><span class="dot dot--past"></span> Pasado</span>
        </div>
      </section>

      <!-- Próximos turnos -->
      <aside class="card" aria-label="Próximos turnos">
        <h2>Próximos turnos</h2>
        <div class="timeline">
          <?php if (empty($prox)): ?>
            <div class="tl-item">
              <div class="tl-left">
                <div class="time">—</div>
                <div>
                  <div class="name">No hay turnos futuros</div>
                  <div class="muted">Cuando se agenden, aparecerán aquí.</div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($prox as $t):
              [$cls,$lbl,$ico] = pillFor($t['estado'] ?? '');
              $nom = trim($t['paciente'] ?? '');
              if ($nom==='') { $nom = 'Paciente '.$t['id_paciente']; }
              $dni = $t['dni'] ? 'DNI • '.htmlspecialchars($t['dni']) : '—';
            ?>
              <div class="tl-item">
                <div class="tl-left">
                  <div class="time"><?= htmlspecialchars($t['hhmm']) ?></div>
                  <div>
                    <div class="name"><?= htmlspecialchars($nom) ?></div>
                    <div class="muted"><?= $dni ?> · <?= htmlspecialchars(date('d/m', strtotime($t['f']))) ?></div>
                  </div>
                </div>
                <span class="pill <?= $cls ?>"><i class="fa-solid <?= $ico ?>"></i> <?= htmlspecialchars($lbl) ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div style="margin-top:14px; display:flex; gap:10px; justify-content:flex-end">
          <a class="btn" style="text-decoration:none" href="turnos.php">
            <i class="fa-solid fa-list"></i> Ver todos
          </a>
        </div>
      </aside>
    </div>
  </main>
</body>
</html>
