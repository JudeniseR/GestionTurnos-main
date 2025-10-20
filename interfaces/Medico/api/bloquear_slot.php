<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if (!isset($_SESSION['id_medico'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}
$id_medico = (int)$_SESSION['id_medico'];

$fecha  = $_POST['fecha']  ?? null;
$hora   = $_POST['hora']   ?? null;
$motivo = trim((string)($_POST['motivo'] ?? ''));

function norm_h(?string $s): ?string {
  if ($s === null) return null;
  $s = trim(str_replace('.', ':', $s));
  if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) return null;
  [$h,$m,$sec] = array_pad(explode(':', $s), 3, '00');
  $h = str_pad((string)intval($h), 2, '0', STR_PAD_LEFT);
  return "$h:$m:".(strlen($sec)?$sec:'00');
}

if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Fecha inválida']); exit;
}
$hora = norm_h($hora);
if (!$hora) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Hora inválida']); exit;
}

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

try{
  // Evitar duplicados
  $q = $conn->prepare("SELECT id_bloqueo FROM agenda_bloqueos 
                        WHERE id_medico=? AND fecha=? AND hora=? AND tipo='slot' LIMIT 1");
  $q->bind_param('iss', $id_medico, $fecha, $hora);
  $q->execute();
  $row = $q->get_result()->fetch_assoc();
  $q->close();

  if ($row) {
    if ($motivo !== '') {
      $up = $conn->prepare("UPDATE agenda_bloqueos SET motivo=? WHERE id_bloqueo=?");
      $up->bind_param('si', $motivo, $row['id_bloqueo']);
      $up->execute(); $up->close();
    }
    echo json_encode(['ok'=>true,'id_bloqueo'=>(int)$row['id_bloqueo'],'updated'=>($motivo!==''?1:0)]);
    exit;
  }

  $st = $conn->prepare("INSERT INTO agenda_bloqueos (id_medico, fecha, hora, tipo, motivo)
                        VALUES (?, ?, ?, 'slot', ?)");
  $st->bind_param('isss', $id_medico, $fecha, $hora, $motivo);
  $st->execute();
  $id = $st->insert_id;
  $st->close();

  echo json_encode(['ok'=>true,'id_bloqueo'=>$id]);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
