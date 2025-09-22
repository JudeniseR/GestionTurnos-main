<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once '../../../Persistencia/conexionBD.php';
require_once '../../General/envioNotificacion.php';

session_start();

$conn = ConexionBD::conectar();

$paciente_id = $_SESSION['id_paciente_token'] ?? null;

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

// 1. Verificar si el turno está disponible en agenda
$sqlCheckAgenda = "
    SELECT id_agenda
    FROM agenda
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
$agendaId = $rowAgenda['id_agenda'];

// 2. Obtener ID del estado "confirmado"
$sqlEstado = "SELECT id_estado FROM estado WHERE nombre_estado = 'confirmado' LIMIT 1";
$resEstado = $conn->query($sqlEstado);

if ($resEstado->num_rows === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'Estado "confirmado" no configurado en la base de datos.']);
    exit;
}

$rowEstado = $resEstado->fetch_assoc();
$id_estado = $rowEstado['id_estado'];

// 3. Insertar en turnos
$sqlInsertTurno = "
    INSERT INTO turnos (
        id_paciente, id_medico, id_estado, id_estudio,
        fecha, hora, copago, observaciones
    ) VALUES (?, ?, ?, NULL, ?, ?, 0.00, '')
";

$stmt2 = $conn->prepare($sqlInsertTurno);
$stmt2->bind_param("iiiss", $paciente_id, $id_medico, $id_estado, $fecha, $hora_inicio);

if (!$stmt2->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar el turno: ' . $conn->error]);
    exit;
}

$turnoId = $conn->insert_id;

// 4. Marcar el turno en agenda como no disponible
$sqlUpdateAgenda = "UPDATE agenda SET disponible = FALSE WHERE id_agenda = ?";
$stmt3 = $conn->prepare($sqlUpdateAgenda);
$stmt3->bind_param("i", $agendaId);
$stmt3->execute();

// 5. Enviar notificación
enviarNotificacionTurno($conn, $turnoId);

// 6. Responder limpio (sin basura previa)
ob_clean(); // limpia cualquier salida previa
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'mensaje' => '✅ Turno confirmado correctamente. Se envió un email con los detalles.'
]);
exit;

?>
