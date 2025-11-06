<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 3; // Admin
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$nombreAdmin = $_SESSION['nombre'] ?? 'Admin';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
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
$tab    = qget('tab','turnos');
$action = qget('action','list');
$id     = (int)qget('id',0);

// ===== Flash =====
$status = qget('status');
$msg    = qget('msg');
$flashText = [
  'created'=>'Turno confirmado correctamente.',
  'updated'=>'Actualizado correctamente.',
  'deleted'=>'Eliminado correctamente.',
  'error'  => ($msg ?: 'Ocurrió un error. Intentalo nuevamente.')
][$status] ?? null;
$flashKind = [
  'created'=>'success','updated'=>'success','deleted'=>'warning','error'=>'danger'
][$status] ?? 'success';

// ===== Catálogos básicos =====
$ESTADOS = [];
$res = $conn->query("SELECT id_estado, nombre_estado FROM estados");
if ($res) { while($row=$res->fetch_assoc()){ $ESTADOS[strtolower($row['nombre_estado'])]=(int)$row['id_estado']; } $res->close(); }

// Médicos activos con sus especialidades
$MEDICOS = [];
$res = $conn->query("
  SELECT m.id_medico, u.apellido, u.nombre,
         GROUP_CONCAT(DISTINCT e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ', ') as especialidades
  FROM medicos m
  JOIN usuarios u ON u.id_usuario = m.id_usuario
  LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
  LEFT JOIN especialidades e ON e.id_especialidad = me.id_especialidad
  WHERE u.activo = 1
  GROUP BY m.id_medico, u.apellido, u.nombre
  ORDER BY u.apellido, u.nombre
");
if ($res) { 
  while($row=$res->fetch_assoc()){ 
    $especialidades = $row['especialidades'] ? ' - ' . $row['especialidades'] : ' - Sin especialidad';
    $MEDICOS[(int)$row['id_medico']] = $row['apellido'].', '.$row['nombre'] . $especialidades;
  } 
  $res->close(); 
}

// Pacientes activos
$PACIENTES = [];
$res = $conn->query("
  SELECT p.id_paciente, u.apellido, u.nombre, p.nro_documento
  FROM pacientes p
  JOIN usuarios u ON u.id_usuario = p.id_usuario
  WHERE u.activo = 1
  ORDER BY u.apellido, u.nombre
");
if ($res) { while($row=$res->fetch_assoc()){
  $d = trim($row['nro_documento'] ?? '');
  $PACIENTES[(int)$row['id_paciente']] = $row['apellido'].', '.$row['nombre'].($d!==''?' ('.$d.')':'');
} $res->close(); }

// ===== Detectar tablas =====
$HAS_FERIADOS = table_exists($conn,'feriados');
$EXC_READ_TABLE  = null;
$EXC_WRITE_TABLE = null;
if (table_exists($conn,'agenda_bloqueos')) {
  $EXC_WRITE_TABLE = 'agenda_bloqueos';
  $EXC_READ_TABLE  = 'agenda_bloqueos';
}
if (table_exists($conn,'excepciones')) {
  if ($EXC_READ_TABLE === null) $EXC_READ_TABLE = 'excepciones';
}
$HAS_EXCEPCIONES = ($EXC_READ_TABLE !== null);

// ===== AJAX: Obtener horarios disponibles =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_slots') {
  header('Content-Type: application/json');
  
  $id_medico = (int)($_GET['id_medico'] ?? 0);
  $fecha = trim($_GET['fecha'] ?? '');
  
  if (!$id_medico || !$fecha) {
    echo json_encode(['error' => 'Parámetros incompletos']);
    exit;
  }
  
  // 1. Obtener slots de la agenda del médico para esa fecha
  $stmt = $conn->prepare("
    SELECT hora_inicio, hora_fin, id_recurso
    FROM agenda
    WHERE id_medico = ? 
      AND fecha = ?
      AND disponible = 1
    ORDER BY hora_inicio
  ");
  $stmt->bind_param('is', $id_medico, $fecha);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $slots_disponibles = [];
  while ($row = $result->fetch_assoc()) {
    $hora_inicio = substr($row['hora_inicio'], 0, 5);
    $hora_fin = substr($row['hora_fin'], 0, 5);
    $id_recurso = $row['id_recurso'];
    
    // Generar slots de 30 minutos (ajustable)
    $current = strtotime($hora_inicio);
    $end = strtotime($hora_fin);
    
    while ($current < $end) {
      $slot_hora = date('H:i', $current);
      
      // Verificar si el slot NO está ocupado por un turno
      $stmt_turno = $conn->prepare("
        SELECT 1 FROM turnos 
        WHERE id_medico = ? 
          AND fecha = ? 
          AND hora = ?
          AND id_estado != ?
        LIMIT 1
      ");
      $id_cancelado = $ESTADOS['cancelado'] ?? 0;
      $stmt_turno->bind_param('issi', $id_medico, $fecha, $slot_hora, $id_cancelado);
      $stmt_turno->execute();
      $ocupado = $stmt_turno->get_result()->num_rows > 0;
      $stmt_turno->close();
      
      // Verificar si el slot NO está bloqueado
      $stmt_bloq = $conn->prepare("
        SELECT 1 FROM agenda_bloqueos
        WHERE id_medico = ?
          AND fecha = ?
          AND (tipo = 'dia' OR (tipo = 'slot' AND hora = ?))
          AND activo = 1
        LIMIT 1
      ");
      $stmt_bloq->bind_param('iss', $id_medico, $fecha, $slot_hora);
      $stmt_bloq->execute();
      $bloqueado = $stmt_bloq->get_result()->num_rows > 0;
      $stmt_bloq->close();
      
      if (!$ocupado && !$bloqueado) {
        $slots_disponibles[] = $slot_hora;
      }
      
      $current = strtotime('+30 minutes', $current);
    }
  }
  $stmt->close();
  
  echo json_encode(['slots' => $slots_disponibles]);
  exit;
}

// ===== AJAX: Obtener fechas disponibles para calendario =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_dates') {
  header('Content-Type: application/json');
  
  $id_medico = (int)($_GET['id_medico'] ?? 0);
  $mes = (int)($_GET['mes'] ?? date('n'));
  $anio = (int)($_GET['anio'] ?? date('Y'));
  
  if (!$id_medico) {
    echo json_encode(['error' => 'Médico no seleccionado']);
    exit;
  }
  
  // Obtener fechas donde el médico tiene agenda disponible
  $desde = sprintf('%04d-%02d-01', $anio, $mes);
  $hasta = date('Y-m-t', strtotime($desde));
  
  $stmt = $conn->prepare("
    SELECT DISTINCT fecha
    FROM agenda
    WHERE id_medico = ?
      AND fecha BETWEEN ? AND ?
      AND disponible = 1
    ORDER BY fecha
  ");
  $stmt->bind_param('iss', $id_medico, $desde, $hasta);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $fechas_con_agenda = [];
  while ($row = $result->fetch_assoc()) {
    $fecha = $row['fecha'];
    
    // Verificar que no sea feriado
    if ($HAS_FERIADOS) {
      $stmt_fer = $conn->prepare("SELECT 1 FROM feriados WHERE fecha = ? LIMIT 1");
      $stmt_fer->bind_param('s', $fecha);
      $stmt_fer->execute();
      $es_feriado = $stmt_fer->get_result()->num_rows > 0;
      $stmt_fer->close();
      
      if ($es_feriado) continue;
    }
    
    // Verificar que no esté bloqueado todo el día
    $stmt_bloq = $conn->prepare("
      SELECT 1 FROM agenda_bloqueos
      WHERE id_medico = ? AND fecha = ? AND tipo = 'dia' AND activo = 1
      LIMIT 1
    ");
    $stmt_bloq->bind_param('is', $id_medico, $fecha);
    $stmt_bloq->execute();
    $dia_bloqueado = $stmt_bloq->get_result()->num_rows > 0;
    $stmt_bloq->close();
    
    if (!$dia_bloqueado) {
      $fechas_con_agenda[] = $fecha;
    }
  }
  $stmt->close();
  
  echo json_encode(['fechas' => $fechas_con_agenda]);
  exit;
}

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
    $obs        = trim($_POST['observaciones'] ?? '');
    
    // Estado automático: confirmado para crear, editable para update
    if ($form === 'turno_create') {
      $id_estado = $ESTADOS['confirmado'] ?? $ESTADOS['pendiente'] ?? 0;
    } else {
      $id_estado = isset($_POST['id_estado']) ? (int)$_POST['id_estado'] : ($ESTADOS['confirmado'] ?? 0);
    }

    if (!$id_paciente || !$id_medico || $fecha==='' || $hora==='') {
      back_to('tab=turnos&status=error&msg='.rawurlencode('Completá paciente, médico, fecha y hora'));
    }

    // Validar que el slot esté disponible en la agenda y obtener id_recurso
    $stmt_agenda = $conn->prepare("
      SELECT id_recurso FROM agenda
      WHERE id_medico = ?
        AND fecha = ?
        AND ? BETWEEN hora_inicio AND hora_fin
        AND disponible = 1
      LIMIT 1
    ");
    $stmt_agenda->bind_param('iss', $id_medico, $fecha, $hora);
    $stmt_agenda->execute();
    $result_agenda = $stmt_agenda->get_result();
    $agenda_row = $result_agenda->fetch_assoc();
    $stmt_agenda->close();
    
    if (!$agenda_row) {
      back_to('tab=turnos&status=error&msg='.rawurlencode('El horario seleccionado no está disponible en la agenda del médico'));
    }
    $id_recurso = (int)$agenda_row['id_recurso'];

    // No superposición
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
      $stmt=$conn->prepare("INSERT INTO turnos (id_paciente,id_medico,fecha,hora,id_estado,id_estudio,id_recurso,copago,observaciones,reprogramado) VALUES (?,?,?,?,?,?,?,?,?,?)");
      $id_estudio = null; // NULL
      $copago = 0.00;
      $reprogramado = 0;
      $stmt->bind_param('iissiiidis', $id_paciente, $id_medico, $fecha, $hora, $id_estado, $id_estudio, $id_recurso, $copago, $obs, $reprogramado);
      $ok=$stmt->execute(); $stmt->close();
      back_to('tab=turnos&status='.($ok?'created':'error'));
    } else {
      $stmt=$conn->prepare("UPDATE turnos SET id_paciente=?, id_medico=?, fecha=?, hora=?, id_estado=?, id_estudio=?, id_recurso=?, copago=?, observaciones=?, reprogramado=? WHERE id_turno=?");
      $id_estudio = null; // NULL
      $copago = 0.00;
      $reprogramado = 0;
      $stmt->bind_param('iissiiidisi', $id_paciente, $id_medico, $fecha, $hora, $id_estado, $id_estudio, $id_recurso, $copago, $obs, $reprogramado, $id_turno);
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

  // ---- ABM EXCEPCIONES ----
  if ($tab==='excepciones' && $HAS_EXCEPCIONES) {
    $id_excepcion = isset($_POST['id_excepcion']) ? (int)$_POST['id_excepcion'] : 0;
    $id_medico    = isset($_POST['id_medico']) ? (int)$_POST['id_medico'] : 0;
    $fecha        = trim($_POST['fecha'] ?? '');
    $hora_desde   = trim($_POST['hora_desde'] ?? '');
    $hora_hasta   = trim($_POST['hora_hasta'] ?? '');
    $motivo       = trim($_POST['motivo'] ?? '');
    $formExc      = $form;

    if ($hora_desde === '' && $hora_hasta !== '') $hora_desde = $hora_hasta;
    if ($hora_hasta === '' && $hora_desde !== '') $hora_hasta = $hora_desde;

    if ($formExc==='exc_create') {
      if ($fecha==='') back_to('tab=excepciones&status=error&msg=Fecha%20requerida');

      if ($EXC_WRITE_TABLE==='agenda_bloqueos') {
        $tipo = ($hora_desde!=='' ? 'slot' : 'dia');
        $hora = ($hora_desde!=='' ? $hora_desde : null);
        $sql = "INSERT INTO agenda_bloqueos (id_medico, fecha, hora, tipo, motivo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issss', $id_medico, $fecha, $hora, $tipo, $motivo);
        $ok=$stmt->execute(); $stmt->close();
        back_to('tab=excepciones&status='.($ok?'created':'error'));
      } else {
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
$turnos = [];
$turnoEdit = null;
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
    LEFT JOIN estados   e ON e.id_estado=t.id_estado
    LEFT JOIN pacientes p ON p.id_paciente=t.id_paciente
    LEFT JOIN usuarios  up ON up.id_usuario=p.id_usuario
    LEFT JOIN medicos   m ON m.id_medico=t.id_medico
    LEFT JOIN usuarios  um ON um.id_usuario=m.id_usuario
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

// ---- Feriados ----
$feriados = [];
$feriadoEdit=null;
if ($tab==='feriados' && $HAS_FERIADOS) {
  $anio = (int)qget('anio', date('Y'));
  $stmt=$conn->prepare("SELECT id_feriado, fecha, motivo FROM feriados WHERE YEAR(fecha)=? ORDER BY fecha ASC");
  $stmt->bind_param('i',$anio); $stmt->execute();
  $r=$stmt->get_result(); while($r && $row=$r->fetch_assoc()){ $feriados[]=$row; }
  $stmt->close();

  if ($action==='edit' && $id>0){
    $stmt=$conn->prepare("SELECT id_feriado, fecha, motivo FROM feriados WHERE id_feriado=?");
    $stmt->bind_param('i',$id); $stmt->execute();
    $feriadoEdit=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if(!$feriadoEdit) $action='list';
  }
}

// ---- Excepciones ----
$excepciones = [];
$excEdit=null;
if ($tab==='excepciones' && $HAS_EXCEPCIONES) {
  $f_medico_exc = (int)qget('f_medico_exc',0);
  $mes = (int)qget('mes', (int)date('n'));
  $anio= (int)qget('anio', (int)date('Y'));
  $desde = sprintf('%04d-%02d-01',$anio,$mes);
  $hasta = date('Y-m-t', strtotime($desde));

  if ($EXC_READ_TABLE==='agenda_bloqueos') {
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

    if ($action==='edit' && $id>0){
      $stmt=$conn->prepare("SELECT id_bloqueo AS id_excepcion, id_medico, fecha, hora AS hora_desde, hora AS hora_hasta, motivo FROM agenda_bloqueos WHERE id_bloqueo=?");
      $stmt->bind_param('i',$id); $stmt->execute();
      $excEdit=$stmt->get_result()->fetch_assoc(); $stmt->close();
      if(!$excEdit) $action='list';
    }
  } else {
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
<title>Agenda (Turnos / Feriados / Excepciones) | Gestión de turnos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
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
select{background:#fff;cursor:pointer}
select option{padding:8px}
.form-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
.tabbar{display:flex;gap:8px;flex-wrap:wrap}
.notice{padding:10px;border-radius:10px;background:#fff8e1;border:1px solid #fde68a;color:#7c2d12}
.backbar{display:flex;gap:10px;margin:8px 0 16px}
.btn.gray{background:#6b7280}

/* Calendario y slots */
.calendar-container{display:none;margin-top:16px;padding:16px;background:#f9fafb;border-radius:12px;border:1px solid var(--border)}
.calendar-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.calendar-header h3{font-size:1.1rem}
.calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:16px}
.calendar-day{padding:12px;text-align:center;border-radius:8px;background:#fff;border:2px solid transparent;cursor:pointer;font-size:.9rem}
.calendar-day.available{border-color:var(--brand);color:var(--brand);font-weight:bold}
.calendar-day.available:hover{background:var(--brand);color:#fff}
.calendar-day.selected{background:var(--brand);color:#fff}
.calendar-day.disabled{color:#ccc;cursor:not-allowed}
.slots-container{display:none;margin-top:16px}
.slots-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:8px}
.slot-btn{padding:10px;text-align:center;border-radius:8px;background:#fff;border:2px solid var(--brand);color:var(--brand);cursor:pointer;font-weight:bold;font-size:.9rem}
.slot-btn:hover{background:var(--brand);color:#fff}
.slot-btn.selected{background:var(--brand);color:#fff}
.loading{text-align:center;padding:20px;color:#666}
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

  <div class="backbar">
    <a class="btn gray" href="principalAdmi.php"><i class="fa fa-arrow-left"></i> Volver al inicio</a>
  </div>

  <div class="card tabbar">
    <a class="btn<?= $tab==='turnos'?'':' btn-outline' ?>" href="agenda.php?tab=turnos"><i class="fa fa-calendar-check"></i> Turnos</a>
    <?php if ($HAS_FERIADOS): ?>
      <a class="btn<?= $tab==='feriados'?'':' btn-outline' ?>" href="agenda.php?tab=feriados"><i class="fa fa-umbrella-beach"></i> Feriados</a>
    <?php endif; ?>
    <?php if ($HAS_EXCEPCIONES): ?>
      <a class="btn<?= $tab==='excepciones'?'':' btn-outline' ?>" href="agenda.php?tab=excepciones"><i class="fa fa-ban"></i> Excepciones</a>
    <?php endif; ?>
  </div>

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
              <th>Fecha</th><th>Hora</th><th>Paciente</th><th>Médico</th><th>Estado</th><th>Obs.</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if(empty($turnos)): ?>
            <tr><td colspan="7" style="color:#666">No hay turnos.</td></tr>
          <?php else: foreach($turnos as $t): $badge=strtolower($t['nombre_estado']??''); ?>
            <tr>
              <td><?= esc($t['fecha']) ?></td>
              <td><?= esc(substr($t['hora'],0,5)) ?></td>
              <td><?= esc(($t['ap_pac']??'-').', '.($t['no_pac']??'')) ?></td>
              <td><?= esc(($t['ap_med']??'-').', '.($t['no_med']??'')) ?></td>
              <td><span class="badge <?= esc($badge) ?>"><?= esc(ucfirst($t['nombre_estado']??'-')) ?></span></td>
              <td><?= esc($t['observaciones']??'') ?></td>
              <td>
                <a class="btn-outline btn-sm" href="agenda.php?tab=turnos&action=edit&id=<?= (int)$t['id_turno'] ?>">
                  <i class="fa fa-pen"></i> Editar
                </a>
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
      <form method="post" id="formTurno" class="form-grid" autocomplete="off">
        <?php if ($action==='edit' && !empty($turnoEdit)): ?>
          <input type="hidden" name="form_action" value="turno_update">
          <input type="hidden" name="id_turno" value="<?= (int)$turnoEdit['id_turno'] ?>">
        <?php else: ?>
          <input type="hidden" name="form_action" value="turno_create">
        <?php endif; ?>

        <div>
          <label>Paciente *</label>
          <select name="id_paciente" required>
            <option value="">Seleccionar…</option>
            <?php foreach($PACIENTES as $idp=>$np): ?>
              <option value="<?= (int)$idp ?>" <?= (!empty($turnoEdit) && (int)$turnoEdit['id_paciente']===$idp?'selected':'') ?>><?= esc($np) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Médico *</label>
          <select name="id_medico" id="selectMedico" required <?= ($action==='edit' ? 'disabled' : '') ?> style="font-size:0.9rem">
            <option value="">Seleccionar médico...</option>
            <?php foreach($MEDICOS as $idm=>$nm): ?>
              <option value="<?= (int)$idm ?>" <?= (!empty($turnoEdit) && (int)$turnoEdit['id_medico']===$idm?'selected':'') ?>>
                <?= esc($nm) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($action==='edit'): ?>
            <input type="hidden" name="id_medico" value="<?= (int)$turnoEdit['id_medico'] ?>">
          <?php endif; ?>
        </div>

        <?php if ($action==='edit'): ?>
  <!-- Modo edición: usar calendario igual que creación -->
  <input type="hidden" name="fecha" id="inputFecha" value="<?= esc($turnoEdit['fecha'] ?? '') ?>" required>
  <input type="hidden" name="hora" id="inputHora" value="<?= esc(substr($turnoEdit['hora'] ?? '',0,5)) ?>" required>
  
  <!-- Calendario de disponibilidad -->
  <div class="full">
    <div id="calendarContainer" class="calendar-container" style="display:block">
      <div class="calendar-header">
        <button type="button" class="btn-outline btn-sm" id="btnPrevMonth">
          <i class="fa fa-chevron-left"></i>
        </button>
        <h3 id="calendarTitle">Seleccioná una fecha</h3>
        <button type="button" class="btn-outline btn-sm" id="btnNextMonth">
          <i class="fa fa-chevron-right"></i>
        </button>
      </div>
      <div id="calendarGrid" class="calendar-grid"></div>
      <div id="loadingCalendar" class="loading" style="display:none">
        <i class="fa fa-spinner fa-spin"></i> Cargando disponibilidad...
      </div>
    </div>

    <!-- Slots disponibles -->
    <div id="slotsContainer" class="slots-container" style="display:block">
      <h3 style="margin-bottom:12px">Horarios disponibles</h3>
      <div id="slotsGrid" class="slots-grid"></div>
      <div id="loadingSlots" class="loading" style="display:none">
        <i class="fa fa-spinner fa-spin"></i> Cargando horarios...
      </div>
    </div>
  </div>

  <div>
    <label>Estado</label>
    <select name="id_estado">
      <?php foreach($ESTADOS as $name=>$id_estado): ?>
        <option value="<?= (int)$id_estado ?>" <?= (!empty($turnoEdit) && (int)$turnoEdit['id_estado']===$id_estado?'selected':'') ?>><?= esc(ucfirst($name)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
<?php else: ?>
  <!-- Modo creación: campos ocultos que se llenan desde el calendario -->
  <input type="hidden" name="fecha" id="inputFecha" required>
  <input type="hidden" name="hora" id="inputHora" required>
  
  <!-- Calendario de disponibilidad -->
  <div class="full">
    <div id="calendarContainer" class="calendar-container">
      <div class="calendar-header">
        <button type="button" class="btn-outline btn-sm" id="btnPrevMonth">
          <i class="fa fa-chevron-left"></i>
        </button>
        <h3 id="calendarTitle">Seleccioná una fecha</h3>
        <button type="button" class="btn-outline btn-sm" id="btnNextMonth">
          <i class="fa fa-chevron-right"></i>
        </button>
      </div>
      <div id="calendarGrid" class="calendar-grid"></div>
      <div id="loadingCalendar" class="loading" style="display:none">
        <i class="fa fa-spinner fa-spin"></i> Cargando disponibilidad...
      </div>
    </div>

    <!-- Slots disponibles -->
    <div id="slotsContainer" class="slots-container">
      <h3 style="margin-bottom:12px">Horarios disponibles</h3>
      <div id="slotsGrid" class="slots-grid"></div>
      <div id="loadingSlots" class="loading" style="display:none">
        <i class="fa fa-spinner fa-spin"></i> Cargando horarios...
      </div>
    </div>
  </div>
<?php endif; ?>

        <div class="form-actions">
  <?php if ($action==='edit' && !empty($turnoEdit)): ?>
    <a class="btn-outline btn-sm" href="agenda.php?tab=turnos"><i class="fa fa-xmark"></i> Cancelar</a>
    <button class="btn btn-sm" type="submit" id="btnSubmit">
      <i class="fa fa-floppy-disk"></i> Guardar cambios
    </button>
  <?php else: ?>
    <button class="btn btn-sm" type="submit" id="btnSubmit" disabled>
      <i class="fa fa-floppy-disk"></i> Confirmar turno
    </button>
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
      </form>

      <div style="margin-top:12px;overflow:auto">
        <table class="table">
          <thead><tr><th>Fecha</th><th>Motivo</th></tr></thead>
          <tbody>
          <?php if (empty($feriados)): ?>
            <tr><td colspan="2" style="color:#666">No hay feriados en el período.</td></tr>
          <?php else: foreach($feriados as $f): ?>
            <tr>
              <td><?= esc($f['fecha']) ?></td>
              <td><?= esc($f['motivo'] ?? '') ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
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
        <div><label>Mes</label><input type="number" name="mes" min="1" max="12" value="<?= esc(qget('mes', (int)date('n'))) ?>" style="width:100px"></div>
        <div><label>Año</label><input type="number" name="anio" value="<?= esc(qget('anio', (int)date('Y'))) ?>" style="width:120px"></div>
        <div><button class="btn btn-sm" type="submit"><i class="fa fa-search"></i> Ver</button></div>
      </form>

      <div style="margin-top:12px;overflow:auto">
        <table class="table">
          <thead><tr><th>Fecha</th><th>Médico</th><th>Desde</th><th>Hasta</th><th>Motivo</th></tr></thead>
          <tbody>
          <?php if (empty($excepciones)): ?>
            <tr><td colspan="5" style="color:#666">No hay excepciones en el período.</td></tr>
          <?php else: foreach($excepciones as $x): ?>
            <tr>
              <td><?= esc($x['fecha']) ?></td>
              <td><?= ($x['id_medico'] ? esc($MEDICOS[(int)$x['id_medico']] ?? '-') : '<em>Global</em>') ?></td>
              <td><?= esc(substr($x['hora_desde']??'',0,5)) ?></td>
              <td><?= esc(substr($x['hora_hasta']??'',0,5)) ?></td>
              <td><?= esc($x['motivo'] ?? '') ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</main>

<script>
<?php if ($tab === 'turnos'): ?>
// Variables globales
let currentMedico = null;
let currentMonth = new Date().getMonth() + 1;
let currentYear = new Date().getFullYear();
let selectedDate = null;
let selectedSlot = null;
let availableDates = [];
let isEditMode = <?= $action === 'edit' ? 'true' : 'false' ?>;

// Elementos DOM
const selectMedico = document.getElementById('selectMedico');
const calendarContainer = document.getElementById('calendarContainer');
const calendarGrid = document.getElementById('calendarGrid');
const calendarTitle = document.getElementById('calendarTitle');
const loadingCalendar = document.getElementById('loadingCalendar');
const slotsContainer = document.getElementById('slotsContainer');
const slotsGrid = document.getElementById('slotsGrid');
const loadingSlots = document.getElementById('loadingSlots');
const btnSubmit = document.getElementById('btnSubmit');
const inputFecha = document.getElementById('inputFecha');
const inputHora = document.getElementById('inputHora');

// Inicializar para edición
if (isEditMode) {
  currentMedico = selectMedico.value;
  if (currentMedico) {
    const editDate = inputFecha.value;
    const editSlot = inputHora.value;
    if (editDate) {
      const dateParts = editDate.split('-');
      currentYear = parseInt(dateParts[0]);
      currentMonth = parseInt(dateParts[1]);
      selectedDate = editDate;
      selectedSlot = editSlot;
    }
    loadCalendar();
    if (selectedDate) {
      selectDate(selectedDate);
    }
  }
}

// Event listeners
selectMedico.addEventListener('change', function() {
  if (!isEditMode) {  // Solo permitir cambio en creación
    currentMedico = this.value;
    if (currentMedico) {
      calendarContainer.style.display = 'block';
      loadCalendar();
    } else {
      calendarContainer.style.display = 'none';
      slotsContainer.style.display = 'none';
    }
    resetSelection();
  }
});

document.getElementById('btnPrevMonth').addEventListener('click', function() {
  currentMonth--;
  if (currentMonth < 1) {
    currentMonth = 12;
    currentYear--;
  }
  loadCalendar();
});

document.getElementById('btnNextMonth').addEventListener('click', function() {
  currentMonth++;
  if (currentMonth > 12) {
    currentMonth = 1;
    currentYear++;
  }
  loadCalendar();
});

// Cargar calendario
async function loadCalendar() {
  if (!currentMedico) return;
  
  loadingCalendar.style.display = 'block';
  calendarGrid.innerHTML = '';
  
  try {
    const response = await fetch(`agenda.php?ajax=get_dates&id_medico=${currentMedico}&mes=${currentMonth}&anio=${currentYear}`);
    const data = await response.json();
    
    if (data.error) {
      alert(data.error);
      return;
    }
    
    availableDates = data.fechas || [];
    renderCalendar();
  } catch (error) {
    console.error('Error:', error);
    alert('Error al cargar el calendario');
  } finally {
    loadingCalendar.style.display = 'none';
  }
}

// Renderizar calendario
function renderCalendar() {
  const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  calendarTitle.textContent = `${monthNames[currentMonth - 1]} ${currentYear}`;
  
  calendarGrid.innerHTML = '';
  
  // Headers de días
  const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
  dayNames.forEach(day => {
    const header = document.createElement('div');
    header.style.fontWeight = 'bold';
    header.style.textAlign = 'center';
    header.style.padding = '8px';
    header.textContent = day;
    calendarGrid.appendChild(header);
  });
  
  // Calcular primer día del mes
  const firstDay = new Date(currentYear, currentMonth - 1, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
  
  // Espacios vacíos antes del primer día
  for (let i = 0; i < firstDay; i++) {
    const emptyDay = document.createElement('div');
    calendarGrid.appendChild(emptyDay);
  }
  
  // Días del mes
  for (let day = 1; day <= daysInMonth; day++) {
    const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const dayElement = document.createElement('div');
    dayElement.className = 'calendar-day';
    dayElement.textContent = day;
    
    if (availableDates.includes(dateStr)) {
      dayElement.classList.add('available');
      dayElement.addEventListener('click', () => selectDate(dateStr));
    } else {
      dayElement.classList.add('disabled');
    }
    
    if (selectedDate === dateStr) {
      dayElement.classList.add('selected');
    }
    
    calendarGrid.appendChild(dayElement);
  }
}

// Seleccionar fecha
async function selectDate(date) {
  selectedDate = date;
  selectedSlot = null;
  renderCalendar();
  
  slotsContainer.style.display = 'block';
  loadingSlots.style.display = 'block';
  slotsGrid.innerHTML = '';
  
  try {
    const response = await fetch(`agenda.php?ajax=get_slots&id_medico=${currentMedico}&fecha=${date}`);
    const data = await response.json();
    
    if (data.error) {
      alert(data.error);
      return;
    }
    
    const slots = data.slots || [];
    if (slots.length === 0) {
      slotsGrid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#666">No hay horarios disponibles</p>';
    } else {
      slots.forEach(slot => {
        const slotBtn = document.createElement('div');
        slotBtn.className = 'slot-btn';
        slotBtn.textContent = slot;
        if (isEditMode && slot === selectedSlot) {
          slotBtn.classList.add('selected');
        }
        slotBtn.addEventListener('click', () => selectSlot(slot));
        slotsGrid.appendChild(slotBtn);
      });
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error al cargar horarios');
  } finally {
    loadingSlots.style.display = 'none';
  }
}

// Seleccionar slot
function selectSlot(slot) {
  selectedSlot = slot;
  
  // Actualizar UI
  document.querySelectorAll('.slot-btn').forEach(btn => {
    btn.classList.remove('selected');
    if (btn.textContent === slot) {
      btn.classList.add('selected');
    }
  });
  
  // Actualizar campos ocultos
  inputFecha.value = selectedDate;
  inputHora.value = slot;
  btnSubmit.disabled = false;
}

// Resetear selección
function resetSelection() {
  if (!isEditMode) {
    selectedDate = null;
    selectedSlot = null;
    slotsContainer.style.display = 'none';
    btnSubmit.disabled = true;
    inputFecha.value = '';
    inputHora.value = '';
  }
}
<?php endif; ?>

</script>
</body>
</html>