<?php
require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesion.php';

header('Content-Type: application/json');

// Conexión a la base de datos
$conn = ConexionBD::conectar();

// Obtener ID del paciente desde la sesión (el ID de la tabla 'pacientes')
$idPaciente = $_SESSION['id_paciente_token'] ?? null;

// Obtener datos del POST
$fecha = $_POST['fecha'] ?? '';
$horaInicio = $_POST['hora_inicio'] ?? '';
$idEstudio = $_POST['id_estudio'] ?? '';

// Validar que todos los datos estén presentes
if (!$idPaciente || !$fecha || !$horaInicio || !$idEstudio) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos o sesión inválida.']);
    exit;
}

// Verificar si el turno está disponible en la agenda_estudios
$sql = "SELECT id 
        FROM agenda_estudios 
        WHERE estudio_id = ? AND fecha = ? AND hora_inicio = ? AND disponible = 1
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $idEstudio, $fecha, $horaInicio);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Turno no disponible.']);
    exit;
}

$turno = $result->fetch_assoc();
$idAgenda = $turno['id'];

// Actualizar disponibilidad en agenda_estudios
$update = $conn->prepare("UPDATE agenda_estudios SET disponible = 0 WHERE id = ?");
$update->bind_param("i", $idAgenda);
$update->execute();

// Insertar el turno confirmado en la tabla turnos
$insert = $conn->prepare("INSERT INTO turnos (paciente_id, estudio_id, recurso_id, fecha, hora, estado) VALUES (?, ?, NULL, ?, ?, 'confirmado')");
$insert->bind_param("iiss", $idPaciente, $idEstudio, $fecha, $horaInicio);

if ($insert->execute()) {
    echo json_encode(['success' => true, 'mensaje' => 'Turno confirmado correctamente.']);
} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo confirmar el turno.']);
}
?>
