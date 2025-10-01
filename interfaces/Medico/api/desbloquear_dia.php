<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

require_once('../../../Persistencia/conexionBD.php'); // <-- RUTA CORRECTA
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$id_medico = (int)$_SESSION['id_medico'];
$fecha     = $_POST['fecha']  ?? null;

if(!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)){
  http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Fecha inválida']); exit;
}

$st = $conn->prepare("DELETE FROM agenda_bloqueos WHERE id_medico=? AND fecha=? AND tipo='dia' LIMIT 1");
$st->bind_param('is', $id_medico, $fecha);
$st->execute();
$aff = $st->affected_rows;
$st->close();

echo json_encode(['ok'=>true, 'removed'=>$aff]);
