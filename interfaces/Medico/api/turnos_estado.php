<?php
// interfaces/Medico/api/turnos_estado.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

require_once('../../Persistencia/conexionBD.php');

try{
  $conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

  $id_medico = (int)$_SESSION['id_medico'];
  $id_turno  = (int)($_POST['id_turno'] ?? 0);
  $estado    = trim($_POST['estado'] ?? '');

  if(!$id_turno || !$estado){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit; }

  $st = $conn->prepare("SELECT id_estado FROM estado WHERE nombre_estado=? LIMIT 1");
  $st->bind_param('s', $estado); $st->execute(); $st->bind_result($id_estado);
  if(!$st->fetch()){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Estado inválido']); exit; }
  $st->close();

  $st = $conn->prepare("UPDATE turnos SET id_estado=? WHERE id_turno=? AND id_medico=?");
  $st->bind_param('iii', $id_estado, $id_turno, $id_medico);
  if(!$st->execute()){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$st->error]); exit; }
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
