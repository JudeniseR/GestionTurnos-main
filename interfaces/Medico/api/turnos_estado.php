<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');
session_start();
if(!isset($_SESSION['id_medico'])){ http_response_code(401); echo json_encode(['ok'=>false]); exit; }
$id_medico=(int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn=ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$id_turno=(int)($_POST['id_turno'] ?? 0);
$estado_txt=trim(strtolower((string)($_POST['estado'] ?? '')));
$motivo = trim((string)($_POST['motivo'] ?? ''));

try{
  if($id_turno<=0 || $estado_txt===''){ http_response_code(400); echo json_encode(['ok'=>false]); exit; }

  // mapa estado
  $rs=$conn->query("SELECT id_estado FROM estados WHERE LOWER(nombre_estado)='".$conn->real_escape_string($estado_txt)."' LIMIT 1");
  if(!$rs || !$rs->num_rows){ http_response_code(404); echo json_encode(['ok'=>false]); exit; }
  $id_estado=(int)$rs->fetch_assoc()['id_estado'];

  // turno + paciente
  $q=$conn->prepare("SELECT id_paciente FROM turnos WHERE id_turno=? AND id_medico=? LIMIT 1");
  $q->bind_param('ii',$id_turno,$id_medico); $q->execute();
  $row=$q->get_result()->fetch_assoc();
  if(!$row){ http_response_code(404); echo json_encode(['ok'=>false]); exit; }
  $id_paciente=(int)$row['id_paciente'];

  // update
  $st=$conn->prepare("UPDATE turnos SET id_estado=? WHERE id_turno=? AND id_medico=?");
  $st->bind_param('iii',$id_estado,$id_turno,$id_medico);
  $st->execute();

  // Si es cancelado y hay motivo -> dejar constancia
  if($estado_txt==='cancelado' && $motivo!==''){
    // observaciones (id_turno, id_paciente, fecha, nota)
    $nota = 'CANCELADO: '.$motivo;
    $h=$conn->prepare("INSERT INTO observaciones (id_turno,id_paciente,fecha,nota) VALUES (?,?,CURDATE(),?)");
    $h->bind_param('iis',$id_turno,$id_paciente,$nota);
    $h->execute();

    // notificaciones (id_turno,id_paciente,mensaje,estado)
    $msg = "Su turno fue cancelado. Motivo: ".$motivo;
    $n=$conn->prepare("INSERT INTO notificaciones (id_turno,id_paciente,mensaje,estado) VALUES (?,?,?,'pendiente')");
    $n->bind_param('iis',$id_turno,$id_paciente,$msg);
    $n->execute();
  }

  echo json_encode(['ok'=>true]);
}catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false]); }
