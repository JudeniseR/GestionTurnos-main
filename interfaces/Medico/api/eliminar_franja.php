<?php
// Elimina una franja por id_agenda
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$id_medico  = (int)$_SESSION['id_medico'];
$id_agenda  = (int)($_POST['id_agenda'] ?? 0);

if($id_agenda<=0){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'id_agenda requerido']); exit; }

// (Opcional) asegurarse que la franja pertenece al médico
$st = $conn->prepare("DELETE FROM agenda WHERE id_agenda=? AND id_medico=? LIMIT 1");
$st->bind_param('ii', $id_agenda, $id_medico);
$ok = $st->execute();
$st->close();

if(!$ok){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'No se pudo eliminar']); exit; }

echo json_encode(['ok'=>true]);
