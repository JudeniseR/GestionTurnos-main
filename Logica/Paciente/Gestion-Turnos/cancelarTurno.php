<?php
// Mostrar errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesion.php';

$conn = ConexionBD::conectar();

// Validar sesión
$paciente_id = $_SESSION['id_paciente_token'] ?? null;
if (!$paciente_id) {
    die("Debe iniciar sesión.");
}

// Validar entrada
if (!isset($_POST['turno_id'])) {
    die("ID de turno no recibido.");
}

$turno_id = intval($_POST['turno_id']);

// Verificar que el turno existe, pertenece al paciente y obtener información
$sql_info = "SELECT id_turno, id_medico, id_estudio, id_recurso, fecha, hora 
             FROM turnos 
             WHERE id_turno = ? AND id_paciente = ?";
$stmt = $conn->prepare($sql_info);
$stmt->bind_param("ii", $turno_id, $paciente_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("El turno no existe o no pertenece al paciente.");
}

$turno = $result->fetch_assoc();
$id_recurso = $turno['id_recurso'];
$fecha = $turno['fecha'];
$hora = $turno['hora'];
$id_estudio = $turno['id_estudio'];
$id_medico = $turno['id_medico'];

// Cancelar el turno (id_estado = 4)
$sql_cancelar = "UPDATE turnos SET id_estado = 4 WHERE id_turno = ?";
$stmt = $conn->prepare($sql_cancelar);
$stmt->bind_param("i", $turno_id);
$okTurno = $stmt->execute();

// Liberar el turno en la tabla agenda (para médico o técnico)
$sql_liberar = "UPDATE agenda 
                SET disponible = 1 
                WHERE id_recurso = ? AND fecha = ? AND hora_inicio = ?";
$stmt = $conn->prepare($sql_liberar);
$stmt->bind_param("iss", $id_recurso, $fecha, $hora);
$okAgenda = $stmt->execute();

// Redirigir dependiendo del tipo de turno cancelado
if ($okTurno && $okAgenda) {
    if (!empty($id_estudio)) {
        // Turno de estudio
        header("Location: ../../../interfaces/Paciente/Gestion/misTurnos.php?cancelado=1");
    } else {
        // Turno médico
        header("Location: ../../../interfaces/Paciente/Gestion/misTurnos.php?cancelado=1");
    }
    exit;
} else {
    echo "Error al cancelar y/o liberar el turno.";
}
?>
