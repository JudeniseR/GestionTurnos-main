<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

require_once('../../../Persistencia/conexionBD.php'); // <-- RUTA CORRECTA
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$id_medico = (int)$_SESSION['id_medico'];
$fecha     = $_POST['fecha']  ?? null;
$motivo    = trim($_POST['motivo'] ?? '');

if(!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)){
  http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Fecha inválida']); exit;
}

// Evitar feriado
$st = $conn->prepare("SELECT 1 FROM feriados WHERE fecha=? LIMIT 1");
$st->bind_param('s', $fecha); $st->execute(); $st->store_result();
if($st->num_rows>0){ $st->close(); http_response_code(409); echo json_encode(['ok'=>false,'msg'=>'Es feriado']); exit; }
$st->close();

// Insertar bloqueo de día (idempotente simple)
$st = $conn->prepare("INSERT INTO agenda_bloqueos (id_medico, fecha, tipo, motivo) VALUES (?, ?, 'dia', ?) ON DUPLICATE KEY UPDATE motivo=VALUES(motivo)");
$st->bind_param('iss', $id_medico, $fecha, $motivo);
$ok = $st->execute();
$st->close();

echo json_encode(['ok'=>$ok ? true : false]);
