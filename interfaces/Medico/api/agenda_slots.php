<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode([]); exit; }
$id_medico = (int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$fecha  = $_GET['fecha'] ?? date('Y-m-d');
$debug  = isset($_GET['__debug']) ? 1 : 0;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { echo json_encode([]); exit; }

try {
  // 0) Día bloqueado o feriado -> sin slots
  $bloqDia = 0;
  if ($conn->query("SHOW TABLES LIKE 'agenda_bloqueos'")->num_rows) {
    $q = $conn->prepare("SELECT 1 FROM agenda_bloqueos WHERE id_medico=? AND fecha=? AND tipo='dia' LIMIT 1");
    $q->bind_param('is', $id_medico, $fecha); $q->execute(); $q->store_result();
    $bloqDia = $q->num_rows > 0 ? 1 : 0;
  }
  $feriado = 0;
  if ($conn->query("SHOW TABLES LIKE 'feriados'")->num_rows) {
    $q = $conn->prepare("SELECT 1 FROM feriados WHERE fecha=? LIMIT 1");
    $q->bind_param('s', $fecha); $q->execute(); $q->store_result();
    $feriado = $q->num_rows > 0 ? 1 : 0;
  }
  if ($bloqDia || $feriado) {
    echo $debug ? json_encode(['_debug'=>['id_medico'=>$id_medico,'fecha'=>$fecha,'bloqDia'=>$bloqDia,'feriado'=>$feriado,'ventanas'=>0,'slots'=>0],'data'=>[]]) : json_encode([]);
    exit;
  }

  // 1) Ventanas/franjas del día (sin asumir columna 'disponible' ni tipo exacto de fecha)
  $ventanas = [];
  $q = $conn->prepare("SELECT hora_inicio, hora_fin FROM agenda WHERE id_medico=? AND DATE(fecha)=? ORDER BY hora_inicio");
  $q->bind_param('is', $id_medico, $fecha); $q->execute();
  $rs = $q->get_result();
  while ($r = $rs->fetch_assoc()) {
    $ventanas[] = [$r['hora_inicio'], $r['hora_fin']];
  }

  // 2) Turnos ocupados (no cancelados) -> HH:MM
  $ocup = [];
  if ($conn->query("SHOW TABLES LIKE 'estado'")->num_rows) {
    $q = $conn->prepare("SELECT TIME_FORMAT(t.hora, '%H:%i') hh
                           FROM turnos t
                           JOIN estado e ON e.id_estado=t.id_estado
                          WHERE t.id_medico=? AND DATE(t.fecha)=? AND e.nombre_estado<>'cancelado'");
  } else {
    // sin tabla 'estado', considerar todo lo que no sea estado 4 (si existiera) como ocupado
    $q = $conn->prepare("SELECT TIME_FORMAT(t.hora, '%H:%i') hh
                           FROM turnos t
                          WHERE t.id_medico=? AND DATE(t.fecha)=? AND (t.id_estado IS NULL OR t.id_estado<>4)");
  }
  $q->bind_param('is', $id_medico, $fecha); $q->execute();
  $rs = $q->get_result();
  while ($r = $rs->fetch_assoc()) { $ocup[$r['hh']] = 'Turno asignado'; }

  // 3) Bloqueos puntuales
  $bloq = [];
  if ($conn->query("SHOW TABLES LIKE 'agenda_bloqueos'")->num_rows) {
    $q = $conn->prepare("SELECT TIME_FORMAT(hora, '%H:%i') hh, COALESCE(motivo,'Bloqueado') m
                           FROM agenda_bloqueos
                          WHERE id_medico=? AND fecha=? AND tipo='slot'");
    $q->bind_param('is', $id_medico, $fecha); $q->execute();
    $rs = $q->get_result();
    while ($r = $rs->fetch_assoc()) { $bloq[$r['hh']] = $r['m']; }
  }

  // 4) Generar slots de 30'
  $out = [];
  foreach ($ventanas as $v) {
    $hi = strtotime($fecha.' '.substr($v[0],0,8));
    $hf = strtotime($fecha.' '.substr($v[1],0,8));
    for ($t=$hi; $t<$hf; $t+=30*60) {
      $hh = date('H:i', $t);
      if (isset($ocup[$hh])) {
        $out[] = ['hora'=>$hh, 'estado'=>'ocupado', 'motivo'=>$ocup[$hh]];
      } elseif (isset($bloq[$hh])) {
        $out[] = ['hora'=>$hh, 'estado'=>'ocupado', 'motivo'=>$bloq[$hh]];
      } else {
        $out[] = ['hora'=>$hh, 'estado'=>'disponible'];
      }
    }
  }

  if ($debug) {
    echo json_encode(['_debug'=>[
      'id_medico'=>$id_medico,
      'fecha'=>$fecha,
      'bloqDia'=>$bloqDia,
      'feriado'=>$feriado,
      'ventanas'=>count($ventanas),
      'slots'=>count($out)
    ], 'data'=>$out]);
  } else {
    echo json_encode($out);
  }
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
