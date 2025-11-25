<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../Persistencia/conexionBD.php';

if (!isset($_SESSION['id_usuario']) || (int)($_SESSION['rol_id']??0)!==4) {
  http_response_code(401); echo json_encode(['success'=>false,'error'=>'NO_SESSION']); exit;
}
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$turno_id=(int)($_POST['turno_id']??0);
if($turno_id<=0){ echo json_encode(['success'=>false,'error'=>'Turno inválido']); exit; }

try{
  $conn->begin_transaction();

  $stmt=$conn->prepare("SELECT id_medico, fecha, hora FROM turnos WHERE id_turno=? FOR UPDATE");
  $stmt->bind_param('i',$turno_id); $stmt->execute();
  $t=$stmt->get_result()->fetch_assoc();
  if(!$t) throw new Exception('No existe el turno');

  // Regla 48h
  $ahora=new DateTime('now'); $fh=new DateTime($t['fecha'].' '.$t['hora']);
  $diff=$ahora->diff($fh); $horas=($diff->days*24)+$diff->h+($diff->i/60);
  if($horas<48) throw new Exception('Sólo se cancela con 48 horas de anticipación');

  $ID_CANCELADO=4;
  $stmt=$conn->prepare("UPDATE turnos SET id_estado=? WHERE id_turno=?");
  $stmt->bind_param('ii',$ID_CANCELADO,$turno_id);
  if(!$stmt->execute()) throw new Exception('No se pudo cancelar');

  $stmt=$conn->prepare("UPDATE agenda SET disponible=1 WHERE id_medico=? AND fecha=? AND hora_inicio=?");
  $stmt->bind_param('iss',$t['id_medico'],$t['fecha'],$t['hora']);
  if(!$stmt->execute()) throw new Exception('No se pudo liberar el horario');

  $conn->commit();
  echo json_encode(['success'=>true]);
}catch(Throwable $e){
  $conn->rollback();
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
