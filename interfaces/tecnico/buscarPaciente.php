<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../Persistencia/conexionBD.php';

if (!isset($_SESSION['id_usuario']) || (int)($_SESSION['rol_id']??0)!==4) {
  http_response_code(401); echo json_encode(['error'=>'NO_SESSION']); exit;
}
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$dni   = trim($_POST['dni'] ?? '');
$email = trim($_POST['email'] ?? '');

$sql = "SELECT p.id_paciente, p.nro_documento, u.nombre, u.apellido, u.email
        FROM pacientes p
        JOIN usuario u ON u.id_usuario = p.id_usuario
        WHERE 1=1";
$types=''; $params=[];
if($dni!==''){  $sql.=" AND p.nro_documento=?"; $types.='s'; $params[]=$dni; }
if($email!==''){ $sql.=" AND u.email=?";        $types.='s'; $params[]=$email; }
$sql.=" ORDER BY u.apellido,u.nombre LIMIT 1";

$stmt=$conn->prepare($sql);
if($types!=='') $stmt->bind_param($types, ...$params);
$stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
echo json_encode($row? array_merge($row,['found'=>true]) : ['found'=>false], JSON_UNESCAPED_UNICODE);
