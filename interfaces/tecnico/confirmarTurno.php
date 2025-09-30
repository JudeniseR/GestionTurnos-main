<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../Persistencia/conexionBD.php';

if (!isset($_SESSION['id_usuario']) || (int)($_SESSION['rol_id']??0)!==4) {
  http_response_code(401); echo json_encode(['success'=>false,'error'=>'NO_SESSION']); exit;
}
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$id_paciente=(int)($_POST['id_paciente']??0);
$id_medico=(int)($_POST['id_medico']??0);
$fecha=$_POST['fecha']??'';
$hora_ini=$_POST['hora_inicio']??'';

if($id_paciente<=0||$id_medico<=0||!$fecha||!$hora_ini){
  echo json_encode(['success'=>false,'error'=>'Datos incompletos']); exit;
}

try{
  $conn->begin_transaction();

  // Bloqueo slot libre
  $stmt=$conn->prepare("SELECT id_recurso FROM agenda
                        WHERE id_medico=? AND fecha=? AND hora_inicio=? AND disponible=1
                        FOR UPDATE");
  $stmt->bind_param('iss',$id_medico,$fecha,$hora_ini);
  $stmt->execute(); $slot=$stmt->get_result()->fetch_assoc();
  if(!$slot) throw new Exception('El horario ya no está disponible');

  $ID_PENDIENTE = 1; // ver tabla estado
  $stmt=$conn->prepare("INSERT INTO turnos (id_paciente,id_medico,id_estado,fecha,hora)
                        VALUES (?,?,?,?,?)");
  $stmt->bind_param('iiiss',$id_paciente,$id_medico,$ID_PENDIENTE,$fecha,$hora_ini);
  if(!$stmt->execute()) throw new Exception('No se pudo crear el turno');

  $stmt=$conn->prepare("UPDATE agenda SET disponible=0 WHERE id_medico=? AND fecha=? AND hora_inicio=?");
  $stmt->bind_param('iss',$id_medico,$fecha,$hora_ini);
  if(!$stmt->execute()) throw new Exception('No se pudo marcar ocupado');

  $conn->commit();
  echo json_encode(['success'=>true,'mensaje'=>'Turno asignado']);
}catch(Throwable $e){
  $conn->rollback();
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
