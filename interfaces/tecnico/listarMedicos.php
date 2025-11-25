<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../Persistencia/conexionBD.php';

if (!isset($_SESSION['id_usuario']) || (int)($_SESSION['rol_id']??0)!==4) {
  http_response_code(401); echo json_encode(['error'=>'NO_SESSION']); exit;
}
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$id_esp = (int)($_POST['especialidad']??0);
$nom    = trim($_POST['nombre_medico']??'');

if($id_esp<=0){ echo json_encode([]); exit; }

$sql = "SELECT m.id_medico, u.nombre, u.apellido
        FROM medicos m
        JOIN usuario u ON u.id_usuario = m.id_usuario
        JOIN medico_especialidad me ON me.id_medico = m.id_medico
        WHERE me.id_especialidad = ?";
$types='i'; $params=[ $id_esp ];

if($nom!==''){
  $sql.=" AND (u.nombre LIKE ? OR u.apellido LIKE ?)";
  $w='%'.$nom.'%'; $types.='ss'; $params[]=$w; $params[]=$w;
}
$sql.=" ORDER BY u.apellido,u.nombre";

$stmt=$conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res=$stmt->get_result();
$out=[];
while($r=$res->fetch_assoc()) $out[]=$r;
echo json_encode($out, JSON_UNESCAPED_UNICODE);
