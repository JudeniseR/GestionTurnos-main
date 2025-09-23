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
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fetch_rows(mysqli $conn, string $sql, array $params = [], string $types = ''){
  $out=[]; if($st=$conn->prepare($sql)){ if($types && $params){$st->bind_param($types,...$params);} $st->execute(); $r=$st->get_result(); while($r && $row=$r->fetch_assoc()) $out[]=$row; $st->close(); } return $out;
}
function fetch_scalar(mysqli $conn, string $sql, array $params = [], string $types = ''){
  $val=0; if($st=$conn->prepare($sql)){ if($types && $params){$st->bind_param($types,...$params);} $st->execute(); $r=$st->get_result(); if($r && $row=$r->fetch_row()) $val=(int)$row[0]; $st->close(); } return $val;
}
function fetch_one(mysqli $conn, string $sql, array $params = [], string $types = ''){
  $row=null; if($st=$conn->prepare($sql)){ if($types && $params){$st->bind_param($types,...$params);} $st->execute(); $r=$st->get_result(); if($r) $row=$r->fetch_assoc(); $st->close(); } return $row;
}

// ===== Estados (ids por nombre) =====
$mapEstados = [];
foreach (fetch_rows($conn, "SELECT id_estado, nombre_estado FROM estado") as $e) {
  $mapEstados[strtolower($e['nombre_estado'])] = (int)$e['id_estado'];
}

// ===== Acciones (POST) =====
// Reglas: pendiente -> confirmar -> atendido ; pendiente|confirmado -> cancelado
$ok = $_GET['ok'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'], $_POST['id_turno'])) {
  $id_turno = (int)$_POST['id_turno'];
  $accion = $_POST['accion']; // confirmar|cancelar|atender

  $t = fetch_one($conn, "
    SELECT t.id_turno, t.id_estado, e.nombre_estado
    FROM turnos t JOIN estado e ON e.id_estado=t.id_estado
    WHERE t.id_turno = ? LIMIT 1
  ", [$id_turno], 'i');
  if ($t){
    $estadoActual = strtolower($t['nombre_estado']);
    $nuevo = null;
    if ($accion==='confirmar' && $estadoActual==='pendiente') $nuevo = $mapEstados['confirmado'] ?? null;
    if ($accion==='cancelar' && in_array($estadoActual, ['pendiente','confirmado'])) $nuevo = $mapEstados['cancelado'] ?? null;
    if ($accion==='atender'  && $estadoActual==='confirmado') $nuevo = $mapEstados['atendido'] ?? null;

    if ($nuevo){
      if ($st=$conn->prepare("UPDATE turnos SET id_estado=? WHERE id_turno=?")) {
        $st->bind_param('ii', $nuevo, $id_turno);
        $st->execute(); $st->close();
        header("Location: turnosListado.php?ok=".$accion); exit;
      } else { header("Location: turnosListado.php?ok=error"); exit; }
    } else { header("Location: turnosListado.php?ok=transicion_invalida"); exit; }
  } else { header("Location: turnosListado.php?ok=no_encontrado"); exit; }
}

// ===== Datos para selects =====
$medicos = fetch_rows($conn, "
  SELECT m.id_medico, CONCAT(u.apellido, ', ', u.nombre) AS medico
  FROM medicos m JOIN usuario u ON u.id_usuario=m.id_usuario
  ORDER BY u.apellido, u.nombre");

$estados = fetch_rows($conn, "SELECT id_estado, nombre_estado FROM estado ORDER BY nombre_estado");

// ===== Filtros =====
$hoy = date('Y-m-d');
$desde = $_GET['desde'] ?? $hoy;
$hasta = $_GET['hasta'] ?? $hoy;
$id_medico = $_GET['id_medico'] ?? '';
$id_estado = $_GET['id_estado'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');

// Paginación
$pag = max(1, (int)($_GET['pag'] ?? 1));
$pp  = 20;
$off = ($pag-1)*$pp;

// WHERE dinámico
$where = " WHERE DATE(t.fecha) BETWEEN ? AND ? ";
$params = [$desde, $hasta];
$types  = "ss";

if ($id_medico !== '' && ctype_digit($id_medico)) {
  $where .= " AND t.id_medico = ? ";  $params[] = (int)$id_medico;  $types .= "i";
}
if ($id_estado !== '' && ctype_digit($id_estado)) {
  $where .= " AND t.id_estado = ? ";  $params[] = (int)$id_estado;  $types .= "i";
}
if ($buscar !== '') {
  // Busca por paciente (nombre o apellido)
  $where .= " AND (UPPER(up.nombre) LIKE UPPER(CONCAT('%', ?, '%')) OR UPPER(up.apellido) LIKE UPPER(CONCAT('%', ?, '%'))) ";
  $params[] = $buscar; $params[] = $buscar; $types .= "ss";
}

// Total para paginación
$total_rows = fetch_scalar($conn, "
  SELECT COUNT(*)
  FROM turnos t
  LEFT JOIN pacientes p ON p.id_paciente=t.id_paciente
  LEFT JOIN usuario up ON up.id_usuario=p.id_usuario
  LEFT JOIN medicos m ON m.id_medico=t.id_medico
  LEFT JOIN usuario um ON um.id_usuario=m.id_usuario
  LEFT JOIN estado e ON e.id_estado=t.id_estado
  $where
", $params, $types);

$total_pages = max(1, (int)ceil($total_rows / $pp));

// Listado
$sql_list = "
SELECT 
  t.id_turno, t.fecha, t.hora,
  CONCAT(up.apellido, ', ', up.nombre) AS paciente,
  CONCAT(um.apellido, ', ', um.nombre) AS medico,
  e.nombre_estado,
  (SELECT GROUP_CONCAT(esp.nombre_especialidad SEPARATOR ', ')
     FROM medico_especialidad me
     JOIN especialidades esp ON esp.id_especialidad=me.id_especialidad
     WHERE me.id_medico = t.id_medico) AS especialidades
FROM turnos t
LEFT JOIN pacientes p ON p.id_paciente=t.id_paciente
LEFT JOIN usuario up ON up.id_usuario=p.id_usuario
LEFT JOIN medicos m ON m.id_medico=t.id_medico
LEFT JOIN usuario um ON um.id_usuario=m.id_usuario
LEFT JOIN estado e ON e.id_estado=t.id_estado
$where
ORDER BY t.fecha DESC, t.hora DESC
LIMIT $pp OFFSET $off";

$turnos = fetch_rows($conn, $sql_list, $params, $types);

// ===== Export CSV opcional =====
if (isset($_GET['export']) && $_GET['export']==='csv') {
  $fname = "turnos_{$desde}_a_{$hasta}.csv";
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename='.$fname);
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID','Fecha','Hora','Paciente','Médico','Especialidades','Estado']);
  foreach($turnos as $r){
    fputcsv($out, [$r['id_turno'],$r['fecha'],substr($r['hora'],0,5),$r['paciente'],$r['medico'],$r['especialidades'],$r['nombre_estado']]);
  }
  fclose($out); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Turnos - Administrador</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ===== Estilo consistente ===== */
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --brand:#1e88e5; --brand-dark:#1565c0;
  --ok:#22c55e; --warn:#f59e0b; --bad:#ef4444;
  --bgcard: rgba(255,255,255,.92);
}
body{font-family:Arial, sans-serif;background:url("https://i.pinimg.com/1200x/9b/e2/12/9be212df4fc8537ddc31c3f7fa147b42.jpg") no-repeat center center fixed;background-size:cover;color:#222;}
nav{background:#fff;padding:12px 28px;box-shadow:0 4px 10px rgba(0,0,0,.08);position:sticky;top:0;z-index:10}
.nav-inner{display:flex;align-items:center;justify-content:space-between}
.nav-links{display:flex;gap:20px;align-items:center}
nav a{color:var(--brand);text-decoration:none;font-weight:bold}
nav a:hover{text-decoration:underline}
.btn{border:none;border-radius:8px;background:var(--brand);color:#fff;padding:8px 14px;cursor:pointer;font-weight:bold;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
.btn:hover{background:var(--brand-dark)} .btn.gray{background:#6b7280}
.btn.red{background:#ef4444} .btn.green{background:#22c55e} .btn.orange{background:#f59e0b}
.container{padding:24px 18px;max-width:1100px;margin:0 auto}
h1{color:#f5f8fa;text-shadow:1px 1px 3px rgba(0,0,0,.5);margin-bottom:16px;font-size:1.8rem}
.card{background:var(--bgcard);backdrop-filter:blur(3px);border-radius:16px;padding:14px;box-shadow:0 8px 16px rgba(0,0,0,.12);margin-bottom:14px}
.filters{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}
@media(max-width:980px){.filters{grid-template-columns:1fr 1fr}}
label{font-size:.9rem;color:#333}
input,select{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:10px;background:#fff}
.actions{display:flex;gap:8px;justify-content:flex-end;margin-top:8px}
.table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;font-size:.95rem}
.table th,.table td{padding:8px 10px;border-bottom:1px solid #e8e8e8;text-align:left;vertical-align:middle}
.table thead th{background:#f8fafc;color:#111}
.badge{padding:4px 8px;border-radius:999px;font-size:.78rem;color:#fff;display:inline-block}
.badge.pendiente{background:var(--warn)} .badge.confirmado{background:var(--brand-dark)}
.badge.atendido{background:var(--ok)} .badge.cancelado{background:var(--bad)}
.alert{padding:12px 14px;border-radius:10px;margin:10px 0;font-weight:600}
.alert-green{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.alert-orange{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}
.alert-red{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.pager{display:flex;gap:6px;justify-content:center;margin-top:10px}
.pager a,.pager span{padding:6px 10px;border-radius:8px;border:1px solid #ddd;background:#fff;text-decoration:none;color:#333}
.pager .active{background:#1e88e5;color:#fff;border-color:#1e88e5}
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
    <a class="btn gray" href="principalAdmi.php" style="margin-bottom:10px"><i class="fa fa-house"></i> Inicio</a>
    <h1><i class="fa fa-list"></i> Turnos</h1>

    <!-- Alertas -->
    <?php if ($ok): 
      $msgs = [
        'confirmar'=>'Turno confirmado correctamente.',
        'cancelar'=>'Turno cancelado.',
        'atender'=>'Turno marcado como atendido.',
        'transicion_invalida'=>'Acción no permitida para el estado actual.',
        'no_encontrado'=>'Turno no encontrado.',
        'error'=>'Ocurrió un error.'
      ];
      $cls = in_array($ok, ['confirmar','atender']) ? 'alert-green' : ($ok==='cancelar'?'alert-orange':'alert-red');
      $msg = $msgs[$ok] ?? 'Operación realizada.';
    ?>
      <div class="alert <?= $cls ?>"><?= h($msg) ?></div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card">
      <form method="get">
        <div class="filters">
          <div>
            <label>Desde</label>
            <input type="date" name="desde" value="<?= h($desde) ?>">
          </div>
          <div>
            <label>Hasta</label>
            <input type="date" name="hasta" value="<?= h($hasta) ?>">
          </div>
          <div>
            <label>Médico</label>
            <select name="id_medico">
              <option value="">Todos</option>
              <?php foreach($medicos as $m): ?>
                <option value="<?= (int)$m['id_medico'] ?>" <?= ($id_medico!=='' && (int)$id_medico===(int)$m['id_medico'])?'selected':'' ?>>
                  <?= h($m['medico']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Estado</label>
            <select name="id_estado">
              <option value="">Todos</option>
              <?php foreach($estados as $e): ?>
                <option value="<?= (int)$e['id_estado'] ?>" <?= ($id_estado!=='' && (int)$id_estado===(int)$e['id_estado'])?'selected':'' ?>>
                  <?= h($e['nombre_estado']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Buscar paciente</label>
            <input type="text" name="buscar" placeholder="Nombre o apellido" value="<?= h($buscar) ?>">
          </div>
        </div>
        <div class="actions">
          <button class="btn" type="submit"><i class="fa fa-filter"></i> Aplicar</button>
          <a class="btn gray" href="turnosListado.php"><i class="fa fa-eraser"></i> Limpiar</a>
          <a class="btn" style="background:#10b981" href="?<?= h(http_build_query(array_merge($_GET,['export'=>'csv']))) ?>"><i class="fa fa-file-csv"></i> CSV</a>
        </div>
      </form>
    </div>

    <!-- Listado -->
    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th style="width:68px">Fecha</th>
            <th style="width:56px">Hora</th>
            <th>Paciente</th>
            <th>Médico</th>
            <th>Especialidades</th>
            <th style="width:120px">Estado</th>
            <th style="width:260px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($turnos)): ?>
            <tr><td colspan="7" style="color:#666">Sin resultados con el filtro actual.</td></tr>
          <?php else: foreach($turnos as $r): 
            $estado = strtolower($r['nombre_estado'] ?? '');
          ?>
            <tr>
              <td><?= h($r['fecha']) ?></td>
              <td><?= h(substr($r['hora']??'',0,5)) ?></td>
              <td><?= h($r['paciente'] ?? '-') ?></td>
              <td><?= h($r['medico'] ?? '-') ?></td>
              <td><?= h($r['especialidades'] ?? '-') ?></td>
              <td><span class="badge <?= $estado ?>"><?= h($r['nombre_estado'] ?? '-') ?></span></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <!-- Confirmar -->
                <form method="post" onsubmit="return confirm('¿Confirmar turno?')">
                  <input type="hidden" name="id_turno" value="<?= (int)$r['id_turno'] ?>">
                  <input type="hidden" name="accion" value="confirmar">
                  <button class="btn green" type="submit"><i class="fa fa-circle-check"></i> Confirmar</button>
                </form>
                <!-- Atender -->
                <form method="post" onsubmit="return confirm('¿Marcar como atendido?')">
                  <input type="hidden" name="id_turno" value="<?= (int)$r['id_turno'] ?>">
                  <input type="hidden" name="accion" value="atender">
                  <button class="btn" type="submit"><i class="fa fa-user-nurse"></i> Atender</button>
                </form>
                <!-- Cancelar -->
                <form method="post" onsubmit="return confirm('¿Cancelar turno?')">
                  <input type="hidden" name="id_turno" value="<?= (int)$r['id_turno'] ?>">
                  <input type="hidden" name="accion" value="cancelar">
                  <button class="btn red" type="submit"><i class="fa fa-ban"></i> Cancelar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <!-- Paginador -->
      <?php if ($total_pages > 1): ?>
        <div class="pager">
          <?php for($i=1;$i<=$total_pages;$i++):
            $q = $_GET; $q['pag']=$i; $url = 'turnosListado.php?'.http_build_query($q);
          ?>
            <?php if ($i===$pag): ?>
              <span class="active"><?= $i ?></span>
            <?php else: ?>
              <a href="<?= h($url) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      <?php endif; ?>

    </div>

    <div style="text-align:center;color:#eee;font-size:.85rem;margin-top:6px">Clínica AP · Turnos</div>
  </main>
</body>
</html>
