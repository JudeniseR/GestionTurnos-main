<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../Persistencia/conexionBD.php';

if (!isset($_SESSION['id_usuario']) || (int)($_SESSION['rol_id']??0)!==4) {
  http_response_code(401); echo json_encode(['error'=>'NO_SESSION']); exit;
}
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$especialidades = [];
$rs = $conn->query("SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad");
while($row = $rs->fetch_assoc()){ $especialidades[] = $row; }

echo json_encode(['especialidades'=>$especialidades], JSON_UNESCAPED_UNICODE);
