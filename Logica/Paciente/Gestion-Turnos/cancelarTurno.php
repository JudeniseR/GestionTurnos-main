<?php
// Mostrar errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesionPaciente.php';

$conn = ConexionBD::conectar();

// Validar sesión
$paciente_id = $_SESSION['paciente_id'] ?? null;
if (!$paciente_id) {
    die("Debe iniciar sesión.");
}

// Validar entrada
if (!isset($_POST['turno_id'])) {
    die("ID de turno no recibido.");
}

$turno_id = intval($_POST['turno_id']);

// Verificar que el turno pertenece al paciente y obtener info
$sql_info = "SELECT recurso_id, fecha, hora FROM turnos WHERE id = ? AND paciente_id = ?";
$stmt = $conn->prepare($sql_info);
$stmt->bind_param("ii", $turno_id, $paciente_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("El turno no existe o no pertenece al paciente.");
}

$turno = $result->fetch_assoc();
$recurso_id = $turno['recurso_id'];
$fecha = $turno['fecha'];
$hora = $turno['hora'];

// Cancelar el turno
$sql_cancelar = "UPDATE turnos SET estado = 'cancelado' WHERE id = ?";
$stmt = $conn->prepare($sql_cancelar);
$stmt->bind_param("i", $turno_id);
$okTurno = $stmt->execute();

// Liberar el slot en la agenda
$sql_liberar = "UPDATE agenda SET disponible = TRUE 
                WHERE recurso_id = ? AND fecha = ? AND hora_inicio = ?";
$stmt = $conn->prepare($sql_liberar);
$stmt->bind_param("iss", $recurso_id, $fecha, $hora);
$okAgenda = $stmt->execute();

if ($okTurno && $okAgenda) {
    header("Location: ../../../interfaces/Paciente/Gestion/misTurnos.php?cancelado=1");
    exit;
} else {
    echo "Error al cancelar y/o liberar el turno.";
}
?>
