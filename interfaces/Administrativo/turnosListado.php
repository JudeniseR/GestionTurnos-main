<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 5; // Administrativo
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$nombreAdmin = $_SESSION['nombre'] ?? 'Administrativo';

// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// ===== Helpers =====
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qget($k,$d=null){ return isset($_GET[$k])?$_GET[$k]:$d; }

// ===== Catálogos =====
$ESTADOS = [];
$res = $conn->query("SELECT id_estado, nombre_estado FROM estados ORDER BY id_estado");
if ($res){ while($row=$res->fetch_assoc()){ $ESTADOS[(int)$row['id_estado']] = $row['nombre_estado']; } $res->close(); }

$MEDICOS = [];
$res = $conn->query("
  SELECT m.id_medico, u.apellido, u.nombre
  FROM medicos m
  JOIN usuarios u ON u.id_usuario = m.id_usuario
  ORDER BY u.apellido, u.nombre
");
if ($res){ while($row=$res->fetch_assoc()){ $MEDICOS[(int)$row['id_medico']] = $row['apellido'].', '.$row['nombre']; } $res->close(); }

// ===== Filtros =====
$f_medico = (int)qget('f_medico', 0);
$f_estado = (int)qget('f_estado', 0);
$f_desde  = qget('f_desde','');
$f_hasta  = qget('f_hasta','');
$q        = trim((string)qget('q',''));

// ===== Paginación =====
$per_page = 50;
$page = max(1, (int)qget('page', 1));
$offset = ($page - 1) * $per_page;

// ===== WHERE / FROM base =====
$sql_base = "
  FROM turnos t
  LEFT JOIN estados e         ON e.id_estado = t.id_estado
  LEFT JOIN pacientes p       ON p.id_paciente = t.id_paciente
  LEFT JOIN usuarios up       ON up.id_usuario = p.id_usuario
  LEFT JOIN medicos m         ON m.id_medico = t.id_medico
  LEFT JOIN usuarios um       ON um.id_usuario = m.id_usuario
  LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
  LEFT JOIN especialidades esp     ON esp.id_especialidad = me.id_especialidad
  WHERE 1=1
";
$w = []; $params = []; $types = '';

if ($f_medico > 0) { $w[] = " t.id_medico = ? "; $types .= 'i'; $params[] = $f_medico; }
if ($f_estado > 0) { $w[] = " t.id_estado = ? "; $types .= 'i'; $params[] = $f_estado; }
if ($f_desde !== '') { $w[] = " t.fecha >= ? "; $types .= 's'; $params[] = $f_desde; }
if ($f_hasta !== '') { $w[] = " t.fecha <= ? "; $types .= 's'; $params[] = $f_hasta; }
if ($q !== '') {
  $w[] = " ( CONCAT_WS(' ', up.apellido, up.nombre) LIKE ? OR CONCAT_WS(' ', um.apellido, um.nombre) LIKE ? ) ";
  $types .= 'ss';
  $like = '%'.$q.'%';
  $params[] = $like; $params[] = $like;
}
$where = $w ? (' AND '.implode(' AND ',$w)) : '';

// ===== Conteo total =====
$sql_count = "SELECT COUNT(DISTINCT t.id_turno) ".$sql_base.$where;
$stmt = $conn->prepare($sql_count);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$total_rows = 0;
$r = $stmt->get_result(); if ($r && $row=$r->fetch_row()){ $total_rows = (int)$row[0]; }
$stmt->close();

$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ===== Listado =====
$sql_list = "
  SELECT
    t.id_turno, t.fecha, t.hora, t.observaciones,
    t.id_estado, e.nombre_estado,
    up.apellido AS ap_pac, up.nombre AS no_pac,
    um.apellido AS ap_med, um.nombre AS no_med,
    GROUP_CONCAT(DISTINCT esp.nombre_especialidad ORDER BY esp.nombre_especialidad SEPARATOR ', ') AS especialidad
  ".$sql_base.$where."
  GROUP BY t.id_turno
  ORDER BY t.fecha DESC, t.hora ASC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql_list);
$params2 = $params;
$types2 = $types.'ii';
$params2[] = $per_page; $params2[] = $offset;
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$list = [];
$res = $stmt->get_result();
while($res && $row=$res->fetch_assoc()){ $list[]=$row; }
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Listado de Turnos | Gestión de turnos</title>
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
.btn.gray{background:#6b7280}
.btn.sm{padding:6px 10px;font-size:.9rem}
.container{padding:32px 18px;max-width:1200px;margin:0 auto}
h1{color:#f5f8fa;text-shadow:1px 1px 3px rgba(0,0,0,.5);margin-bottom:12px;font-size:2rem}
.card{background:var(--bgcard);backdrop-filter:blur(3px);border-radius:16px;padding:16px;box-shadow:0 8px 16px rgba(0,0,0,.12);margin-bottom:18px;border:1px solid rgba(0,0,0,.03)}
.table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
.table th,.table td{padding:10px;border-bottom:1px solid #e8e8e8;text-align:left}
.table thead th{background:#f8fafc;color:#111}
.badge{padding:4px 8px;border-radius:999px;font-size:.78rem;color:#fff;display:inline-block}
.badge.pendiente{background:var(--warn)}
.badge.confirmado{background:var(--brand-dark)}
.badge.atendido{background:var(--ok)}
.badge.cancelado{background:var(--bad)}
.form-row{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
input[type="date"], input[type="text"], select{padding:10px;border:1px solid var(--border);border-radius:10px;background:#fff}
.pager{display:flex;gap:8px;justify-content:flex-end;align-items:center;margin-top:10px}
.pager .info{color:#333}
.backbar{display:flex;gap:10px;margin:8px 0 16px}
</style>
</head>
<body>
<!-- ===== NAV nuevo ===== -->
<?php include('navAdministrativo.php'); ?>

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
    <h1><i class="fa fa-list"></i> Listado de turnos</h1>

    <div class="backbar">
      <a class="btn gray" href="principalAdministrativo.php"><i class="fa fa-house"></i> Volver al inicio</a>
      <a class="btn" href="gestionarTurnos.php?tab=turnos"><i class="fa fa-calendar-plus"></i> Ir Gestionar Turnos</a>
    </div>

    <div class="card">
      <form method="get" class="form-row">
        <div>
          <label style="display:block;font-weight:700;margin-bottom:6px">Médico</label>
          <select name="f_medico">
            <option value="0">Todos</option>
            <?php foreach($MEDICOS as $idm=>$nm): ?>
              <option value="<?= (int)$idm ?>" <?= ($f_medico===$idm?'selected':'') ?>><?= esc($nm) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-weight:700;margin-bottom:6px">Estado</label>
          <select name="f_estado">
            <option value="0">Todos</option>
            <?php foreach($ESTADOS as $idE=>$nomE): ?>
              <option value="<?= (int)$idE ?>" <?= ($f_estado===$idE?'selected':'') ?>><?= esc(ucfirst($nomE)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-weight:700;margin-bottom:6px">Desde</label>
          <input type="date" name="f_desde" value="<?= esc($f_desde) ?>">
        </div>
        <div>
          <label style="display:block;font-weight:700;margin-bottom:6px">Hasta</label>
          <input type="date" name="f_hasta" value="<?= esc($f_hasta) ?>">
        </div>
        <div style="min-width:240px">
          <label style="display:block;font-weight:700;margin-bottom:6px">Buscar (paciente o médico)</label>
          <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Ej: Pérez, Juan">
        </div>
        <div>
          <button class="btn sm" type="submit"><i class="fa fa-search"></i> Filtrar</button>
          <a class="btn-outline sm" href="turnosListado.php"><i class="fa fa-eraser"></i> Limpiar</a>
        </div>
      </form>
    </div>

    <!-- Tabla -->
    <div class="card" style="overflow:auto">
      <table class="table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Paciente</th>
            <th>Médico</th>
            <th>Especialidad</th>
            <th>Estado</th>
            <th>Obs.</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($list)): ?>
            <tr><td colspan="7" style="color:#666">No hay turnos para los filtros aplicados.</td></tr>
          <?php else: foreach($list as $t): 
            $badge = strtolower($t['nombre_estado'] ?? '');
          ?>
            <tr>
              <td><?= esc($t['fecha']) ?></td>
              <td><?= esc(substr($t['hora'],0,5)) ?></td>
              <td><?= esc(($t['ap_pac']??'-').', '.($t['no_pac']??'')) ?></td>
              <td><?= esc(($t['ap_med']??'-').', '.($t['no_med']??'')) ?></td>
              <td><?= esc($t['especialidad'] ?? '-') ?></td>
              <td><span class="badge <?= esc($badge) ?>"><?= esc(ucfirst($t['nombre_estado'] ?? '-')) ?></span></td>
              <td><?= esc($t['observaciones'] ?? '') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <!-- Paginación -->
      <div class="pager">
        <span class="info">Mostrando <?= count($list) ?> de <?= (int)$total_rows ?> registros</span>
        <?php
          $qs = $_GET; unset($qs['page']);
          $base = 'turnosListado.php?'.http_build_query($qs);
        ?>
        <?php if ($page > 1): ?>
          <a class="btn-outline sm" href="<?= esc($base.'&page=1') ?>"><i class="fa fa-angles-left"></i></a>
          <a class="btn-outline sm" href="<?= esc($base.'&page='.($page-1)) ?>"><i class="fa fa-angle-left"></i> Anterior</a>
        <?php endif; ?>
        <span style="padding:6px 10px;background:#fff;border:1px solid var(--border);border-radius:8px">
          Página <?= (int)$page ?> / <?= (int)$total_pages ?>
        </span>
        <?php if ($page < $total_pages): ?>
          <a class="btn-outline sm" href="<?= esc($base.'&page='.($page+1)) ?>">Siguiente <i class="fa fa-angle-right"></i></a>
          <a class="btn-outline sm" href="<?= esc($base.'&page='.$total_pages) ?>"><i class="fa fa-angles-right"></i></a>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>
