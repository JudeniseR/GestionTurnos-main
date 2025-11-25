<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if(!isset($_SESSION['id_medico'])){
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$id_medico_sesion = (int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

function norm_hora(?string $s): ?string {
  if ($s===null) return null;
  $s = trim(str_replace('.',':',$s));
  if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/',$s)) return null;
  [$h,$m,$sec] = array_pad(explode(':',$s),3,'00');
  $h = str_pad((string)intval($h),2,'0',STR_PAD_LEFT);
  return "$h:$m:".(strlen($sec)?$sec:'00');
}

$id_paciente     = (int)($_POST['id_paciente'] ?? 0);
$fecha           = $_POST['fecha'] ?? '';
$hora            = norm_hora($_POST['hora'] ?? '');
$obs             = trim((string)($_POST['observaciones'] ?? ''));
$id_medico_dest  = (int)($_POST['id_medico_destino'] ?? 0);
$id_medico       = $id_medico_dest > 0 ? $id_medico_dest : $id_medico_sesion;

if(!$id_paciente || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha) || !$hora){
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']);
  exit;
}

try{
  // Paciente existe
  $st = $conn->prepare("SELECT 1 FROM pacientes WHERE id_paciente=?");
  $st->bind_param('i', $id_paciente);
  $st->execute();
  if(!$st->get_result()->fetch_row()){
    http_response_code(404);
    echo json_encode(['ok'=>false,'msg'=>'Paciente inexistente']);
    exit;
  }
  $st->close();

  // Estado pendiente
  $id_estado_pend = null;
  $rs = $conn->query("SELECT id_estado FROM estados WHERE nombre_estado='pendiente' LIMIT 1");
  if($rs && ($row=$rs->fetch_assoc())) $id_estado_pend = (int)$row['id_estado'];
  if(!$id_estado_pend){
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Estado pendiente no configurado']);
    exit;
  }

  // Colisión (excepto cancelado)
  $q = $conn->prepare("
    SELECT 1
    FROM turnos t
    WHERE t.id_medico=? AND t.fecha=? AND t.hora=? AND t.id_estado <> (
      SELECT id_estado FROM estados WHERE nombre_estado='cancelado' LIMIT 1
    ) LIMIT 1
  ");
  $q->bind_param('iss', $id_medico, $fecha, $hora);
  $q->execute();
  if($q->get_result()->fetch_row()){
    http_response_code(409);
    echo json_encode(['ok'=>false,'msg'=>'Horario ocupado']);
    exit;
  }
  $q->close();

  // Alta
  $st = $conn->prepare("INSERT INTO turnos (id_paciente, id_medico, id_estado, fecha, hora, observaciones) VALUES (?,?,?,?,?,?)");
  $st->bind_param('iiisss', $id_paciente, $id_medico, $id_estado_pend, $fecha, $hora, $obs);
  $st->execute();
  $id_new = $st->insert_id;
  $st->close();

  echo json_encode(['ok'=>true,'id_turno'=>$id_new], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
