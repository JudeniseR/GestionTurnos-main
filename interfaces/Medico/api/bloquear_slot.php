<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

require_once('../../../Persistencia/conexionBD.php'); // <-- RUTA CORRECTA
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$id_medico = (int)$_SESSION['id_medico'];
$fecha     = $_POST['fecha'] ?? null;
$hora      = $_POST['hora']  ?? null;
$motivo    = trim($_POST['motivo'] ?? '');

if(!$fecha || !$hora || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !preg_match('/^\d{2}:\d{2}$/', $hora)){
  http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit;
}

// ¿Ya hay turno (no cancelado)?
$sql = "SELECT 1
        FROM turnos t
        JOIN estado e ON e.id_estado=t.id_estado
        WHERE t.id_medico=? AND t.fecha=? AND t.hora LIKE CONCAT(?,':%')
          AND e.nombre_estado <> 'cancelado' LIMIT 1";
$st = $conn->prepare($sql);
$st->bind_param('iss', $id_medico, $fecha, $hora);
$st->execute(); $st->store_result();
if($st->num_rows>0){ $st->close(); http_response_code(409); echo json_encode(['ok'=>false,'msg'=>'Hay un turno en ese horario']); exit; }
$st->close();

// Insertar bloqueo puntual (idempotente simple)
$st = $conn->prepare("INSERT INTO agenda_bloqueos (id_medico, fecha, tipo, hora, motivo)
                      VALUES (?, ?, 'slot', ?, ?)
                      ON DUPLICATE KEY UPDATE motivo=VALUES(motivo)");
$st->bind_param('isss', $id_medico, $fecha, $hora, $motivo);
$ok = $st->execute();
$st->close();

echo json_encode(['ok'=>$ok ? true : false]);
