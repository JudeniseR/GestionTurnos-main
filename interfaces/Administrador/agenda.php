<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 3; // Admin
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$nombreAdmin = $_SESSION['nombre'] ?? 'Admin';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// ===== Helpers =====
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function back_to($qs){ header('Location: agenda.php?'.$qs); exit; }
function qget($k,$d=null){ return isset($_GET[$k])?$_GET[$k]:$d; }
function table_exists(mysqli $c, string $t) {
  try { $r = $c->query("SHOW TABLES LIKE '".$c->real_escape_string($t)."'"); return ($r && $r->num_rows>0); } catch(Throwable $e){ return false; }
}

// ===== Tabs / Acciones =====
$tab    = qget('tab','turnos');          // turnos | feriados | excepciones
$action = qget('action','list');         // list | new | edit
$id     = (int)qget('id',0);

// ===== Flash =====
$status = qget('status'); // created | updated | deleted | error
$msg    = qget('msg');
$flashText = [
  'created'=>'Guardado correctamente.',
  'updated'=>'Actualizado correctamente.',
  'deleted'=>'Eliminado correctamente.',
  'error'  => ($msg ?: 'Ocurrió un error. Intentalo nuevamente.')
][$status] ?? null;
$flashKind = [
  'created'=>'success','updated'=>'success','deleted'=>'warning','error'=>'danger'
][$status] ?? 'success';

// ===== Catálogos básicos =====
/** Estados de turnos **/
$ESTADOS = []; // nombre_estado => id_estado
$res = $conn->query("SELECT id_estado, nombre_estado FROM estado");
if ($res) { while($row=$res->fetch_assoc()){ $ESTADOS[strtolower($row['nombre_estado'])]=(int)$row['id_estado']; } $res->close(); }

/** Select médicos **/
$MEDICOS = []; // id_medico => "Apellido, Nombre"
$res = $conn->query("
  SELECT m.id_medico, u.apellido, u.nombre
  FROM medicos m
  JOIN usuario u ON u.id_usuario=m.id_usuario
  ORDER BY u.apellido, u.nombre
");
if ($res) { while($row=$res->fetch_assoc()){ $MEDICOS[(int)$row['id_medico']] = $row['apellido'].', '.$row['nombre']; } $res->close(); }

/** Select pacientes **/
$PACIENTES = []; // id_paciente => "Apellido, Nombre (doc)"
$res = $conn->query("
  SELECT p.id_paciente, u.apellido, u.nombre, p.nro_documento
  FROM pacientes p
  JOIN usuario u ON u.id_usuario=p.id_usuario
  ORDER BY u.apellido, u.nombre
");
if ($res) { while($row=$res->fetch_assoc()){
  $d = trim($row['nro_documento'] ?? '');
  $PACIENTES[(int)$row['id_paciente']] = $row['apellido'].', '.$row['nombre'].($d!==''?' ('.$d.')':'');
} $res->close(); }

// ===== Detectar tablas de Feriados/Excepciones =====
$HAS_FERIADOS = table_exists($conn,'feriados');

// Excepciones: preferimos escribir en agenda_bloqueos si existe
$EXC_READ_TABLE  = null;  // de dónde LEEMOS
$EXC_WRITE_TABLE = null;  // dónde ESCRIBIMOS

if (table_exists($conn,'agenda_bloqueos')) {
  $EXC_WRITE_TABLE = 'agenda_bloqueos';
  $EXC_READ_TABLE  = 'agenda_bloqueos';
}
if (table_exists($conn,'excepciones')) {
  if ($EXC_READ_TABLE === null) $EXC_READ_TABLE = 'excepciones';
}
$HAS_EXCEPCIONES = ($EXC_READ_TABLE !== null);

// ======= POST Handlers =======
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $form = $_POST['form_action'] ?? '';

  // ---- ABM TURNOS ----
  if ($tab==='turnos') {
    if ($form==='turno_create' || $form==='turno_update') {
      $id_turno   = isset($_POST['id_turno']) ? (int)$_POST['id_turno'] : 0;
      $id_paciente= isset($_POST['id_paciente']) ? (int)$_POST['id_paciente'] : 0;
      $id_medico  = isset($_POST['id_medico']) ? (int)$_POST['id_medico'] : 0;
      $fecha      = trim($_POST['fecha'] ?? '');
      $hora       = trim($_POST['hora'] ?? '');
      $id_estado  = isset($_POST['id_estado']) ? (int)$_POST['id_estado'] : ($ESTADOS['pendiente'] ?? 0);
      $obs        = trim($_POST['observaciones'] ?? '');

      if (!$id_paciente || !$id_medico || $fecha==='' || $hora==='') {
        back_to('tab=turnos&status=error&msg='.rawurlencode('Completá paciente, médico, fecha y hora'));
      }

      // No superposición mismo médico misma fecha/hora (excepto cancelado)
      $sql_chk = "
        SELECT 1
        FROM turnos t
        WHERE t.id_medico=? AND t.fecha=? AND t.hora=? 
          AND t.id_estado<>?
          ".($form==='turno_update'?" AND t.id_turno<>?":"")."
        LIMIT 1
      ";
      $stmt = $conn->prepare($sql_chk);
      $id_cancelado = (int)($ESTADOS['cancelado'] ?? 0);
      if ($form==='turno_update') {
        $stmt->bind_param('issii',$id_medico,$fecha,$hora,$id_cancelado,$id_turno);
      } else {
        $stmt->bind_param('issi',$id_medico,$fecha,$hora,$id_cancelado);
      }
      $stmt->execute();
      $dup = $stmt->get_result()->num_rows>0;
      $stmt->close();
      if ($dup) {
        back_to('tab=turnos&status=error&msg='.rawurlencode('Ya existe un turno para ese médico en el mismo horario.'));
      }

      if ($form==='turno_create') {
        $stmt=$conn->prepare("INSERT INTO turnos (id_paciente,id_medico,fecha,hora,id_estado,observaciones) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('iissis',$id_paciente,$id_medico,$fecha,$hora,$id_estado,$obs);
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=turnos&status='.($ok?'created':'error'));
      } else {
        $stmt=$conn->prepare("UPDATE turnos SET id_paciente=?, id_medico=?, fecha=?, hora=?, id_estado=?, observaciones=? WHERE id_turno=?");
        $stmt->bind_param('iissisi',$id_paciente,$id_medico,$fecha,$hora,$id_estado,$obs,$id_turno);
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=turnos&status='.($ok?'updated':'error'));
      }
    }

    if ($form==='turno_delete') {
      $id_turno = isset($_POST['id_turno']) ? (int)$_POST['id_turno'] : 0;
      if (!$id_turno) back_to('tab=turnos&status=error');
      $stmt=$conn->prepare("DELETE FROM turnos WHERE id_turno=?");
      $stmt->bind_param('i',$id_turno);
      $ok=$stmt->execute(); $stmt->close();
      back_to('tab=turnos&status='.($ok?'deleted':'error'));
    }

    if ($form==='turno_estado') {
      $id_turno = isset($_POST['id_turno']) ? (int)$_POST['id_turno'] : 0;
      $id_estado= isset($_POST['id_estado']) ? (int)$_POST['id_estado'] : 0;
      if (!$id_turno || !$id_estado) back_to('tab=turnos&status=error');
      $stmt=$conn->prepare("UPDATE turnos SET id_estado=? WHERE id_turno=?");
      $stmt->bind_param('ii',$id_estado,$id_turno);
      $ok=$stmt->execute(); $stmt->close();
      back_to('tab=turnos&status='.($ok?'updated':'error'));
    }
  }

  // ---- ABM FERIADOS ----
  if ($tab==='feriados' && $HAS_FERIADOS) {
    if ($form==='feriado_create' || $form==='feriado_update') {
      $id_feriado = isset($_POST['id_feriado']) ? (int)$_POST['id_feriado'] : 0;
      $fecha      = trim($_POST['fecha'] ?? '');
      $motivo     = trim($_POST['motivo'] ?? '');
      if ($fecha==='') back_to('tab=feriados&status=error&msg=Fecha%20requerida');

      if ($form==='feriado_create') {
        $stmt=$conn->prepare("INSERT INTO feriados (fecha, motivo) VALUES (?,?)");
        $stmt->bind_param('ss',$fecha,$motivo);
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=feriados&status='.($ok?'created':'error'));
      } else {
        $stmt=$conn->prepare("UPDATE feriados SET fecha=?, motivo=? WHERE id_feriado=?");
        $stmt->bind_param('ssi',$fecha,$motivo,$id_feriado);
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=feriados&status='.($ok?'updated':'error'));
      }
    }

    if ($form==='feriado_delete') {
      $id_feriado = isset($_POST['id_feriado']) ? (int)$_POST['id_feriado'] : 0;
      if(!$id_feriado) back_to('tab=feriados&status=error');
      $stmt=$conn->prepare("DELETE FROM feriados WHERE id_feriado=?");
      $stmt->bind_param('i',$id_feriado);
      $ok=$stmt->execute(); $stmt->close();
      back_to('tab=feriados&status='.($ok?'deleted':'error'));
    }
  }

  // ---- ABM EXCEPCIONES (compatible con excepciones o agenda_bloqueos) ----
  if ($tab==='excepciones' && $HAS_EXCEPCIONES) {
    // Normalizar variables (evita pass-by-ref)
    $id_excepcion = isset($_POST['id_excepcion']) ? (int)$_POST['id_excepcion'] : 0;
    $id_medico    = isset($_POST['id_medico']) ? (int)$_POST['id_medico'] : 0; // 0 => global/NULL
    $fecha        = trim($_POST['fecha'] ?? '');
    $hora_desde   = trim($_POST['hora_desde'] ?? '');
    $hora_hasta   = trim($_POST['hora_hasta'] ?? '');
    $motivo       = trim($_POST['motivo'] ?? '');
    $formExc      = $form;

    // Si hay una sola hora en tu esquema, usamos la misma en ambos
    if ($hora_desde === '' && $hora_hasta !== '') $hora_desde = $hora_hasta;
    if ($hora_hasta === '' && $hora_desde !== '') $hora_hasta = $hora_desde;

    if ($formExc==='exc_create') {
      if ($fecha==='') back_to('tab=excepciones&status=error&msg=Fecha%20requerida');

      if ($EXC_WRITE_TABLE==='agenda_bloqueos') {
        // agenda_bloqueos: (id_bloqueo, id_medico, fecha, hora, tipo, motivo)
        $tipo = ($hora_desde!=='' ? 'slot' : 'dia');
        $hora = ($hora_desde!=='' ? $hora_desde : null);

        $sql = "INSERT INTO agenda_bloqueos (id_medico, fecha, hora, tipo, motivo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issss', $id_medico, $fecha, $hora, $tipo, $motivo);
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=excepciones&status='.($ok?'created':'error'));
      } else {
        // excepciones completa
        if ($id_medico > 0) {
          $sql = "INSERT INTO excepciones (id_medico, fecha, hora_desde, hora_hasta, motivo) VALUES (?, ?, ?, ?, ?)";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param('issss',$id_medico,$fecha,$hora_desde,$hora_hasta,$motivo);
        } else {
          $sql = "INSERT INTO excepciones (id_medico, fecha, hora_desde, hora_hasta, motivo) VALUES (NULL, ?, ?, ?, ?)";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param('ssss',$fecha,$hora_desde,$hora_hasta,$motivo);
        }
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=excepciones&status='.($ok?'created':'error'));
      }
    }

    if ($formExc==='exc_update') {
      if ($EXC_WRITE_TABLE==='agenda_bloqueos') {
        $id_bloqueo = $id_excepcion;
        $tipo = ($hora_desde!=='' ? 'slot' : 'dia');
        $hora = ($hora_desde!=='' ? $hora_desde : null);

        $sql = "UPDATE agenda_bloqueos SET id_medico=?, fecha=?, hora=?, tipo=?, motivo=? WHERE id_bloqueo=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issssi',$id_medico,$fecha,$hora,$tipo,$motivo,$id_bloqueo);
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=excepciones&status='.($ok?'updated':'error'));
      } else {
        if ($id_medico > 0) {
          $sql = "UPDATE excepciones SET id_medico=?, fecha=?, hora_desde=?, hora_hasta=?, motivo=? WHERE id_excepcion=?";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param('issssi',$id_medico,$fecha,$hora_desde,$hora_hasta,$motivo,$id_excepcion);
        } else {
          $sql = "UPDATE excepciones SET id_medico=NULL, fecha=?, hora_desde=?, hora_hasta=?, motivo=? WHERE id_excepcion=?";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param('ssssi',$fecha,$hora_desde,$hora_hasta,$motivo,$id_excepcion);
        }
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=excepciones&status='.($ok?'updated':'error'));
      }
    }

    if ($formExc==='exc_delete') {
      if ($EXC_WRITE_TABLE==='agenda_bloqueos') {
        $id_bloqueo = isset($_POST['id_excepcion']) ? (int)$_POST['id_excepcion'] : 0;
        if (!$id_bloqueo) back_to('tab=excepciones&status=error');
        $stmt=$conn->prepare("DELETE FROM agenda_bloqueos WHERE id_bloqueo=?");
        $stmt->bind_param('i',$id_bloqueo);
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=excepciones&status='.($ok?'deleted':'error'));
      } else {
        $id_excepcion = isset($_POST['id_excepcion']) ? (int)$_POST['id_excepcion'] : 0;
        if (!$id_excepcion) back_to('tab=excepciones&status=error');
        $stmt=$conn->prepare("DELETE FROM excepciones WHERE id_excepcion=?");
        $stmt->bind_param('i',$id_excepcion);
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=excepciones&status='.($ok?'deleted':'error'));
      }
    }
  }
}

// ===== CARGAS PARA VISTA =====

// ---- Turnos (listado + edición) ----
$turnos = [];
if ($tab==='turnos') {
  $f_medico = (int)qget('f_medico',0);
  $f_estado = (int)qget('f_estado',0);
  $f_desde  = qget('f_desde','');
  $f_hasta  = qget('f_hasta','');

  $sql = "
    SELECT t.id_turno, t.fecha, t.hora, t.id_estado, t.observaciones,
           e.nombre_estado,
           up.apellido AS ap_pac, up.nombre AS no_pac,
           um.apellido AS ap_med, um.nombre AS no_med,
           p.id_paciente, m.id_medico
    FROM turnos t
    LEFT JOIN estado e   ON e.id_estado=t.id_estado
    LEFT JOIN pacientes p ON p.id_paciente=t.id_paciente
    LEFT JOIN usuario up  ON up.id_usuario=p.id_usuario
    LEFT JOIN medicos m   ON m.id_medico=t.id_medico
    LEFT JOIN usuario um  ON um.id_usuario=m.id_usuario
    WHERE 1=1
  ";
  $w = []; $params = []; $types = '';

  if ($f_medico>0){ $w[]=" t.id_medico=? "; $types.='i'; $params[]=$f_medico; }
  if ($f_estado>0){ $w[]=" t.id_estado=? "; $types.='i'; $params[]=$f_estado; }
  if ($f_desde!==''){   $w[]=" t.fecha>=? "; $types.='s'; $params[]=$f_desde; }
  if ($f_hasta!==''){   $w[]=" t.fecha<=? "; $types.='s'; $params[]=$f_hasta; }

  if ($w){ $sql .= " AND ".implode(' AND ',$w); }
  $sql .= " ORDER BY t.fecha DESC, t.hora ASC LIMIT 500";

  $stmt=$conn->prepare($sql);
  if (!empty($params)){ $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $r = $stmt->get_result();
  while($r && $row=$r->fetch_assoc()){ $turnos[]=$row; }
  $stmt->close();

  // Carga turno para edición
  $turnoEdit = null;
  if ($action==='edit' && $id>0) {
    $stmt=$conn->prepare("
      SELECT id_turno,id_paciente,id_medico,fecha,hora,id_estado,observaciones
      FROM turnos WHERE id_turno=? LIMIT 1
    ");
    $stmt->bind_param('i',$id); $stmt->execute();
    $turnoEdit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$turnoEdit) $action='list';
  }
}

// ---- Feriados (si existe) ----
$feriados = [];
if ($tab==='feriados' && $HAS_FERIADOS) {
  $anio = (int)qget('anio', date('Y'));
  $stmt=$conn->prepare("SELECT id_feriado, fecha, motivo FROM feriados WHERE YEAR(fecha)=? ORDER BY fecha ASC");
  $stmt->bind_param('i',$anio); $stmt->execute();
  $r=$stmt->get_result(); while($r && $row=$r->fetch_assoc()){ $feriados[]=$row; }
  $stmt->close();

  $feriadoEdit=null;
  if ($action==='edit' && $id>0){
    $stmt=$conn->prepare("SELECT id_feriado, fecha, motivo FROM feriados WHERE id_feriado=?");
    $stmt->bind_param('i',$id); $stmt->execute();
    $feriadoEdit=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if(!$feriadoEdit) $action='list';
  }
}

// ---- Excepciones (según tabla disponible) ----
$excepciones = [];
if ($tab==='excepciones' && $HAS_EXCEPCIONES) {
  $f_medico_exc = (int)qget('f_medico_exc',0);
  $mes = (int)qget('mes', (int)date('n'));
  $anio= (int)qget('anio', (int)date('Y'));
  $desde = sprintf('%04d-%02d-01',$anio,$mes);
  $hasta = date('Y-m-t', strtotime($desde));

  if ($EXC_READ_TABLE==='agenda_bloqueos') {
    // agenda_bloqueos: (id_bloqueo, id_medico, fecha, hora, tipo, motivo)
    $sql="SELECT id_bloqueo AS id_excepcion, id_medico, fecha, hora AS hora_desde, hora AS hora_hasta, motivo
          FROM agenda_bloqueos
          WHERE fecha BETWEEN ? AND ?";
    $types='ss'; $params=[$desde,$hasta];
    if ($f_medico_exc>0){ $sql.=" AND id_medico=?"; $types.='i'; $params[]=$f_medico_exc; }
    $sql.=" ORDER BY fecha, hora";

    $stmt=$conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute(); $r=$stmt->get_result();
    while($r && $row=$r->fetch_assoc()){ $excepciones[]=$row; }
    $stmt->close();

    // edición
    $excEdit=null;
    if ($action==='edit' && $id>0){
      $stmt=$conn->prepare("SELECT id_bloqueo AS id_excepcion, id_medico, fecha, hora AS hora_desde, hora AS hora_hasta, motivo FROM agenda_bloqueos WHERE id_bloqueo=?");
      $stmt->bind_param('i',$id); $stmt->execute();
      $excEdit=$stmt->get_result()->fetch_assoc(); $stmt->close();
      if(!$excEdit) $action='list';
    }
  } else {
    // excepciones completa
    $sql="SELECT id_excepcion, id_medico, fecha, hora_desde, hora_hasta, motivo
          FROM excepciones
          WHERE fecha BETWEEN ? AND ?";
    $types='ss'; $params=[$desde,$hasta];
    if ($f_medico_exc>0){ $sql.=" AND (id_medico=? )"; $types.='i'; $params[]=$f_medico_exc; }
    $sql.=" ORDER BY fecha, hora_desde";

    $stmt=$conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute(); $r=$stmt->get_result();
    while($r && $row=$r->fetch_assoc()){ $excepciones[]=$row; }
    $stmt->close();

    $excEdit=null;
    if ($action==='edit' && $id>0){
      $stmt=$conn->prepare("SELECT id_excepcion, id_medico, fecha, hora_desde, hora_hasta, motivo FROM excepciones WHERE id_excepcion=?");
      $stmt->bind_param('i',$id); $stmt->execute();
      $excEdit=$stmt->get_result()->fetch_assoc(); $stmt->close();
      if(!$excEdit) $action='list';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Agenda (Turnos / Feriados / Excepciones)</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ===== Mismo diseño que principalAdmi ===== */
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
.container{padding:32px 18px;max-width:1200px;margin:0 auto}
h1{color:#f5f8fa;text-shadow:1px 1px 3px rgba(0,0,0,.5);margin-bottom:12px;font-size:2.1rem}
.card{background:var(--bgcard);backdrop-filter:blur(3px);border-radius:16px;padding:16px;box-shadow:0 8px 16px rgba(0,0,0,.12);margin-bottom:18px;border:1px solid rgba(0,0,0,.03)}
.table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
.table th,.table td{padding:10px;border-bottom:1px solid #e8e8e8;text-align:left}
.table thead th{background:#f8fafc;color:#111}
.badge{padding:4px 8px;border-radius:999px;font-size:.78rem;color:#fff;display:inline-block}
.badge.pendiente{background:var(--warn)}
.badge.confirmado{background:var(--brand-dark)}
.badge.atendido{background:var(--ok)}
.badge.cancelado{background:var(--bad)}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.form-grid .full{grid-column:1 / -1}
label{display:block;font-weight:700;margin-bottom:6px}
input[type="text"],input[type="email"],input[type="password"],input[type="date"],input[type="time"],select,textarea{width:100%;padding:10px;border:1px solid var(--border);border-radius:10px}
.form-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
.tabbar{display:flex;gap:8px;flex-wrap:wrap}
.notice{padding:10px;border-radius:10px;background:#fff8e1;border:1px solid #fde68a;color:#7c2d12}
/* barra volver */
.backbar{display:flex;gap:10px;margin:8px 0 16px}
.btn.gray{background:#6b7280}
</style>
</head>
<body>
<nav>
  <div class="nav-inner">
    <div class="nav-links">
      <a href="principalAdmi.php"><i class="fa fa-house"></i> Inicio</a>
    </div>
    <div class="nav-links">
      <span style="color:#333;font-weight:bold">Bienvenido, <?= esc($nombreAdmin) ?></span>
      <a class="btn" href="../../Logica/General/cerrarSesion.php"><i class="fa fa-right-from-bracket"></i> Cerrar sesión</a>
    </div>
  </div>
</nav>

<main class="container">
  <h1>Agenda</h1>

  <!-- Botón Volver al inicio -->
  <div class="backbar">
    <a class="btn gray" href="principalAdmi.php"><i class="fa fa-arrow-left"></i> Volver al inicio</a>
  </div>

  <!-- Tabs -->
  <div class="card tabbar">
    <a class="btn<?= $tab==='turnos'?'':' btn-outline' ?>" href="agenda.php?tab=turnos"><i class="fa fa-calendar-check"></i> Turnos</a>
    <?php if ($HAS_FERIADOS): ?>
      <a class="btn<?= $tab==='feriados'?'':' btn-outline' ?>" href="agenda.php?tab=feriados"><i class="fa fa-umbrella-beach"></i> Feriados</a>
    <?php endif; ?>
    <?php if ($HAS_EXCEPCIONES): ?>
      <a class="btn<?= $tab==='excepciones'?'':' btn-outline' ?>" href="agenda.php?tab=excepciones"><i class="fa fa-ban"></i> Excepciones</a>
    <?php else: ?>
      <span class="notice" style="margin-left:auto">
        <?php if(!$HAS_EXCEPCIONES): ?>
          La pestaña <b>Excepciones</b> se ocultó porque no existe la tabla <code>excepciones</code> ni <code>agenda_bloqueos</code>.
        <?php endif; ?>
      </span>
    <?php endif; ?>
  </div>

  <!-- Flash -->
  <?php if ($flashText): ?>
    <div class="card" style="padding:12px;border-left:4px solid <?= $flashKind==='danger'?'#ef4444':($flashKind==='warning'?'#f59e0b':'#22c55e') ?>">
      <strong><?= esc($flashText) ?></strong>
    </div>
  <?php endif; ?>

  <?php if ($tab==='turnos'): ?>
    <!-- ======= LISTADO / FILTROS TURNOS ======= -->
    <div class="card">
      <h2 style="margin-bottom:10px"><i class="fa fa-list"></i> Listado de turnos</h2>
      <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="tab" value="turnos"/>
        <div>
          <label>Médico</label>
          <select name="f_medico">
            <option value="0">Todos</option>
            <?php foreach($MEDICOS as $idm=>$nm): ?>
              <option value="<?= (int)$idm ?>" <?= ((int)qget('f_medico',0)===$idm?'selected':'') ?>><?= esc($nm) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Estado</label>
          <select name="f_estado">
            <option value="0">Todos</option>
            <?php foreach($ESTADOS as $name=>$id_estado): ?>
              <option value="<?= (int)$id_estado ?>" <?= ((int)qget('f_estado',0)===$id_estado?'selected':'') ?>><?= esc(ucfirst($name)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Desde</label>
          <input type="date" name="f_desde" value="<?= esc(qget('f_desde','')) ?>">
        </div>
        <div>
          <label>Hasta</label>
          <input type="date" name="f_hasta" value="<?= esc(qget('f_hasta','')) ?>">
        </div>
        <div>
          <button class="btn btn-sm" type="submit"><i class="fa fa-search"></i> Filtrar</button>
        </div>
      </form>

      <div style="margin-top:12px; overflow:auto">
        <table class="table">
          <thead>
            <tr>
              <th>Fecha</th><th>Hora</th><th>Paciente</th><th>Médico</th><th>Estado</th><th>Obs.</th><th style="width:240px">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if(empty($turnos)): ?>
            <tr><td colspan="7" style="color:#666">No hay turnos.</td></tr>
          <?php else: foreach($turnos as $t):
            $badge = strtolower($t['nombre_estado'] ?? '');
          ?>
            <tr>
              <td><?= esc($t['fecha']) ?></td>
              <td><?= esc(substr($t['hora'],0,5)) ?></td>
              <td><?= esc(($t['ap_pac']??'-').', '.($t['no_pac']??'')) ?></td>
              <td><?= esc(($t['ap_med']??'-').', '.($t['no_med']??'')) ?></td>
              <td><span class="badge <?= esc($badge) ?>"><?= esc(ucfirst($t['nombre_estado'] ?? '-')) ?></span></td>
              <td><?= esc($t['observaciones'] ?? '') ?></td>
              <td>
                <a class="btn-outline btn-sm" href="agenda.php?tab=turnos&action=edit&id=<?= (int)$t['id_turno'] ?>"><i class="fa fa-pen"></i> Editar</a>
                <!-- Cambio rápido de estado -->
                <form method="post" style="display:inline-flex;gap:6px;align-items:center">
                  <?php $currId = (int)$t['id_turno']; ?>
                  <input type="hidden" name="form_action" value="turno_estado">
                  <input type="hidden" name="id_turno" value="<?= $currId ?>">
                  <select name="id_estado">
                    <?php foreach($ESTADOS as $name=>$id_estado): ?>
                      <option value="<?= (int)$id_estado ?>" <?= ((int)$t['id_estado']===$id_estado?'selected':'') ?>><?= esc(ucfirst($name)) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm" type="submit"><i class="fa fa-rotate"></i></button>
                </form>
                <!-- Eliminar -->
                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar turno?')">
                  <input type="hidden" name="form_action" value="turno_delete">
                  <input type="hidden" name="id_turno" value="<?= (int)$t['id_turno'] ?>">
                  <button class="btn-danger btn-sm" type="submit"><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ======= ALTA / EDICIÓN TURNO ======= -->
    <div class="card">
      <h2 style="margin-bottom:10px">
        <i class="fa fa-plus-circle"></i> <?= ($action==='edit' && !empty($turnoEdit)) ? 'Editar turno' : 'Nuevo turno' ?>
      </h2>
      <form method="post" class="form-grid" autocomplete="off">
        <?php if ($action==='edit' && !empty($turnoEdit)): ?>
          <input type="hidden" name="form_action" value="turno_update">
          <input type="hidden" name="id_turno" value="<?= (int)$turnoEdit['id_turno'] ?>">
        <?php else: ?>
          <input type="hidden" name="form_action" value="turno_create">
        <?php endif; ?>

        <div>
          <label>Paciente</label>
          <select name="id_paciente" required>
            <option value="">Seleccionar…</option>
            <?php foreach($PACIENTES as $idp=>$np): ?>
              <option value="<?= (int)$idp ?>" <?= (!empty($turnoEdit) && (int)$turnoEdit['id_paciente']===$idp?'selected':'') ?>><?= esc($np) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Médico</label>
          <select name="id_medico" required>
            <option value="">Seleccionar…</option>
            <?php foreach($MEDICOS as $idm=>$nm): ?>
              <option value="<?= (int)$idm ?>" <?= (!empty($turnoEdit) && (int)$turnoEdit['id_medico']===$idm?'selected':'') ?>><?= esc($nm) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Fecha</label>
          <input type="date" name="fecha" value="<?= esc($turnoEdit['fecha'] ?? '') ?>" required>
        </div>
        <div>
          <label>Hora</label>
          <input type="time" name="hora" value="<?= esc(substr($turnoEdit['hora'] ?? '',0,5)) ?>" required>
        </div>

        <div>
          <label>Estado</label>
          <select name="id_estado">
            <?php foreach($ESTADOS as $name=>$id_estado): ?>
              <option value="<?= (int)$id_estado ?>" <?= (!empty($turnoEdit) && (int)$turnoEdit['id_estado']===$id_estado?'selected':'') ?>><?= esc(ucfirst($name)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="full">
          <label>Observaciones</label>
          <textarea name="observaciones" rows="2" placeholder="Motivo breve, indicaciones, etc."><?= esc($turnoEdit['observaciones'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
          <?php if ($action==='edit' && !empty($turnoEdit)): ?>
            <a class="btn-outline btn-sm" href="agenda.php?tab=turnos"><i class="fa fa-xmark"></i> Cancelar</a>
            <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar cambios</button>
          <?php else: ?>
            <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Crear turno</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($tab==='feriados' && $HAS_FERIADOS): ?>
    <!-- ======= FERIADOS ======= -->
    <div class="card">
      <h2 style="margin-bottom:10px"><i class="fa fa-umbrella-beach"></i> Feriados</h2>
      <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="tab" value="feriados"/>
        <label>Año</label>
        <input type="number" name="anio" value="<?= esc(qget('anio', date('Y'))) ?>" style="width:120px">
        <button class="btn btn-sm" type="submit"><i class="fa fa-search"></i> Ver</button>
        <a class="btn btn-sm" href="agenda.php?tab=feriados&action=new"><i class="fa fa-plus"></i> Nuevo feriado</a>
      </form>

      <div style="margin-top:12px;overflow:auto">
        <table class="table">
          <thead><tr><th>Fecha</th><th>Motivo</th><th style="width:200px">Acciones</th></tr></thead>
          <tbody>
          <?php if (empty($feriados)): ?>
            <tr><td colspan="3" style="color:#666">No hay feriados en el período.</td></tr>
          <?php else: foreach($feriados as $f): ?>
            <tr>
              <td><?= esc($f['fecha']) ?></td>
              <td><?= esc($f['motivo'] ?? '') ?></td>
              <td>
                <a class="btn-outline btn-sm" href="agenda.php?tab=feriados&action=edit&id=<?= (int)$f['id_feriado'] ?>"><i class="fa fa-pen"></i> Editar</a>
                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar feriado?')">
                  <input type="hidden" name="form_action" value="feriado_delete">
                  <input type="hidden" name="id_feriado" value="<?= (int)$f['id_feriado'] ?>">
                  <button class="btn-danger btn-sm" type="submit"><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($action==='new' || ($action==='edit' && !empty($feriadoEdit))): ?>
      <div class="card">
        <h3><?= $action==='new' ? 'Nuevo feriado' : 'Editar feriado' ?></h3>
        <form method="post" class="form-grid">
          <?php if ($action==='new'): ?>
            <input type="hidden" name="form_action" value="feriado_create">
          <?php else: ?>
            <input type="hidden" name="form_action" value="feriado_update">
            <input type="hidden" name="id_feriado" value="<?= (int)$feriadoEdit['id_feriado'] ?>">
          <?php endif; ?>
          <div>
            <label>Fecha</label>
            <input type="date" name="fecha" value="<?= esc($feriadoEdit['fecha'] ?? '') ?>" required>
          </div>
          <div class="full">
            <label>Motivo</label>
            <input type="text" name="motivo" value="<?= esc($feriadoEdit['motivo'] ?? '') ?>">
          </div>
          <div class="form-actions">
            <a class="btn-outline btn-sm" href="agenda.php?tab=feriados"><i class="fa fa-xmark"></i> Cancelar</a>
            <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar</button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($tab==='excepciones' && $HAS_EXCEPCIONES): ?>
    <!-- ======= EXCEPCIONES ======= -->
    <div class="card">
      <h2 style="margin-bottom:10px"><i class="fa fa-ban"></i> Excepciones</h2>
      <form method="get" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="tab" value="excepciones"/>
        <div>
          <label>Médico</label>
          <select name="f_medico_exc">
            <option value="0">Todos</option>
            <?php foreach($MEDICOS as $idm=>$nm): ?>
              <option value="<?= (int)$idm ?>" <?= ((int)qget('f_medico_exc',0)===$idm?'selected':'') ?>><?= esc($nm) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Mes</label>
          <input type="number" name="mes" min="1" max="12" value="<?= esc(qget('mes', (int)date('n'))) ?>" style="width:100px">
        </div>
        <div>
          <label>Año</label>
          <input type="number" name="anio" value="<?= esc(qget('anio', (int)date('Y'))) ?>" style="width:120px">
        </div>
        <div>
          <button class="btn btn-sm" type="submit"><i class="fa fa-search"></i> Ver</button>
          <a class="btn btn-sm" href="agenda.php?tab=excepciones&action=new"><i class="fa fa-plus"></i> Nueva excepción</a>
        </div>
      </form>

      <div style="margin-top:12px;overflow:auto">
        <table class="table">
          <thead><tr><th>Fecha</th><th>Médico</th><th>Desde</th><th>Hasta</th><th>Motivo</th><th style="width:200px">Acciones</th></tr></thead>
          <tbody>
            <?php if (!isset($excEdit)) $excEdit = null; ?>
            <?php if (empty($excepciones)): ?>
              <tr><td colspan="6" style="color:#666">No hay excepciones en el período.</td></tr>
            <?php else: foreach($excepciones as $x): ?>
              <tr>
                <td><?= esc($x['fecha']) ?></td>
                <td><?= ($x['id_medico'] ? esc($MEDICOS[(int)$x['id_medico']] ?? '-') : '<em>Global</em>') ?></td>
                <td><?= esc(substr($x['hora_desde']??'',0,5)) ?></td>
                <td><?= esc(substr($x['hora_hasta']??'',0,5)) ?></td>
                <td><?= esc($x['motivo'] ?? '') ?></td>
                <td>
                  <a class="btn-outline btn-sm" href="agenda.php?tab=excepciones&action=edit&id=<?= (int)$x['id_excepcion'] ?>"><i class="fa fa-pen"></i> Editar</a>
                  <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar excepción?')">
                    <input type="hidden" name="form_action" value="exc_delete">
                    <input type="hidden" name="id_excepcion" value="<?= (int)$x['id_excepcion'] ?>">
                    <button class="btn-danger btn-sm" type="submit"><i class="fa fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php
      // ¿tenemos single hour? (cuando leemos de agenda_bloqueos)
      $singleHour = ($EXC_READ_TABLE === 'agenda_bloqueos');
    ?>
    <?php if ($action==='new' || ($action==='edit' && !empty($excEdit))): ?>
      <div class="card">
        <h3><?= $action==='new' ? 'Nueva excepción' : 'Editar excepción' ?></h3>
        <form method="post" class="form-grid" autocomplete="off">
          <?php if ($action==='new'): ?>
            <input type="hidden" name="form_action" value="exc_create">
          <?php else: ?>
            <input type="hidden" name="form_action" value="exc_update">
            <input type="hidden" name="id_excepcion" value="<?= (int)$excEdit['id_excepcion'] ?>">
          <?php endif; ?>

          <div>
            <label>Médico (vacío = global)</label>
            <select name="id_medico">
              <option value="0">— Global —</option>
              <?php foreach($MEDICOS as $idm=>$nm): ?>
                <option value="<?= (int)$idm ?>" <?= (!empty($excEdit) && (int)($excEdit['id_medico'] ?? 0)===$idm?'selected':'') ?>><?= esc($nm) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Fecha</label>
            <input type="date" name="fecha" value="<?= esc($excEdit['fecha'] ?? '') ?>" required>
          </div>

          <?php if ($singleHour): ?>
            <div class="full">
              <label>Hora (opcional)</label>
              <input type="time" name="hora_desde" value="<?= esc(substr($excEdit['hora_desde'] ?? '',0,5)) ?>">
              <input type="hidden" name="hora_hasta" value="<?= esc(substr($excEdit['hora_desde'] ?? '',0,5)) ?>">
            </div>
          <?php else: ?>
            <div>
              <label>Hora desde (opcional)</label>
              <input type="time" name="hora_desde" value="<?= esc(substr($excEdit['hora_desde'] ?? '',0,5)) ?>">
            </div>
            <div>
              <label>Hora hasta (opcional)</label>
              <input type="time" name="hora_hasta" value="<?= esc(substr($excEdit['hora_hasta'] ?? '',0,5)) ?>">
            </div>
          <?php endif; ?>

          <div class="full">
            <label>Motivo</label>
            <input type="text" name="motivo" value="<?= esc($excEdit['motivo'] ?? '') ?>">
          </div>
          <div class="form-actions">
            <a class="btn-outline btn-sm" href="agenda.php?tab=excepciones"><i class="fa fa-xmark"></i> Cancelar</a>
            <button class="btn btn-sm" type="submit"><i class="fa fa-floppy-disk"></i> Guardar</button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</main>
</body>
</html>
