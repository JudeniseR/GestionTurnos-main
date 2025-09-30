<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../Persistencia/conexionBD.php';

if (!isset($_SESSION['id_usuario']) || (int)($_SESSION['rol_id']??0)!==4) {
  http_response_code(401); echo json_encode([]); exit;
}
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$id_paciente=(int)($_POST['id_paciente']??0);
if($id_paciente<=0){ echo json_encode([]); exit; }

$stmt=$conn->prepare("
  SELECT t.id_turno, t.fecha, t.hora,
         CONCAT(mu.apellido, ', ', mu.nombre) AS medico,
         e.nombre_estado AS estado
  FROM turnos t
  JOIN medicos m   ON m.id_medico = t.id_medico
  JOIN usuario mu  ON mu.id_usuario = m.id_usuario
  JOIN estado e    ON e.id_estado = t.id_estado
  WHERE t.id_paciente = ?
  ORDER BY t.fecha DESC, t.hora DESC
");
$stmt->bind_param('i',$id_paciente);
$stmt->execute();
$res=$stmt->get_result();
$out=[];
while($r=$res->fetch_assoc()) $out[]=$r;
echo json_encode($out, JSON_UNESCAPED_UNICODE);
