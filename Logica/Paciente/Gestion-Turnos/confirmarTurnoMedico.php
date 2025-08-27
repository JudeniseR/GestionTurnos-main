<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once '../../../Persistencia/conexionBD.php';
require_once '../../General/envioNotificacion.php';

session_start();

$conn = ConexionBD::conectar();

$paciente_id = $_SESSION['paciente_id'] ?? null;
if (!$paciente_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Debe estar logueado para confirmar un turno.']);
    exit;
}

$id_medico = $_POST['id_medico'] ?? null;
$fecha = $_POST['fecha'] ?? null;
$hora_inicio = $_POST['hora_inicio'] ?? null;

if (!$id_medico || !$fecha || !$hora_inicio) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos para confirmar el turno.']);
    exit;
}

// 1. Validar disponibilidad en agenda_medica
$sqlCheckAgenda = "
    SELECT id
    FROM agenda_medica
    WHERE id_medico = ? AND fecha = ? AND hora_inicio = ? AND disponible = TRUE
    LIMIT 1
";
$stmt = $conn->prepare($sqlCheckAgenda);
$stmt->bind_param("iss", $id_medico, $fecha, $hora_inicio);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(409);
    echo json_encode(['error' => 'El turno seleccionado ya no está disponible.']);
    exit;
}

$rowAgenda = $res->fetch_assoc();
$agendaId = $rowAgenda['id'];

// 2. Insertar turno en tabla turnos
$sqlInsertTurno = "
    INSERT INTO turnos (paciente_id, medico_id, estudio_id, recurso_id, fecha, hora, estado, copago, observaciones, orden_estudio_id)
    VALUES (?, ?, NULL, NULL, ?, ?, 'confirmado', 0, '', NULL)
";

$stmt2 = $conn->prepare($sqlInsertTurno);
$stmt2->bind_param("iiss", $paciente_id, $id_medico, $fecha, $hora_inicio);

if (!$stmt2->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar el turno: ' . $conn->error]);
    exit;
}

$turnoId = $conn->insert_id;

// 3. Actualizar agenda_medica para marcar turno como ocupado
$sqlUpdateAgenda = "UPDATE agenda_medica SET disponible = FALSE WHERE id = ?";
$stmt3 = $conn->prepare($sqlUpdateAgenda);
$stmt3->bind_param("i", $agendaId);
$stmt3->execute();

// 4. Enviar notificación
enviarNotificacionTurno($conn, $turnoId);

echo json_encode([
    'success' => true,
    'mensaje' => '✅ Turno confirmado correctamente. Se envió un email con los detalles.'
]);


?>
