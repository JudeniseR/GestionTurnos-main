<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if(!isset($_SESSION['id_medico'])){ http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
$med_origen = (int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$id_turno_origen = (int)($_POST['id_turno'] ?? 0);
$med_dest        = (int)($_POST['id_medico_dest'] ?? 0);
$fecha           = $_POST['fecha'] ?? '';
$hora            = $_POST['hora'] ?? '';

if($id_turno_origen<=0 || $med_dest<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha) || !preg_match('/^\d{2}:\d{2}$/',$hora)){
  http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit;
}

try{
  // obtenemos paciente del turno original
  $q=$conn->prepare("SELECT id_paciente FROM turnos WHERE id_turno=? AND id_medico=?");
  $q->bind_param('ii',$id_turno_origen,$med_origen); $q->execute();
  $row=$q->get_result()->fetch_assoc();
  if(!$row){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Turno origen no encontrado']); exit; }
  $id_pac=(int)$row['id_paciente'];

  // estado por defecto (pendiente)
  $estado = $conn->query("SELECT id_estado FROM estado WHERE nombre_estado='pendiente' LIMIT 1")->fetch_assoc();
  $id_estado = (int)($estado['id_estado'] ?? 0);

  $ins = $conn->prepare("INSERT INTO turnos (id_paciente,id_medico,fecha,hora,id_estado,observaciones) VALUES (?,?,?,?,?,?)");
  $obs = 'Derivado desde turno #'.$id_turno_origen;
  $h2 = $hora.':00';
  $ins->bind_param('iissis',$id_pac,$med_dest,$fecha,$h2,$id_estado,$obs);
  $ins->execute();

  // anota en el turno original
  $conn->query("UPDATE turnos SET observaciones=CONCAT(COALESCE(observaciones,''),' | Derivado a médico $med_dest ($fecha $hora)') WHERE id_turno=$id_turno_origen AND id_medico=$med_origen");

  echo json_encode(['ok'=>true,'nuevo_turno'=>$conn->insert_id]);
}catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
