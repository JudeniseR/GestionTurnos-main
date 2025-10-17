<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if(!isset($_SESSION['id_medico'])){ http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
$id_medico = (int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$id_turno = (int)($_GET['id_turno'] ?? 0);
if($id_turno<=0){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit; }

try{
  $q = $conn->prepare("SELECT observaciones FROM turnos WHERE id_turno=? AND id_medico=?");
  $q->bind_param('ii',$id_turno,$id_medico);
  $q->execute(); $r=$q->get_result()->fetch_assoc();
  if(!$r){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Turno no encontrado']); exit; }
  echo json_encode(['ok'=>true,'notas'=>$r['observaciones'] ?? '']);
}catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
