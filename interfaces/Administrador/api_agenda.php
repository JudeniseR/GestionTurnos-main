<?php
// ===== Seguridad mínima =====
$rol_requerido = 3;
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$mode   = $_GET['mode'] ?? null;

function json_body(){
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $d = json_decode($raw, true);
  return is_array($d)?$d:[];
}
function resp($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

// === Tabla de bloqueos (si no existe) ===
$conn->query("
CREATE TABLE IF NOT EXISTS agenda_bloqueos (
  id_bloqueo INT AUTO_INCREMENT PRIMARY KEY,
  id_medico INT NOT NULL,
  fecha DATE NOT NULL,
  hora TIME NULL,
  tipo ENUM('dia','slot') NOT NULL,
  motivo VARCHAR(255) NULL,
  UNIQUE KEY ux_medico_fecha_hora (id_medico, fecha, hora, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Config (ajustar a la clínica)
$HORA_INI   = '09:00';
$HORA_FIN   = '17:00';
$INTERVALO  = 30; // minutos

function gen_slots($fecha, $ini, $fin, $step){
  $out = [];
  $t = new DateTime("$fecha $ini:00");
  $end= new DateTime("$fecha $fin:00");
  while ($t <= $end) {
    $out[] = $t->format('H:i');
    $t->modify("+$step minutes");
  }
  return $out;
}

// === META (estados + pacientes) ===
if ($mode === 'meta') {
  $estados = [];
  $r = $conn->query("SELECT id_estado, nombre_estado FROM estado ORDER BY id_estado");
  while($r && $row=$r->fetch_assoc()){ $estados[]=$row; }

  $pacientes = [];
  $p = $conn->query("SELECT p.id_paciente, CONCAT(u.apellido, ', ', u.nombre) AS nombre
                     FROM pacientes p JOIN usuario u ON u.id_usuario=p.id_usuario
                     ORDER BY u.apellido, u.nombre");
  while($p && $row=$p->fetch_assoc()){ $pacientes[]=$row; }

  resp(['estados'=>$estados, 'pacientes'=>$pacientes]);
}

// === DAYS (GET) ===
if ($mode === 'days' && $method === 'GET') {
  $id_medico = isset($_GET['id_medico']) ? (int)$_GET['id_medico'] : 0;
  $start = $_GET['start'] ?? date('Y-m-01');
  $end   = $_GET['end']   ?? date('Y-m-t');
  if (!$id_medico) resp(['days'=>[]]);

  // turnos no cancelados
  $stmt = $conn->prepare("
    SELECT DATE(t.fecha) AS dia, TIME_FORMAT(t.hora,'%H:%i') AS hora
    FROM turnos t
    JOIN estado e ON e.id_estado = t.id_estado
    WHERE t.id_medico = ? AND DATE(t.fecha) BETWEEN ? AND ? AND e.nombre_estado <> 'cancelado'
  ");
  $stmt->bind_param('iss', $id_medico, $start, $end);
  $stmt->execute();
  $res = $stmt->get_result();
  $busy = [];
  while ($res && $row = $res->fetch_assoc()) {
    $d = $row['dia']; $h = $row['hora'];
    if (!isset($busy[$d])) $busy[$d] = [];
    $busy[$d][$h] = true;
  }
  $stmt->close();

  // bloqueos
  $bloqDia = []; $bloqSlot = [];
  $b = $conn->prepare("SELECT fecha, TIME_FORMAT(hora,'%H:%i') AS hora, tipo FROM agenda_bloqueos WHERE id_medico=? AND fecha BETWEEN ? AND ?");
  $b->bind_param('iss', $id_medico, $start, $end);
  $b->execute(); $br = $b->get_result();
  while($br && $row=$br->fetch_assoc()){
    if ($row['tipo']==='dia'){ $bloqDia[$row['fecha']]=true; }
    else { $bloqSlot[$row['fecha']][$row['hora']]=true; }
  }
  $b->close();

  $out = [];
  $iter = new DateTime($start);
  $limit= new DateTime($end);
  while ($iter <= $limit) {
    $d = $iter->format('Y-m-d');

    if (isset($bloqDia[$d])) {
      $out[] = ['date'=>$d, 'status'=>'closed', 'free'=>0, 'busy'=>0];
      $iter->modify('+1 day'); continue;
    }

    $slots = gen_slots($d, $HORA_INI, $HORA_FIN, $INTERVALO);
    $ocup = isset($busy[$d]) ? $busy[$d] : [];
    $bloq = isset($bloqSlot[$d]) ? $bloqSlot[$d] : [];

    $countBusy = 0;
    foreach ($slots as $h) {
      if (!empty($ocup[$h]) || !empty($bloq[$h])) $countBusy++;
    }
    $free = max(count($slots) - $countBusy, 0);

    $status = 'available';
    if (count($slots) === 0) $status = 'closed';
    elseif ($free === 0)     $status = 'full';

    $out[] = ['date'=>$d, 'status'=>$status, 'free'=>$free, 'busy'=>$countBusy];
    $iter->modify('+1 day');
  }
  resp(['days'=>$out]);
}

// === SLOTS (GET) ===
if ($mode === 'slots' && $method === 'GET') {
  $id_medico = isset($_GET['id_medico']) ? (int)$_GET['id_medico'] : 0;
  $date = $_GET['date'] ?? date('Y-m-d');
  if (!$id_medico) resp([]);

  // bloqueos
  $diaBloq = $conn->prepare("SELECT 1 FROM agenda_bloqueos WHERE id_medico=? AND fecha=? AND tipo='dia' LIMIT 1");
  $diaBloq->bind_param('is', $id_medico, $date);
  $diaBloq->execute();
  $esDiaBloq = $diaBloq->get_result()->num_rows>0;
  $diaBloq->close();
  if ($esDiaBloq){ resp([]); }

  $bloqSlots = [];
  $bs = $conn->prepare("SELECT TIME_FORMAT(hora,'%H:%i') AS hora FROM agenda_bloqueos WHERE id_medico=? AND fecha=? AND tipo='slot'");
  $bs->bind_param('is', $id_medico, $date);
  $bs->execute(); $br = $bs->get_result();
  while ($br && $row=$br->fetch_assoc()){ $bloqSlots[$row['hora']]=true; }
  $bs->close();

  $slots = gen_slots($date, $HORA_INI, $HORA_FIN, $INTERVALO);

  // Turnos del día (todos)
  $stmt = $conn->prepare("
    SELECT t.id_turno, TIME_FORMAT(t.hora,'%H:%i') AS hora, e.nombre_estado,
           CONCAT(up.apellido, ', ', up.nombre) AS paciente,
           CONCAT(um.apellido, ', ', um.nombre) AS medico
    FROM turnos t
    JOIN estado e ON e.id_estado = t.id_estado
    LEFT JOIN pacientes p ON p.id_paciente = t.id_paciente
    LEFT JOIN usuario up ON up.id_usuario = p.id_usuario
    LEFT JOIN medicos m ON m.id_medico = t.id_medico
    LEFT JOIN usuario um ON um.id_usuario = m.id_usuario
    WHERE t.id_medico=? AND DATE(t.fecha)=?
  ");
  $stmt->bind_param('is', $id_medico, $date);
  $stmt->execute();
  $res = $stmt->get_result();
  $busy = [];
  while ($res && $row = $res->fetch_assoc()) {
    $busy[$row['hora']] = [
      'id_turno' => (int)$row['id_turno'],
      'estado'   => $row['nombre_estado'],
      'paciente' => $row['paciente'],
      'medico'   => $row['medico']
    ];
  }
  $stmt->close();

  $out = [];
  foreach ($slots as $h) {
    if (isset($busy[$h])) {
      $out[] = ['time'=>$h, 'busy'=>true] + $busy[$h];
    } elseif (isset($bloqSlots[$h])) {
      $out[] = ['time'=>$h, 'busy'=>true, 'estado'=>'bloqueado', 'paciente'=>null, 'id_turno'=>null];
    } else {
      $out[] = ['time'=>$h, 'busy'=>false];
    }
  }
  resp($out);
}

// ====== POST (ABM) ======
if ($method === 'POST') {
  $b = json_body();
  $mode = $b['mode'] ?? '';

  // UPDATE ESTADO
  if ($mode === 'update_estado') {
    $id_turno  = (int)($b['id_turno'] ?? 0);
    $id_estado = (int)($b['id_estado'] ?? 0);
    if (!$id_turno || !$id_estado) resp(['ok'=>false,'error'=>'Datos incompletos']);
    $stmt = $conn->prepare("UPDATE turnos SET id_estado=? WHERE id_turno=?");
    $stmt->bind_param('ii', $id_estado, $id_turno);
    $ok = $stmt->execute(); $stmt->close();
    resp(['ok'=>$ok]);
  }

  // REASIGNAR PACIENTE
  if ($mode === 'reassign') {
    $id_turno    = (int)($b['id_turno'] ?? 0);
    $id_paciente = (int)($b['id_paciente'] ?? 0);
    if (!$id_turno || !$id_paciente) resp(['ok'=>false,'error'=>'Datos incompletos']);
    $stmt = $conn->prepare("UPDATE turnos SET id_paciente=? WHERE id_turno=?");
    $stmt->bind_param('ii', $id_paciente, $id_turno);
    $ok = $stmt->execute(); $stmt->close();
    resp(['ok'=>$ok]);
  }

  // CANCELAR (set estado cancelado)
  if ($mode === 'cancel') {
    $id_turno = (int)($b['id_turno'] ?? 0);
    if (!$id_turno) resp(['ok'=>false,'error'=>'id_turno requerido']);
    $id_estado_cancel = 0;
    $r = $conn->query("SELECT id_estado FROM estado WHERE nombre_estado='cancelado' LIMIT 1");
    if ($r && $row=$r->fetch_assoc()) $id_estado_cancel=(int)$row['id_estado'];
    if (!$id_estado_cancel) resp(['ok'=>false,'error'=>'No existe estado cancelado']);
    $stmt = $conn->prepare("UPDATE turnos SET id_estado=? WHERE id_turno=?");
    $stmt->bind_param('ii',$id_estado_cancel,$id_turno);
    $ok = $stmt->execute(); $stmt->close();
    resp(['ok'=>$ok]);
  }

  // MOVER turno
  if ($mode === 'move') {
    $id_turno = (int)($b['id_turno'] ?? 0);
    $fecha    = $b['fecha'] ?? null;
    $hora     = $b['hora']  ?? null;
    if (!$id_turno || !$fecha || !$hora) resp(['ok'=>false,'error'=>'Datos incompletos']);

    // obtener médico del turno
    $stmt = $conn->prepare("SELECT id_medico FROM turnos WHERE id_turno=?");
    $stmt->bind_param('i',$id_turno);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res?$res->fetch_assoc():null;
    $stmt->close();
    if (!$row) resp(['ok'=>false,'error'=>'Turno no encontrado']);
    $id_medico = (int)$row['id_medico'];

    // colisión con otro turno no cancelado
    $stmt = $conn->prepare("SELECT 1 FROM turnos t JOIN estado e ON e.id_estado=t.id_estado WHERE t.id_medico=? AND DATE(t.fecha)=? AND TIME_FORMAT(t.hora,'%H:%i')=? AND e.nombre_estado<>'cancelado' AND t.id_turno<>?");
    $stmt->bind_param('issi', $id_medico, $fecha, $hora, $id_turno);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows>0;
    $stmt->close();
    if ($exists) resp(['ok'=>false,'error'=>'Ya hay un turno en ese horario']);

    // colisión con bloqueo de slot
    $stmt = $conn->prepare("SELECT 1 FROM agenda_bloqueos WHERE id_medico=? AND fecha=? AND hora=? AND tipo='slot' LIMIT 1");
    $stmt->bind_param('iss', $id_medico, $fecha, $hora);
    $stmt->execute();
    $bexists = $stmt->get_result()->num_rows>0;
    $stmt->close();
    if ($bexists) resp(['ok'=>false,'error'=>'El horario está bloqueado']);

    $stmt = $conn->prepare("UPDATE turnos SET fecha=?, hora=? WHERE id_turno=?");
    $stmt->bind_param('ssi', $fecha, $hora, $id_turno);
    $ok = $stmt->execute(); $stmt->close();
    resp(['ok'=>$ok]);
  }

  // DELETE turno
  if ($mode === 'delete') {
    $id_turno = (int)($b['id_turno'] ?? 0);
    if (!$id_turno) resp(['ok'=>false,'error'=>'id_turno requerido']);
    $stmt = $conn->prepare("DELETE FROM turnos WHERE id_turno=?");
    $stmt->bind_param('i',$id_turno);
    $ok = $stmt->execute(); $stmt->close();
    resp(['ok'=>$ok]);
  }

  // BLOQUEAR / DESBLOQUEAR DÍA
  if ($mode === 'block_day') {
    $id_medico = (int)($b['id_medico'] ?? 0);
    $fecha     = $b['fecha'] ?? null;
    if (!$id_medico || !$fecha) resp(['ok'=>false,'error'=>'Datos incompletos']);
    $stmt = $conn->prepare("INSERT IGNORE INTO agenda_bloqueos (id_medico,fecha,hora,tipo,motivo) VALUES (?,? ,NULL,'dia','Bloqueo manual')");
    $stmt->bind_param('is', $id_medico, $fecha);
    $ok = $stmt->execute(); $stmt->close();
    resp(['ok'=>$ok]);
  }
  if ($mode === 'unblock_day') {
    $id_medico = (int)($b['id_medico'] ?? 0);
    $fecha     = $b['fecha'] ?? null;
    if (!$id_medico || !$fecha) resp(['ok'=>false,'error'=>'Datos incompletos']);
    $stmt = $conn->prepare("DELETE FROM agenda_bloqueos WHERE id_medico=? AND fecha=? AND tipo='dia'");
    $stmt->bind_param('is', $id_medico, $fecha);
    $ok = $stmt->execute(); $stmt->close();
    resp(['ok'=>$ok]);
  }

  // BLOQUEAR / DESBLOQUEAR SLOT
  if ($mode === 'block_slot') {
    $id_medico = (int)($b['id_medico'] ?? 0);
    $fecha     = $b['fecha'] ?? null;
    $hora      = $b['hora']  ?? null;
    if (!$id_medico || !$fecha || !$hora) resp(['ok'=>false,'error'=>'Datos incompletos']);

    // no permitir bloquear si ya hay un turno activo en ese horario (salvo cancelado)
    $stmt = $conn->prepare("SELECT 1 FROM turnos t JOIN estado e ON e.id_estado=t.id_estado WHERE t.id_medico=? AND DATE(t.fecha)=? AND TIME_FORMAT(t.hora,'%H:%i')=? AND e.nombre_estado<>'cancelado' LIMIT 1");
    $stmt->bind_param('iss', $id_medico, $fecha, $hora);
    $stmt->execute(); $exists = $stmt->get_result()->num_rows>0; $stmt->close();
    if ($exists) resp(['ok'=>false,'error'=>'Ya hay un turno activo en ese horario']);

    $stmt = $conn->prepare("INSERT IGNORE INTO agenda_bloqueos (id_medico,fecha,hora,tipo,motivo) VALUES (?,?,?,'slot','Bloqueo manual')");
    $stmt->bind_param('iss', $id_medico, $fecha, $hora);
    $ok = $stmt->execute(); $stmt->close();
    resp(['ok'=>$ok]);
  }
  if ($mode === 'unblock_slot') {
    $id_medico = (int)($b['id_medico'] ?? 0);
    $fecha     = $b['fecha'] ?? null;
    $hora      = $b['hora']  ?? null;
    if (!$id_medico || !$fecha || !$hora) resp(['ok'=>false,'error'=>'Datos incompletos']);
    $stmt = $conn->prepare("DELETE FROM agenda_bloqueos WHERE id_medico=? AND fecha=? AND hora=? AND tipo='slot'");
    $stmt->bind_param('iss', $id_medico, $fecha, $hora);
    $ok = $stmt->execute(); $stmt->close();
    resp(['ok'=>$ok]);
  }

  resp(['ok'=>false,'error'=>'Acción no reconocida']);
}

// Fallback
resp([]);
