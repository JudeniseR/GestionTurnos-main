<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../Persistencia/conexionBD.php';

if (!isset($_SESSION['id_usuario']) || (int)($_SESSION['rol_id']??0)!==4) {
  http_response_code(401); echo json_encode(['success'=>false,'error'=>'NO_SESSION']); exit;
}
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$turno_id   = (int)($_POST['turno_id']??0);
$id_medicoN = (int)($_POST['id_medico']??0);
$fechaN     = $_POST['fecha']??'';
$horaN      = $_POST['hora_inicio']??'';

if($turno_id<=0||$id_medicoN<=0||!$fechaN||!$horaN){
  echo json_encode(['success'=>false,'error'=>'Datos incompletos']); exit;
}

try{
  $conn->begin_transaction();

  $stmt=$conn->prepare("SELECT id_medico, fecha, hora FROM turnos WHERE id_turno=? FOR UPDATE");
  $stmt->bind_param('i',$turno_id); $stmt->execute();
  $old=$stmt->get_result()->fetch_assoc();
  if(!$old) throw new Exception('Turno no encontrado');

  // 48 horas
  $ahora=new DateTime('now'); $fh=new DateTime($old['fecha'].' '.$old['hora']);
  $diff=$ahora->diff($fh); $horas=($diff->days*24)+$diff->h+($diff->i/60);
  if($horas<48) throw new Exception('Sólo reprogramable con 48 horas de anticipación');

  // Nuevo slot debe estar libre
  $stmt=$conn->prepare("SELECT id_recurso FROM agenda
                        WHERE id_medico=? AND fecha=? AND hora_inicio=? AND disponible=1
                        FOR UPDATE");
  $stmt->bind_param('iss',$id_medicoN,$fechaN,$horaN);
  $stmt->execute(); $slot=$stmt->get_result()->fetch_assoc();
  if(!$slot) throw new Exception('El nuevo horario ya no está disponible');

  // Libero viejo slot
  $stmt=$conn->prepare("UPDATE agenda SET disponible=1 WHERE id_medico=? AND fecha=? AND hora_inicio=?");
  $stmt->bind_param('iss',$old['id_medico'],$old['fecha'],$old['hora']);
  if(!$stmt->execute()) throw new Exception('No se pudo liberar el horario anterior');

  // Ocupo nuevo slot
  $stmt=$conn->prepare("UPDATE agenda SET disponible=0 WHERE id_medico=? AND fecha=? AND hora_inicio=?");
  $stmt->bind_param('iss',$id_medicoN,$fechaN,$horaN);
  if(!$stmt->execute()) throw new Exception('No se pudo ocupar el nuevo horario');

  // Actualizo turno
  $stmt=$conn->prepare("UPDATE turnos SET id_medico=?, fecha=?, hora=? WHERE id_turno=?");
  $stmt->bind_param('issi',$id_medicoN,$fechaN,$horaN,$turno_id);
  if(!$stmt->execute()) throw new Exception('No se pudo reprogramar');

  $conn->commit();
  echo json_encode(['success'=>true]);
}catch(Throwable $e){
  $conn->rollback();
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
