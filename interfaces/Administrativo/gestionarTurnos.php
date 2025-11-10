<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 5; // Administrativo
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$nombreAdmin = $_SESSION['nombre'] ?? 'Administrativo';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);


// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// ===== Helpers =====
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function back_to($qs){ header('Location: gestionarTurnos.php?'.$qs); exit; }
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
  'canceled'=>'Turno cancelado correctamente.',
  'error'  => ($msg ?: 'Ocurrió un error. Intentalo nuevamente.')
][$status] ?? null;
$flashKind = [
  'created'=>'success','updated'=>'success','deleted'=>'warning','canceled'=>'warning','error'=>'danger'
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

// Estudios disponibles
$ESTUDIOS = [];
$res = $conn->query("SELECT id_estudio, nombre FROM estudios ORDER BY nombre");
if ($res) { 
  while($row=$res->fetch_assoc()){ 
    $ESTUDIOS[(int)$row['id_estudio']] = $row['nombre'];
  } 
  $res->close(); 
}

// Técnicos activos
$TECNICOS = [];
$res = $conn->query("
  SELECT t.id_tecnico, u.apellido, u.nombre
  FROM tecnicos t
  JOIN usuarios u ON u.id_usuario = t.id_usuario
  WHERE u.activo = 1
  ORDER BY u.apellido, u.nombre
");
if ($res) { 
  while($row=$res->fetch_assoc()){ 
    $TECNICOS[(int)$row['id_tecnico']] = $row['apellido'].', '.$row['nombre'];
  } 
  $res->close(); 
}

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

// ===== AJAX: Obtener técnicos disponibles para un estudio =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_tecnicos_estudio') {
  header('Content-Type: application/json');
  
  $id_estudio = (int)($_GET['id_estudio'] ?? 0);
  
  if (!$id_estudio) {
    echo json_encode(['error' => 'Estudio no seleccionado']);
    exit;
  }
  
  // Obtener técnicos que pueden realizar este estudio
  $stmt = $conn->prepare("
    SELECT DISTINCT t.id_tecnico, u.apellido, u.nombre
    FROM tecnicos t
    JOIN usuarios u ON u.id_usuario = t.id_usuario
    JOIN tecnico_estudio te ON te.id_tecnico = t.id_tecnico
    WHERE te.id_estudio = ?
      AND u.activo = 1
    ORDER BY u.apellido, u.nombre
  ");
  $stmt->bind_param('i', $id_estudio);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $tecnicos = [];
  while ($row = $result->fetch_assoc()) {
    $tecnicos[] = [
      'id_tecnico' => (int)$row['id_tecnico'],
      'nombre' => $row['apellido'] . ', ' . $row['nombre']
    ];
  }
  $stmt->close();
  
  echo json_encode(['tecnicos' => $tecnicos]);
  exit;
}


// ===== AJAX: Obtener órdenes médicas del paciente =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_ordenes') {
  header('Content-Type: application/json');
  
  $id_paciente = (int)($_GET['id_paciente'] ?? 0);
  
  if (!$id_paciente) {
    echo json_encode(['error' => 'Paciente no seleccionado']);
    exit;
  }
  
  // Obtener órdenes médicas activas del paciente
  $stmt = $conn->prepare("
    SELECT 
      om.id_orden,
      om.diagnostico,
      om.estudios_indicados,
      om.fecha_emision,
      CONCAT(u.apellido, ', ', u.nombre) as nombre_medico,
      m.matricula
    FROM ordenes_medicas om
    JOIN medicos m ON m.id_medico = om.id_medico
    JOIN usuarios u ON u.id_usuario = m.id_usuario
    WHERE om.id_paciente = ?
      AND om.estado = 'activa'
    ORDER BY om.fecha_emision DESC
  ");
  $stmt->bind_param('i', $id_paciente);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $ordenes = [];
  while ($row = $result->fetch_assoc()) {
    $ordenes[] = [
      'id_orden' => (int)$row['id_orden'],
      'diagnostico' => $row['diagnostico'],
      'estudios' => $row['estudios_indicados'],
      'fecha_emision' => $row['fecha_emision'],
      'medico' => $row['nombre_medico'],
      'matricula' => $row['matricula']
    ];
  }
  $stmt->close();
  
  echo json_encode(['ordenes' => $ordenes]);
  exit;
}


// ===== AJAX: Obtener fechas disponibles para técnico y estudio =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_dates_estudio') {
  header('Content-Type: application/json');
  
  $id_estudio = (int)($_GET['id_estudio'] ?? 0);
  $mes = (int)($_GET['mes'] ?? date('n'));
  $anio = (int)($_GET['anio'] ?? date('Y'));
  
  if (!$id_estudio) {
    echo json_encode(['error' => 'Estudio no seleccionado']);
    exit;
  }
  
  $desde = sprintf('%04d-%02d-01', $anio, $mes);
  $hasta = date('Y-m-t', strtotime($desde));
  
  // Obtener fechas donde hay técnicos disponibles para este estudio
  $stmt = $conn->prepare("
    SELECT DISTINCT a.fecha
    FROM agenda a
    JOIN tecnico_estudio te ON te.id_tecnico = a.id_tecnico
    WHERE te.id_estudio = ?
      AND a.id_estudio = ?
      AND a.fecha BETWEEN ? AND ?
      AND a.disponible = 1
    ORDER BY a.fecha
  ");
  $stmt->bind_param('iiss', $id_estudio, $id_estudio, $desde, $hasta);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $fechas_con_agenda = [];
  while ($row = $result->fetch_assoc()) {
    $fecha = $row['fecha'];
    
    // Verificar feriados
    if ($HAS_FERIADOS) {
      $stmt_fer = $conn->prepare("SELECT 1 FROM feriados WHERE fecha = ? LIMIT 1");
      $stmt_fer->bind_param('s', $fecha);
      $stmt_fer->execute();
      $es_feriado = $stmt_fer->get_result()->num_rows > 0;
      $stmt_fer->close();
      
      if ($es_feriado) continue;
    }
    
    $fechas_con_agenda[] = $fecha;
  }
  $stmt->close();
  
  echo json_encode(['fechas' => $fechas_con_agenda]);
  exit;
}

// ===== AJAX: Obtener horarios disponibles para estudio =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_slots_estudio') {
  header('Content-Type: application/json');
  
  $id_estudio = (int)($_GET['id_estudio'] ?? 0);
  $fecha = trim($_GET['fecha'] ?? '');
  
  if (!$id_estudio || !$fecha) {
    echo json_encode(['error' => 'Parámetros incompletos']);
    exit;
  }
  
  // Obtener slots disponibles para este estudio en la fecha
  $stmt = $conn->prepare("
    SELECT a.hora_inicio, a.hora_fin, a.id_tecnico, a.id_recurso
    FROM agenda a
    JOIN tecnico_estudio te ON te.id_tecnico = a.id_tecnico
    WHERE te.id_estudio = ?
      AND a.id_estudio = ?
      AND a.fecha = ?
      AND a.disponible = 1
    ORDER BY a.hora_inicio
  ");
  $stmt->bind_param('iis', $id_estudio, $id_estudio, $fecha);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $slots_disponibles = [];
  while ($row = $result->fetch_assoc()) {
    $hora_inicio = substr($row['hora_inicio'], 0, 5);
    $hora_fin = substr($row['hora_fin'], 0, 5);
    $id_tecnico = $row['id_tecnico'];
    $id_recurso = $row['id_recurso'];
    
    $current = strtotime($hora_inicio);
    $end = strtotime($hora_fin);
    
    while ($current < $end) {
      $slot_hora = date('H:i', $current);
      
      // Verificar si el slot NO está ocupado
      $stmt_turno = $conn->prepare("
        SELECT 1 FROM turnos 
        WHERE id_estudio = ? 
          AND fecha = ? 
          AND hora = ?
          AND id_estado != ?
        LIMIT 1
      ");
      $id_cancelado = $ESTADOS['cancelado'] ?? 0;
      $stmt_turno->bind_param('issi', $id_estudio, $fecha, $slot_hora, $id_cancelado);
      $stmt_turno->execute();
      $ocupado = $stmt_turno->get_result()->num_rows > 0;
      $stmt_turno->close();
      
      if (!$ocupado) {
        $slots_disponibles[] = [
          'hora' => $slot_hora,
          'id_tecnico' => $id_tecnico,
          'id_recurso' => $id_recurso
        ];
      }
      
      $current = strtotime('+30 minutes', $current);
    }
  }
  $stmt->close();
  
  echo json_encode(['slots' => $slots_disponibles]);
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
  $stmt=$conn->prepare("UPDATE turnos SET id_paciente=?, id_medico=?, fecha=?, hora=?, id_estado=?, id_estudio=?, id_recurso=?, copago=?, observaciones=?, reprogramado=1 WHERE id_turno=?");
  $id_estudio = null; // NULL
  $copago = 0.00;
  $stmt->bind_param('iissiiidsi', $id_paciente, $id_medico, $fecha, $hora, $id_estado, $id_estudio, $id_recurso, $copago, $obs, $id_turno);
  $ok=$stmt->execute(); $stmt->close();
  back_to('tab=turnos&status='.($ok?'updated':'error'));
}
  }

  // ---- CANCELAR TURNO ----
    if ($form==='turno_cancel') {
      $id_turno = isset($_POST['id_turno']) ? (int)$_POST['id_turno'] : 0;
      if (!$id_turno) back_to('tab=turnos&status=error&msg='.rawurlencode('ID de turno inválido'));
      
      $conn->begin_transaction();
      try {
        // Obtener datos del turno antes de cancelar
        $stmt = $conn->prepare("
          SELECT id_medico, id_tecnico, fecha, hora, id_recurso 
          FROM turnos 
          WHERE id_turno = ?
        ");
        $stmt->bind_param('i', $id_turno);
        $stmt->execute();
        $turno_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$turno_data) {
          throw new Exception('Turno no encontrado');
        }
        
        // Actualizar el turno a estado "cancelado"
        $id_estado_cancelado = $ESTADOS['cancelado'] ?? 0;
        $stmt = $conn->prepare("UPDATE turnos SET id_estado = ? WHERE id_turno = ?");
        $stmt->bind_param('ii', $id_estado_cancelado, $id_turno);
        $stmt->execute();
        $stmt->close();
        
        // Liberar el slot en la agenda
        if ($turno_data['id_medico'] || $turno_data['id_tecnico']) {
          $id_profesional = $turno_data['id_medico'] ?: $turno_data['id_tecnico'];
          $campo_profesional = $turno_data['id_medico'] ? 'id_medico' : 'id_tecnico';
          
          $stmt = $conn->prepare("
            UPDATE agenda 
            SET disponible = 1 
            WHERE $campo_profesional = ? 
              AND fecha = ? 
              AND ? BETWEEN hora_inicio AND hora_fin
          ");
          $stmt->bind_param('iss', $id_profesional, $turno_data['fecha'], $turno_data['hora']);
          $stmt->execute();
          $stmt->close();
        }
        
        $conn->commit();
        back_to('tab=turnos&status=canceled');
        
      } catch (Exception $e) {
        $conn->rollback();
        back_to('tab=turnos&status=error&msg='.rawurlencode('Error al cancelar: '.$e->getMessage()));
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

  // ---- ABM TURNOS ESTUDIOS ----
if ($tab==='turnos_estudios') {
  if ($form==='turno_estudio_create' || $form==='turno_estudio_update') {
    $id_turno   = isset($_POST['id_turno']) ? (int)$_POST['id_turno'] : 0;
    $id_paciente= isset($_POST['id_paciente']) ? (int)$_POST['id_paciente'] : 0;
    $id_estudio = isset($_POST['id_estudio']) ? (int)$_POST['id_estudio'] : 0;
    $id_tecnico = isset($_POST['id_tecnico']) ? (int)$_POST['id_tecnico'] : 0;
    $fecha      = trim($_POST['fecha'] ?? '');
    $hora       = trim($_POST['hora'] ?? '');
    $obs        = trim($_POST['observaciones'] ?? '');
    
    if ($form === 'turno_estudio_create') {
      $id_estado = $ESTADOS['confirmado'] ?? $ESTADOS['pendiente'] ?? 0;
    } else {
      $id_estado = isset($_POST['id_estado']) ? (int)$_POST['id_estado'] : ($ESTADOS['confirmado'] ?? 0);
    }

    if (!$id_paciente || !$id_estudio || !$id_tecnico || $fecha==='' || $hora==='') {
      back_to('tab=turnos_estudios&status=error&msg='.rawurlencode('Completá todos los campos requeridos'));
    }

    // Validar que el técnico tenga agenda disponible
    $stmt_agenda = $conn->prepare("
      SELECT id_recurso FROM agenda
      WHERE id_tecnico = ?
        AND id_estudio = ?
        AND fecha = ?
        AND ? BETWEEN hora_inicio AND hora_fin
        AND disponible = 1
      LIMIT 1
    ");
    $stmt_agenda->bind_param('iiss', $id_tecnico, $id_estudio, $fecha, $hora);
    $stmt_agenda->execute();
    $result_agenda = $stmt_agenda->get_result();
    $agenda_row = $result_agenda->fetch_assoc();
    $stmt_agenda->close();
    
    if (!$agenda_row) {
      back_to('tab=turnos_estudios&status=error&msg='.rawurlencode('El horario seleccionado no está disponible'));
    }
    $id_recurso = (int)$agenda_row['id_recurso'];

    // No superposición
    $sql_chk = "
      SELECT 1
      FROM turnos t
      WHERE t.id_tecnico=? AND t.id_estudio=? AND t.fecha=? AND t.hora=? 
        AND t.id_estado<>?
        ".($form==='turno_estudio_update'?" AND t.id_turno<>?":"")."
      LIMIT 1
    ";
    $stmt = $conn->prepare($sql_chk);
    $id_cancelado = (int)($ESTADOS['cancelado'] ?? 0);
    if ($form==='turno_estudio_update') {
      $stmt->bind_param('iissii',$id_tecnico,$id_estudio,$fecha,$hora,$id_cancelado,$id_turno);
    } else {
      $stmt->bind_param('iissi',$id_tecnico,$id_estudio,$fecha,$hora,$id_cancelado);
    }
    $stmt->execute();
    $dup = $stmt->get_result()->num_rows>0;
    $stmt->close();
    if ($dup) {
      back_to('tab=turnos_estudios&status=error&msg='.rawurlencode('Ya existe un turno para ese estudio en el mismo horario.'));
    }

  if ($form==='turno_estudio_create') {
  $id_orden_medica = isset($_POST['id_orden_medica']) ? (int)$_POST['id_orden_medica'] : 0;
  
  if (!$id_orden_medica) {
    back_to('tab=turnos_estudios&status=error&msg='.rawurlencode('Debe seleccionar una orden médica válida'));
  }
  
  $stmt=$conn->prepare("INSERT INTO turnos (id_paciente,id_tecnico,id_estudio,fecha,hora,id_estado,id_recurso,copago,observaciones,reprogramado,id_orden_medica) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
  $id_medico = null;
  $copago = 0.00;
  $reprogramado = 0;
  $stmt->bind_param('iiiissidisi', $id_paciente, $id_tecnico, $id_estudio, $fecha, $hora, $id_estado, $id_recurso, $copago, $obs, $reprogramado, $id_orden_medica);
  $ok=$stmt->execute(); $stmt->close();
  
  // Marcar la orden médica como utilizada
  if ($ok) {
    $stmt = $conn->prepare("UPDATE ordenes_medicas SET estado = 'utilizada' WHERE id_orden = ?");
    $stmt->bind_param('i', $id_orden_medica);
    $stmt->execute();
    $stmt->close();
  }
  
  back_to('tab=turnos_estudios&status='.($ok?'created':'error'));
} else {
  // En modo edición, obtener la orden médica existente
  $stmt_orden = $conn->prepare("SELECT id_orden_medica FROM turnos WHERE id_turno = ?");
  $stmt_orden->bind_param('i', $id_turno);
  $stmt_orden->execute();
  $orden_result = $stmt_orden->get_result()->fetch_assoc();
  $stmt_orden->close();
  $id_orden_medica = $orden_result['id_orden_medica'] ?? null;
  
  $stmt=$conn->prepare("UPDATE turnos SET id_paciente=?, id_tecnico=?, id_estudio=?, fecha=?, hora=?, id_estado=?, id_recurso=?, copago=?, observaciones=?, reprogramado=1, id_orden_medica=? WHERE id_turno=?");
  $copago = 0.00;
  $stmt->bind_param('iiiissidiii', $id_paciente, $id_tecnico, $id_estudio, $fecha, $hora, $id_estado, $id_recurso, $copago, $obs, $id_orden_medica, $id_turno);
  $ok=$stmt->execute(); $stmt->close();
  back_to('tab=turnos_estudios&status='.($ok?'updated':'error'));
}
  }

  // ---- CANCELAR TURNO ESTUDIO ----
  if ($form==='turno_estudio_cancel') {
    $id_turno = isset($_POST['id_turno']) ? (int)$_POST['id_turno'] : 0;
    if (!$id_turno) back_to('tab=turnos_estudios&status=error&msg='.rawurlencode('ID de turno inválido'));
    
    $conn->begin_transaction();
    try {
      $stmt = $conn->prepare("
        SELECT id_tecnico, id_estudio, fecha, hora, id_recurso 
        FROM turnos 
        WHERE id_turno = ?
      ");
      $stmt->bind_param('i', $id_turno);
      $stmt->execute();
      $turno_data = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      
      if (!$turno_data) {
        throw new Exception('Turno no encontrado');
      }
      
      $id_estado_cancelado = $ESTADOS['cancelado'] ?? 0;
      $stmt = $conn->prepare("UPDATE turnos SET id_estado = ? WHERE id_turno = ?");
      $stmt->bind_param('ii', $id_estado_cancelado, $id_turno);
      $stmt->execute();
      $stmt->close();
      
      // Liberar el slot en la agenda
      if ($turno_data['id_tecnico']) {
        $stmt = $conn->prepare("
          UPDATE agenda 
          SET disponible = 1 
          WHERE id_tecnico = ? 
            AND id_estudio = ?
            AND fecha = ? 
            AND ? BETWEEN hora_inicio AND hora_fin
        ");
        $stmt->bind_param('iiss', $turno_data['id_tecnico'], $turno_data['id_estudio'], $turno_data['fecha'], $turno_data['hora']);
        $stmt->execute();
        $stmt->close();
      }
      
      $conn->commit();
      back_to('tab=turnos_estudios&status=canceled');
      
    } catch (Exception $e) {
      $conn->rollback();
      back_to('tab=turnos_estudios&status=error&msg='.rawurlencode('Error al cancelar: '.$e->getMessage()));
    }
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

// ===== TURNOS ESTUDIOS =====
$turnosEstudios = [];
$turnoEstudioEdit = null;
if ($tab==='turnos_estudios') {
  $f_estudio = (int)qget('f_estudio',0);
  $f_tecnico = (int)qget('f_tecnico',0);
  $f_estado = (int)qget('f_estado',0);
  $f_desde  = qget('f_desde','');
  $f_hasta  = qget('f_hasta','');

  $sql = "
  SELECT t.id_turno, t.fecha, t.hora, t.id_estado, t.observaciones,
         e.nombre_estado,
         est.nombre AS nombre_estudio,
         up.apellido AS ap_pac, up.nombre AS no_pac,
         ut.apellido AS ap_tec, ut.nombre AS no_tec,
         p.id_paciente, tec.id_tecnico, t.id_estudio,
         om.id_orden, om.diagnostico,
         CONCAT(umed.apellido, ', ', umed.nombre) as medico_orden
  FROM turnos t
  LEFT JOIN estados e ON e.id_estado=t.id_estado
  LEFT JOIN pacientes p ON p.id_paciente=t.id_paciente
  LEFT JOIN usuarios up ON up.id_usuario=p.id_usuario
  LEFT JOIN tecnicos tec ON tec.id_tecnico=t.id_tecnico
  LEFT JOIN usuarios ut ON ut.id_usuario=tec.id_usuario
  LEFT JOIN estudios est ON est.id_estudio=t.id_estudio
  LEFT JOIN ordenes_medicas om ON om.id_orden=t.id_orden_medica
  LEFT JOIN medicos med ON med.id_medico=om.id_medico
  LEFT JOIN usuarios umed ON umed.id_usuario=med.id_usuario
  WHERE t.id_estudio IS NOT NULL
";
  $w = []; $params = []; $types = '';

  if ($f_estudio>0){ $w[]=" t.id_estudio=? "; $types.='i'; $params[]=$f_estudio; }
  if ($f_tecnico>0){ $w[]=" t.id_tecnico=? "; $types.='i'; $params[]=$f_tecnico; }
  if ($f_estado>0){ $w[]=" t.id_estado=? "; $types.='i'; $params[]=$f_estado; }
  if ($f_desde!==''){   $w[]=" t.fecha>=? "; $types.='s'; $params[]=$f_desde; }
  if ($f_hasta!==''){   $w[]=" t.fecha<=? "; $types.='s'; $params[]=$f_hasta; }
  if ($w){ $sql .= " AND ".implode(' AND ',$w); }
  $sql .= " ORDER BY t.fecha DESC, t.hora ASC LIMIT 500";

  $stmt=$conn->prepare($sql);
  if (!empty($params)){ $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $r = $stmt->get_result();
  while($r && $row=$r->fetch_assoc()){ $turnosEstudios[]=$row; }
  $stmt->close();

  if ($action==='edit' && $id>0) {
    $stmt=$conn->prepare("
      SELECT id_turno,id_paciente,id_tecnico,id_estudio,fecha,hora,id_estado,observaciones
      FROM turnos WHERE id_turno=? LIMIT 1
    ");
    $stmt->bind_param('i',$id); $stmt->execute();
    $turnoEstudioEdit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$turnoEstudioEdit) $action='list';
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
/* Modal */
.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5);align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:#fff;border-radius:16px;padding:24px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3);animation:modalSlideIn .3s ease}
@keyframes modalSlideIn{from{transform:translateY(-50px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--border)}
.modal-header h2{color:#111;font-size:1.4rem;display:flex;align-items:center;gap:8px}
.modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer;color:#666;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:50%}
.modal-close:hover{background:#f3f4f6;color:#111}
.modal-body{margin-bottom:20px}
.modal-info{background:#f9fafb;padding:12px;border-radius:8px;margin-bottom:12px}
.modal-info-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #e5e7eb}
.modal-info-row:last-child{border-bottom:none}
.modal-info-label{font-weight:700;color:#666}
.modal-info-value{color:#111}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}
</style>
</head>
<body>
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

<main class="container">
  <h1>Agenda</h1>

  <div class="backbar">
    <a class="btn gray" href="principalAdministrativo.php"><i class="fa fa-arrow-left"></i> Volver al inicio</a>
  </div>

  <div class="card tabbar">
    <a class="btn<?= $tab==='turnos'?'':' btn-outline' ?>" href="gestionarTurnos.php?tab=turnos"><i class="fa fa-calendar-check"></i> Turnos Medicos</a>
    <a class="btn<?= $tab==='turnos_estudios'?'':' btn-outline' ?>" href="gestionarTurnos.php?tab=turnos_estudios"><i class="fa fa-flask"></i> Turnos Estudios</a>
    <?php if ($HAS_FERIADOS): ?>
      <a class="btn<?= $tab==='feriados'?'':' btn-outline' ?>" href="gestionarTurnos.php?tab=feriados"><i class="fa fa-umbrella-beach"></i> Feriados</a>
    <?php endif; ?>
    <?php if ($HAS_EXCEPCIONES): ?>
      <a class="btn<?= $tab==='excepciones'?'':' btn-outline' ?>" href="gestionarTurnos.php?tab=excepciones"><i class="fa fa-ban"></i> Excepciones</a>
    <?php endif; ?>
  </div>

  <?php if ($flashText): ?>
    <div class="card" style="padding:12px;border-left:4px solid <?= $flashKind==='danger'?'#ef4444':($flashKind==='warning'?'#f59e0b':'#22c55e') ?>">
      <strong><?= esc($flashText) ?></strong>
    </div>
  <?php endif; ?>

  <?php if ($tab==='turnos'): ?>
    <!-- ======= ALTA / EDICIÓN TURNO MEDICO ======= -->
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
    <a class="btn-outline btn-sm" href="gestionarTurnos.php?tab=turnos"><i class="fa fa-xmark"></i> Cancelar</a>
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
<?php if (empty($turnos)): ?>
  <tr><td colspan="7" style="color:#666">No hay turnos en el listado.</td></tr>
<?php else: foreach($turnos as $t): 
  $badge=strtolower($t['nombre_estado']??''); 
  $id_cancelado = $ESTADOS['cancelado'] ?? 0;
  $id_atendido = $ESTADOS['atendido'] ?? 0;
  $puede_modificar = ((int)$t['id_estado'] !== $id_cancelado && (int)$t['id_estado'] !== $id_atendido);
?>
  <tr>
    <td><?= esc($t['fecha']) ?></td>
    <td><?= esc(substr($t['hora'],0,5)) ?></td>
    <td><?= esc(($t['ap_pac']??'-').', '.($t['no_pac']??'')) ?></td>
    <td><?= esc(($t['ap_med']??'-').', '.($t['no_med']??'')) ?></td>
    <td><span class="badge <?= esc($badge) ?>"><?= esc(ucfirst($t['nombre_estado']??'-')) ?></span></td>
    <td><?= esc($t['observaciones']??'') ?></td>
    <td style="display:flex;gap:4px">
  <?php if ($puede_modificar): ?>
    <a class="btn-outline btn-sm" href="gestionarTurnos.php?tab=turnos&action=edit&id=<?= (int)$t['id_turno'] ?>">
      <i class="fa fa-pen"></i> Reprogramar
    </a>
    <button 
      class="btn-danger btn-sm" 
      type="button"
      onclick="openCancelModal(<?= (int)$t['id_turno'] ?>, '<?= esc($t['fecha']) ?>', '<?= esc(substr($t['hora'],0,5)) ?>', '<?= esc(($t['ap_pac']??'-').', '.($t['no_pac']??'')) ?>', '<?= esc(($t['ap_med']??'-').', '.($t['no_med']??'')) ?>')">
      <i class="fa fa-ban"></i> Cancelar
    </button>
  <?php else: ?>
    <span style="color:#999;font-size:0.85rem">Sin acciones</span>
  <?php endif; ?>
</td>
  </tr>
<?php endforeach; endif; ?>
</tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($tab==='turnos_estudios'): ?>
  <!-- ======= ALTA / EDICIÓN TURNO ESTUDIO ======= -->
  <div class="card">
    <h2 style="margin-bottom:10px">
      <i class="fa fa-plus-circle"></i> <?= ($action==='edit' && !empty($turnoEstudioEdit)) ? 'Editar turno de estudio' : 'Nuevo turno de estudio' ?>
    </h2>
    <form method="post" id="formTurnoEstudio" class="form-grid" autocomplete="off">
      <?php if ($action==='edit' && !empty($turnoEstudioEdit)): ?>
        <input type="hidden" name="form_action" value="turno_estudio_update">
        <input type="hidden" name="id_turno" value="<?= (int)$turnoEstudioEdit['id_turno'] ?>">
      <?php else: ?>
        <input type="hidden" name="form_action" value="turno_estudio_create">
      <?php endif; ?>

  <?php if ($action!=='edit'): ?>
<!-- Selector de orden médica (solo en creación) -->
<div class="full" id="ordenMedicaContainer" style="display:none">
  <div style="background:#fff3cd;border:2px solid #ffc107;border-radius:12px;padding:16px;margin-bottom:12px">
    <h3 style="margin-bottom:12px;color:#856404;display:flex;align-items:center;gap:8px">
      <i class="fa fa-file-medical"></i> Orden Médica Requerida
    </h3>
    <p style="margin-bottom:12px;color:#856404">Seleccioná la orden médica con la que solicitás este estudio:</p>
    <select id="selectOrdenMedica" style="width:100%;padding:12px;border:2px solid #ffc107;border-radius:8px;font-size:0.95rem">
      <option value="">Cargando órdenes médicas...</option>
    </select>
    <div id="detalleOrden" style="display:none;margin-top:12px;padding:12px;background:#fff;border-radius:8px;border:1px solid #e5e7eb">
      <div style="display:grid;gap:8px">
        <div><strong style="color:#666">Médico:</strong> <span id="ordenMedico"></span></div>
        <div><strong style="color:#666">Diagnóstico:</strong> <span id="ordenDiagnostico"></span></div>
        <div><strong style="color:#666">Estudios:</strong> <span id="ordenEstudios"></span></div>
        <div><strong style="color:#666">Fecha emisión:</strong> <span id="ordenFecha"></span></div>
        <div style="color:#22c55e;font-weight:bold"><i class="fa fa-check-circle"></i> Firma digital verificada</div>
      </div>
    </div>
  </div>
</div>

<!-- Campo oculto para enviar el ID de orden médica -->
<input type="hidden" name="id_orden_medica" id="inputOrdenMedica" required>
<input type="hidden" name="id_tecnico_auto" id="inputTecnicoAuto">
<?php endif; ?>

  <div>
    <label>Paciente *</label>
    <select name="id_paciente" id="selectPacienteEstudio" required <?= ($action==='edit' ? 'disabled' : '') ?>>
      <option value="">Seleccionar…</option>
      <?php foreach($PACIENTES as $idp=>$np): ?>
        <option value="<?= (int)$idp ?>" <?= (!empty($turnoEstudioEdit) && (int)$turnoEstudioEdit['id_paciente']===$idp?'selected':'') ?>><?= esc($np) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($action==='edit'): ?>
      <input type="hidden" name="id_paciente" value="<?= (int)$turnoEstudioEdit['id_paciente'] ?>">
    <?php endif; ?>
  </div>

  <div>
  <label>Estudio *</label>
  
<select name="id_estudio" id="selectEstudioTurno" required <?= ($action==='edit' ? 'disabled' : '') ?>>

      <option value="">Seleccionar estudio...</option>
      <?php foreach($ESTUDIOS as $ide=>$ne): ?>
        <option value="<?= (int)$ide ?>" <?= (!empty($turnoEstudioEdit) && (int)$turnoEstudioEdit['id_estudio']===$ide?'selected':'') ?>>
          <?= esc($ne) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($action==='edit'): ?>
      <input type="hidden" name="id_estudio" value="<?= (int)$turnoEstudioEdit['id_estudio'] ?>">
      <input type="hidden" name="id_tecnico" value="<?= (int)$turnoEstudioEdit['id_tecnico'] ?>">
    <?php endif; ?>
  </div>

  <?php if ($action==='edit'): ?>
  <!-- Modo edición: usar calendario igual que creación -->
  <input type="hidden" name="fecha" id="inputFechaEstudio" value="<?= esc($turnoEstudioEdit['fecha']) ?>" required>
  <input type="hidden" name="hora" id="inputHoraEstudio" value="<?= esc(substr($turnoEstudioEdit['hora'],0,5)) ?>" required>
  <input type="hidden" name="id_tecnico" value="<?= (int)$turnoEstudioEdit['id_tecnico'] ?>">
  
  <!-- Calendario de disponibilidad -->
  <div class="full">
    <div id="calendarContainerEstudio" class="calendar-container" style="display:block">
      <div class="calendar-header">
        <button type="button" class="btn-outline btn-sm" id="btnPrevMonthEstudio">
          <i class="fa fa-chevron-left"></i>
        </button>
        <h3 id="calendarTitleEstudio">Seleccioná una fecha</h3>
        <button type="button" class="btn-outline btn-sm" id="btnNextMonthEstudio">
          <i class="fa fa-chevron-right"></i>
        </button>
      </div>
      <div id="calendarGridEstudio" class="calendar-grid"></div>
      <div id="loadingCalendarEstudio" class="loading" style="display:none">
        <i class="fa fa-spinner fa-spin"></i> Cargando disponibilidad...
      </div>
    </div>

    <div id="slotsContainerEstudio" class="slots-container" style="display:block">
      <h3 style="margin-bottom:12px">Horarios disponibles</h3>
      <div id="slotsGridEstudio" class="slots-grid"></div>
      <div id="loadingSlotsEstudio" class="loading" style="display:none">
        <i class="fa fa-spinner fa-spin"></i> Cargando horarios...
      </div>
    </div>
  </div>

  <div>
    <label>Estado</label>
    <select name="id_estado">
      <?php foreach($ESTADOS as $name=>$id_estado): ?>
        <option value="<?= (int)$id_estado ?>" <?= ((int)$turnoEstudioEdit['id_estado']===$id_estado?'selected':'') ?>><?= esc(ucfirst($name)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
<?php else: ?>
    <!-- Modo creación: calendario y slots -->
    <input type="hidden" name="fecha" id="inputFechaEstudio" required>
    <input type="hidden" name="hora" id="inputHoraEstudio" required>
    
    <div class="full">
      <div id="calendarContainerEstudio" class="calendar-container">
        <div class="calendar-header">
          <button type="button" class="btn-outline btn-sm" id="btnPrevMonthEstudio">
            <i class="fa fa-chevron-left"></i>
          </button>
          <h3 id="calendarTitleEstudio">Seleccioná una fecha</h3>
          <button type="button" class="btn-outline btn-sm" id="btnNextMonthEstudio">
            <i class="fa fa-chevron-right"></i>
          </button>
        </div>
        <div id="calendarGridEstudio" class="calendar-grid"></div>
        <div id="loadingCalendarEstudio" class="loading" style="display:none">
          <i class="fa fa-spinner fa-spin"></i> Cargando disponibilidad...
        </div>
      </div>

      <div id="slotsContainerEstudio" class="slots-container">
        <h3 style="margin-bottom:12px">Horarios disponibles</h3>
        <div id="slotsGridEstudio" class="slots-grid"></div>
        <div id="loadingSlotsEstudio" class="loading" style="display:none">
          <i class="fa fa-spinner fa-spin"></i> Cargando horarios...
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="full">
    <label>Observaciones</label>
    <textarea name="observaciones" rows="3"><?= !empty($turnoEstudioEdit) ? esc($turnoEstudioEdit['observaciones']) : '' ?></textarea>
  </div>

  <div class="form-actions">
    <?php if ($action==='edit' && !empty($turnoEstudioEdit)): ?>
      <a class="btn-outline btn-sm" href="gestionarTurnos.php?tab=turnos_estudios"><i class="fa fa-xmark"></i> Cancelar</a>
      <button class="btn btn-sm" type="submit">
        <i class="fa fa-floppy-disk"></i> Guardar cambios
      </button>
    <?php else: ?>
      <button class="btn btn-sm" type="submit" id="btnSubmitEstudio" disabled>
        <i class="fa fa-floppy-disk"></i> Confirmar turno
      </button>
    <?php endif; ?>
  </div>
</form>
  </div>

  <!-- ======= LISTADO / FILTROS TURNOS ESTUDIOS ======= -->
  <div class="card">
    <h2 style="margin-bottom:10px"><i class="fa fa-flask"></i> Listado de turnos de estudios</h2>
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="tab" value="turnos_estudios"/>
      <div>
        <label>Estudio</label>
        <select name="f_estudio">
          <option value="0">Todos</option>
          <?php foreach($ESTUDIOS as $ide=>$ne): ?>
            <option value="<?= (int)$ide ?>" <?= ((int)qget('f_estudio',0)===$ide?'selected':'') ?>><?= esc($ne) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Técnico</label>
        <select name="f_tecnico">
          <option value="0">Todos</option>
          <?php foreach($TECNICOS as $idt=>$nt): ?>
            <option value="<?= (int)$idt ?>" <?= ((int)qget('f_tecnico',0)===$idt?'selected':'') ?>><?= esc($nt) ?></option>
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
            <th>Fecha</th><th>Hora</th><th>Paciente</th><th>Estudio</th><th>Técnico</th><th>Orden Médica</th><th>Estado</th><th>Obs.</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
<?php if (empty($turnosEstudios)): ?>
  <tr><td colspan="9" style="color:#666">No hay turnos de estudios en el listado.</td></tr>
<?php else: foreach($turnosEstudios as $t): 
  $badge=strtolower($t['nombre_estado']??''); 
  $id_cancelado = $ESTADOS['cancelado'] ?? 0;
  $id_atendido = $ESTADOS['atendido'] ?? 0;
  $puede_modificar = ((int)$t['id_estado'] !== $id_cancelado && (int)$t['id_estado'] !== $id_atendido);
?>
 <tr>
  <td><?= esc($t['fecha']) ?></td>
  <td><?= esc(substr($t['hora'],0,5)) ?></td>
  <td><?= esc(($t['ap_pac']??'-').', '.($t['no_pac']??'')) ?></td>
  <td><?= esc($t['nombre_estudio']??'-') ?></td>
  <td><?= esc(($t['ap_tec']??'-').', '.($t['no_tec']??'')) ?></td>
  <td>
    <?php if ($t['id_orden']): ?>
      <span title="<?= esc($t['diagnostico']) ?>" style="cursor:help">
        <i class="fa fa-file-medical" style="color:var(--brand)"></i>
        <?= esc($t['medico_orden']) ?>
      </span>
    <?php else: ?>
      <span style="color:#999">Sin orden</span>
    <?php endif; ?>
  </td>
  <td><span class="badge <?= esc($badge) ?>"><?= esc(ucfirst($t['nombre_estado']??'-')) ?></span></td>
  <td><?= esc($t['observaciones']??'') ?></td>
  <td style="display:flex;gap:4px">
    <?php if ($puede_modificar): ?>
      <a class="btn-outline btn-sm" href="gestionarTurnos.php?tab=turnos_estudios&action=edit&id=<?= (int)$t['id_turno'] ?>">
        <i class="fa fa-pen"></i> Reprogramar
      </a>
      <button 
        class="btn-danger btn-sm" 
        type="button"
        onclick="openCancelModalEstudio(<?= (int)$t['id_turno'] ?>, '<?= esc($t['fecha']) ?>', '<?= esc(substr($t['hora'],0,5)) ?>', '<?= esc(($t['ap_pac']??'-').', '.($t['no_pac']??'')) ?>', '<?= esc($t['nombre_estudio']??'-') ?>', '<?= esc(($t['ap_tec']??'-').', '.($t['no_tec']??'')) ?>')">
        <i class="fa fa-ban"></i> Cancelar
      </button>
    <?php else: ?>
      <span style="color:#999;font-size:0.85rem">Sin acciones</span>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
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
              <option value="<?= (int)$idm?>" <?= ((int)qget('f_medico_exc',0)===$idm?'selected':'') ?>><?= esc($nm) ?></option>
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

<!-- Modal de cancelación turnos medicos -->
<div id="cancelModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2><i class="fa fa-exclamation-triangle" style="color:var(--warn)"></i> Confirmar cancelación</h2>
      <button type="button" class="modal-close" onclick="closeCancelModal()">
        <i class="fa fa-times"></i>
      </button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:16px;color:#666">¿Estás seguro que deseas cancelar este turno? Esta acción liberará el horario en la agenda.</p>
      <div class="modal-info">
        <div class="modal-info-row">
          <span class="modal-info-label">Fecha:</span>
          <span class="modal-info-value" id="cancelFecha"></span>
        </div>
        <div class="modal-info-row">
          <span class="modal-info-label">Hora:</span>
          <span class="modal-info-value" id="cancelHora"></span>
        </div>
        <div class="modal-info-row">
          <span class="modal-info-label">Paciente:</span>
          <span class="modal-info-value" id="cancelPaciente"></span>
        </div>
        <div class="modal-info-row">
          <span class="modal-info-label">Médico:</span>
          <span class="modal-info-value" id="cancelMedico"></span>
        </div>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn-outline btn-sm" onclick="closeCancelModal()">
        <i class="fa fa-times"></i> No, volver
      </button>
      <form method="post" style="display:inline">
        <input type="hidden" name="form_action" value="turno_cancel">
        <input type="hidden" name="id_turno" id="cancelIdTurno">
        <button type="submit" class="btn-danger btn-sm">
          <i class="fa fa-ban"></i> Sí, cancelar turno
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Modal de cancelación estudios -->
<div id="cancelModalEstudio" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2><i class="fa fa-exclamation-triangle" style="color:var(--warn)"></i> Confirmar cancelación</h2>
      <button type="button" class="modal-close" onclick="closeCancelModalEstudio()">
        <i class="fa fa-times"></i>
      </button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:16px;color:#666">¿Estás seguro que deseas cancelar este turno de estudio? Esta acción liberará el horario en la agenda.</p>
      <div class="modal-info">
        <div class="modal-info-row">
          <span class="modal-info-label">Fecha:</span>
          <span class="modal-info-value" id="cancelEstudioFecha"></span>
        </div>
        <div class="modal-info-row">
          <span class="modal-info-label">Hora:</span>
          <span class="modal-info-value" id="cancelEstudioHora"></span>
        </div>
        <div class="modal-info-row">
          <span class="modal-info-label">Paciente:</span>
          <span class="modal-info-value" id="cancelEstudioPaciente"></span>
        </div>
        <div class="modal-info-row">
          <span class="modal-info-label">Estudio:</span>
          <span class="modal-info-value" id="cancelEstudioNombre"></span>
        </div>
        <div class="modal-info-row">
          <span class="modal-info-label">Técnico:</span>
          <span class="modal-info-value" id="cancelEstudioTecnico"></span>
        </div>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn-outline btn-sm" onclick="closeCancelModalEstudio()">
        <i class="fa fa-times"></i> No, volver
      </button>
      <form method="post" style="display:inline">
        <input type="hidden" name="form_action" value="turno_estudio_cancel">
        <input type="hidden" name="id_turno" id="cancelEstudioIdTurno">
        <button type="submit" class="btn-danger btn-sm">
          <i class="fa fa-ban"></i> Sí, cancelar turno
        </button>
      </form>
    </div>
  </div>
</div>

<script>
// ===== JAVASCRIPT PARA TURNOS MÉDICOS =====
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
  if (!isEditMode) {
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

async function loadCalendar() {
  if (!currentMedico) return;
  loadingCalendar.style.display = 'block';
  calendarGrid.innerHTML = '';
  try {
    const response = await fetch(`gestionarTurnos.php?ajax=get_dates&id_medico=${currentMedico}&mes=${currentMonth}&anio=${currentYear}`);
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

function renderCalendar() {
  const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  calendarTitle.textContent = `${monthNames[currentMonth - 1]} ${currentYear}`;
  calendarGrid.innerHTML = '';
  const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
  dayNames.forEach(day => {
    const header = document.createElement('div');
    header.style.fontWeight = 'bold';
    header.style.textAlign = 'center';
    header.style.padding = '8px';
    header.textContent = day;
    calendarGrid.appendChild(header);
  });
  const firstDay = new Date(currentYear, currentMonth - 1, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
  for (let i = 0; i < firstDay; i++) {
    calendarGrid.appendChild(document.createElement('div'));
  }
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

async function selectDate(date) {
  selectedDate = date;
  selectedSlot = null;
  renderCalendar();
  slotsContainer.style.display = 'block';
  loadingSlots.style.display = 'block';
  slotsGrid.innerHTML = '';
  try {
    const response = await fetch(`gestionarTurnos.php?ajax=get_slots&id_medico=${currentMedico}&fecha=${date}`);
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

function selectSlot(slot) {
  selectedSlot = slot;
  document.querySelectorAll('.slot-btn').forEach(btn => {
    btn.classList.remove('selected');
    if (btn.textContent === slot) {
      btn.classList.add('selected');
    }
  });
  inputFecha.value = selectedDate;
  inputHora.value = slot;
  btnSubmit.disabled = false;
}

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

// ===== MODALES DE CANCELACIÓN =====
function openCancelModal(id, fecha, hora, paciente, medico) {
  document.getElementById('cancelIdTurno').value = id;
  document.getElementById('cancelFecha').textContent = fecha;
  document.getElementById('cancelHora').textContent = hora;
  document.getElementById('cancelPaciente').textContent = paciente;
  document.getElementById('cancelMedico').textContent = medico;
  document.getElementById('cancelModal').classList.add('active');
}

function closeCancelModal() {
  document.getElementById('cancelModal').classList.remove('active');
}

document.getElementById('cancelModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeCancelModal();
  }
});

function openCancelModalEstudio(id, fecha, hora, paciente, estudio, tecnico) {
  document.getElementById('cancelEstudioIdTurno').value = id;
  document.getElementById('cancelEstudioFecha').textContent = fecha;
  document.getElementById('cancelEstudioHora').textContent = hora;
  document.getElementById('cancelEstudioPaciente').textContent = paciente;
  document.getElementById('cancelEstudioNombre').textContent = estudio;
  document.getElementById('cancelEstudioTecnico').textContent = tecnico;
  document.getElementById('cancelModalEstudio').classList.add('active');
}

function closeCancelModalEstudio() {
  document.getElementById('cancelModalEstudio').classList.remove('active');
}

document.getElementById('cancelModalEstudio').addEventListener('click', function(e) {
  if (e.target === this) {
    closeCancelModalEstudio();
  }
});

// ===== JAVASCRIPT PARA TURNOS ESTUDIOS =====
<?php if ($tab === 'turnos_estudios'): ?>

let ordenesDisponibles = [];
let currentEstudio = null;
let currentMonthEstudio = new Date().getMonth() + 1;
let currentYearEstudio = new Date().getFullYear();
let selectedDateEstudio = null;
let selectedSlotEstudio = null;
let availableDatesEstudio = [];
let currentTecnicoAuto = null;

const selectPacienteEstudio = document.getElementById('selectPacienteEstudio');
const ordenMedicaContainer = document.getElementById('ordenMedicaContainer');
const selectOrdenMedica = document.getElementById('selectOrdenMedica');
const detalleOrden = document.getElementById('detalleOrden');
const selectEstudioTurno = document.getElementById('selectEstudioTurno');
const inputOrdenMedica = document.getElementById('inputOrdenMedica');
const calendarContainerEstudio = document.getElementById('calendarContainerEstudio');
const calendarGridEstudio = document.getElementById('calendarGridEstudio');
const calendarTitleEstudio = document.getElementById('calendarTitleEstudio');
const loadingCalendarEstudio = document.getElementById('loadingCalendarEstudio');
const slotsContainerEstudio = document.getElementById('slotsContainerEstudio');
const slotsGridEstudio = document.getElementById('slotsGridEstudio');
const loadingSlotsEstudio = document.getElementById('loadingSlotsEstudio');
const btnSubmitEstudio = document.getElementById('btnSubmitEstudio');
const inputFechaEstudio = document.getElementById('inputFechaEstudio');
const inputHoraEstudio = document.getElementById('inputHoraEstudio');
const inputTecnicoAuto = document.getElementById('inputTecnicoAuto');

let isEditModeEstudio = <?= ($tab === 'turnos_estudios' && $action === 'edit') ? 'true' : 'false' ?>;

// Inicializar para edición de estudios
if (isEditModeEstudio) {
  currentEstudio = '<?= $turnoEstudioEdit['id_estudio'] ?? '' ?>';
  if (currentEstudio) {
    const editDate = '<?= $turnoEstudioEdit['fecha'] ?? '' ?>';
    const editSlot = '<?= substr($turnoEstudioEdit['hora'] ?? '', 0, 5) ?>';
    if (editDate) {
      const dateParts = editDate.split('-');
      currentYearEstudio = parseInt(dateParts[0]);
      currentMonthEstudio = parseInt(dateParts[1]);
      selectedDateEstudio = editDate;
      selectedSlotEstudio = editSlot;
    }
    loadCalendarEstudio();
    if (selectedDateEstudio) {
      setTimeout(() => selectDateEstudio(selectedDateEstudio), 500);
    }
  }
}

// 1. Al seleccionar paciente, cargar sus órdenes médicas
if (selectPacienteEstudio) {
  selectPacienteEstudio.addEventListener('change', async function() {
    const id_paciente = this.value;
    ordenMedicaContainer.style.display = 'none';
    selectOrdenMedica.innerHTML = '<option value="">Cargando...</option>';
    detalleOrden.style.display = 'none';
    selectEstudioTurno.value = '';
    selectEstudioTurno.disabled = true;
    calendarContainerEstudio.style.display = 'none';
    slotsContainerEstudio.style.display = 'none';
    btnSubmitEstudio.disabled = true;
    
    if (!id_paciente) return;
    
    try {
      const response = await fetch(`gestionarTurnos.php?ajax=get_ordenes&id_paciente=${id_paciente}`);
      const data = await response.json();
      
      if (data.error) {
        alert(data.error);
        return;
      }
      
      ordenesDisponibles = data.ordenes || [];
      
      if (ordenesDisponibles.length === 0) {
        selectOrdenMedica.innerHTML = '<option value="">No hay órdenes médicas activas</option>';
        alert('Este paciente no tiene órdenes médicas activas. Debe tener una orden médica para solicitar un turno de estudio.');
        return;
      }
      
      selectOrdenMedica.innerHTML = '<option value="">Seleccionar orden médica...</option>';
      ordenesDisponibles.forEach(orden => {
        const option = document.createElement('option');
        option.value = orden.id_orden;
        const fechaFormat = new Date(orden.fecha_emision).toLocaleDateString('es-AR');
        option.textContent = `${orden.medico} - ${fechaFormat} - ${orden.diagnostico.substring(0, 50)}...`;
        selectOrdenMedica.appendChild(option);
      });
      
      ordenMedicaContainer.style.display = 'block';
      
    } catch (error) {
      console.error('Error:', error);
      alert('Error al cargar órdenes médicas');
    }
  });
}

// 2. Al seleccionar orden médica, mostrar detalles
if (selectOrdenMedica) {
  selectOrdenMedica.addEventListener('change', function() {
    const id_orden = this.value;
    
    if (!id_orden) {
      detalleOrden.style.display = 'none';
      selectEstudioTurno.disabled = true;
      inputOrdenMedica.value = '';
      return;
    }
    
    const orden = ordenesDisponibles.find(o => o.id_orden == id_orden);
    if (!orden) return;
    
    document.getElementById('ordenMedico').textContent = `${orden.medico} (Mat. ${orden.matricula})`;
    document.getElementById('ordenDiagnostico').textContent = orden.diagnostico;
    document.getElementById('ordenEstudios').textContent = orden.estudios;
    document.getElementById('ordenFecha').textContent = new Date(orden.fecha_emision).toLocaleDateString('es-AR');
    detalleOrden.style.display = 'block';
    
    inputOrdenMedica.value = id_orden;
    selectEstudioTurno.disabled = false;
  });
}

// 3. Al seleccionar estudio, cargar calendario
if (selectEstudioTurno) {
  selectEstudioTurno.addEventListener('change', function() {
    currentEstudio = this.value;
    if (currentEstudio) {
      calendarContainerEstudio.style.display = 'block';
      loadCalendarEstudio();
    } else {
      calendarContainerEstudio.style.display = 'none';
      slotsContainerEstudio.style.display = 'none';
    }
    resetSelectionEstudio();
  });
}

document.getElementById('btnPrevMonthEstudio').addEventListener('click', function() {
  currentMonthEstudio--;
  if (currentMonthEstudio < 1) {
    currentMonthEstudio = 12;
    currentYearEstudio--;
  }
  loadCalendarEstudio();
});

document.getElementById('btnNextMonthEstudio').addEventListener('click', function() {
  currentMonthEstudio++;
  if (currentMonthEstudio > 12) {
    currentMonthEstudio = 1;
    currentYearEstudio++;
  }
  loadCalendarEstudio();
});

async function loadCalendarEstudio() {
  if (!currentEstudio) return;
  loadingCalendarEstudio.style.display = 'block';
  calendarGridEstudio.innerHTML = '';
  try {
    const response = await fetch(`gestionarTurnos.php?ajax=get_dates_estudio&id_estudio=${currentEstudio}&mes=${currentMonthEstudio}&anio=${currentYearEstudio}`);
    const data = await response.json();
    if (data.error) {
      alert(data.error);
      return;
    }
    availableDatesEstudio = data.fechas || [];
    renderCalendarEstudio();
  } catch (error) {
    console.error('Error:', error);
    alert('Error al cargar el calendario');
  } finally {
    loadingCalendarEstudio.style.display = 'none';
  }
}

function renderCalendarEstudio() {
  const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  calendarTitleEstudio.textContent = `${monthNames[currentMonthEstudio - 1]} ${currentYearEstudio}`;
  calendarGridEstudio.innerHTML = '';
  const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
  dayNames.forEach(day => {
    const header = document.createElement('div');
    header.style.fontWeight = 'bold';
    header.style.textAlign = 'center';
    header.style.padding = '8px';
    header.textContent = day;
    calendarGridEstudio.appendChild(header);
  });
  const firstDay = new Date(currentYearEstudio, currentMonthEstudio - 1, 1).getDay();
  const daysInMonth = new Date(currentYearEstudio, currentMonthEstudio, 0).getDate();
  for (let i = 0; i < firstDay; i++) {
    calendarGridEstudio.appendChild(document.createElement('div'));
  }
  for (let day = 1; day <= daysInMonth; day++) {
    const dateStr = `${currentYearEstudio}-${String(currentMonthEstudio).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const dayElement = document.createElement('div');
    dayElement.className = 'calendar-day';
    dayElement.textContent = day;
    if (availableDatesEstudio.includes(dateStr)) {
      dayElement.classList.add('available');
      dayElement.addEventListener('click', () => selectDateEstudio(dateStr));
    } else {
      dayElement.classList.add('disabled');
    }
    if (selectedDateEstudio === dateStr) {
      dayElement.classList.add('selected');
    }
    calendarGridEstudio.appendChild(dayElement);
  }
}

async function selectDateEstudio(date) {
  selectedDateEstudio = date;
  selectedSlotEstudio = null;
  renderCalendarEstudio();
  slotsContainerEstudio.style.display = 'block';
  loadingSlotsEstudio.style.display = 'block';
  slotsGridEstudio.innerHTML = '';
  try {
    const response = await fetch(`gestionarTurnos.php?ajax=get_slots_estudio&id_estudio=${currentEstudio}&fecha=${date}`);
    const data = await response.json();
    if (data.error) {
      alert(data.error);
      return;
    }
    const slots = data.slots || [];
    if (slots.length === 0) {
      slotsGridEstudio.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#666">No hay horarios disponibles</p>';
    } else {
      slots.forEach(slot => {
        const slotBtn = document.createElement('div');
        slotBtn.className = 'slot-btn';
        slotBtn.textContent = slot.hora;
        slotBtn.dataset.tecnico = slot.id_tecnico;
        slotBtn.dataset.recurso = slot.id_recurso;
        slotBtn.addEventListener('click', () => selectSlotEstudio(slot.hora, slot.id_tecnico, slot.id_recurso));
        slotsGridEstudio.appendChild(slotBtn);
      });
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error al cargar horarios');
  } finally {
    loadingSlotsEstudio.style.display = 'none';
  }
}

function selectSlotEstudio(hora, id_tecnico, id_recurso) {
  selectedSlotEstudio = hora;
  currentTecnicoAuto = id_tecnico;
  document.querySelectorAll('#slotsGridEstudio .slot-btn').forEach(btn => {
    btn.classList.remove('selected');
    if (btn.textContent === hora) {
      btn.classList.add('selected');
    }
  });
  inputFechaEstudio.value = selectedDateEstudio;
  inputHoraEstudio.value = hora;
  inputTecnicoAuto.value = id_tecnico;
  btnSubmitEstudio.disabled = false;
}

function resetSelectionEstudio() {
  selectedDateEstudio = null;
  selectedSlotEstudio = null;
  currentTecnicoAuto = null;
  slotsContainerEstudio.style.display = 'none';
  btnSubmitEstudio.disabled = true;
  inputFechaEstudio.value = '';
  inputHoraEstudio.value = '';
  inputTecnicoAuto.value = '';
}

<?php endif; ?>
</script>
</body>
</html>


