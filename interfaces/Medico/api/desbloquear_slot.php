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

$fecha  = $_POST['fecha'] ?? $_GET['fecha'] ?? null;
$hora   = $_POST['hora']  ?? $_GET['hora']  ?? null;

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
  $st = $conn->prepare("DELETE FROM agenda_bloqueos
                         WHERE id_medico=? AND fecha=? AND hora=? AND tipo='slot'");
  $st->bind_param('iss', $id_medico, $fecha, $hora);
  $st->execute();
  $deleted = $st->affected_rows;
  $st->close();

  echo json_encode(['ok'=>true,'deleted'=>$deleted]);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
