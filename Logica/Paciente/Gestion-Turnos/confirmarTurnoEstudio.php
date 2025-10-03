<?php
// confirmarTurnoEstudio.php adaptado

require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesion.php';

header('Content-Type: application/json');

$conn = ConexionBD::conectar();

// Obtener ID del paciente desde la sesión
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

// Buscar un turno disponible en la agenda para ese estudio
$sql = "
    SELECT 
        a.id_agenda,
        a.id_recurso,
        t.id_tecnico
    FROM agenda a
    JOIN recursos r ON a.id_recurso = r.id_recurso
    JOIN tecnicos t ON t.id_recurso = r.id_recurso
    JOIN tecnico_estudio te ON te.id_tecnico = t.id_tecnico
    WHERE 
        te.id_estudio = ?
        AND a.fecha = ?
        AND a.hora_inicio = ?
        AND a.disponible = 1
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $idEstudio, $fecha, $horaInicio);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Turno no disponible.']);
    exit;
}

$data = $result->fetch_assoc();
$idAgenda = $data['id_agenda'];
$idRecurso = $data['id_recurso'];
$idTecnico = $data['id_tecnico'];

// Marcar como no disponible
$update = $conn->prepare("UPDATE agenda SET disponible = 0 WHERE id_agenda = ?");
$update->bind_param("i", $idAgenda);
$update->execute();

// Definir estado "confirmado"
$idEstadoConfirmado = 1;

$insert = $conn->prepare("
    INSERT INTO turnos 
        (id_paciente, id_estudio, id_recurso, fecha, hora, id_estado)
    VALUES 
        (?, ?, ?, ?, ?, ?)
");
$insert->bind_param("iiissi", $idPaciente, $idEstudio, $idRecurso, $fecha, $horaInicio, $idEstadoConfirmado);

if ($insert->execute()) {
    echo json_encode(['success' => true, 'mensaje' => 'Turno confirmado correctamente.']);
} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo confirmar el turno. Error: ' . $insert->error]);
}
?>
