<?php
// Crea una franja (rango) de agenda
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$id_medico  = (int)$_SESSION['id_medico'];
$fecha      = $_POST['fecha'] ?? null;
$hora_ini   = $_POST['hora_inicio'] ?? null;
$hora_fin   = $_POST['hora_fin'] ?? null;

if(!$fecha || !$hora_ini || !$hora_fin){
  http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Faltan datos']); exit;
}
if($hora_ini >= $hora_fin){
  http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Hora fin debe ser mayor a inicio']); exit;
}

// No solapar con otras franjas del mismo día (se permite adyacente)
$solapa = false;
$sqlChk = "SELECT 1
           FROM agenda
           WHERE id_medico=? AND fecha=? AND disponible=1
             AND NOT (hora_fin <= ? OR hora_inicio >= ?)
           LIMIT 1";
$st = $conn->prepare($sqlChk);
$st->bind_param('isss', $id_medico, $fecha, $hora_ini, $hora_fin);
$st->execute(); $st->store_result();
$solapa = $st->num_rows > 0;
$st->close();

if($solapa){
  http_response_code(409); echo json_encode(['ok'=>false,'msg'=>'Se superpone con otra franja']); exit;
}

$st = $conn->prepare("INSERT INTO agenda (id_medico, id_recurso, fecha, hora_inicio, hora_fin, disponible) VALUES (?, 1, ?, ?, ?, 1)");
$st->bind_param('isss', $id_medico, $fecha, $hora_ini, $hora_fin);
if(!$st->execute()){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'No se pudo crear']); exit;
}
$st->close();

echo json_encode(['ok'=>true]);
