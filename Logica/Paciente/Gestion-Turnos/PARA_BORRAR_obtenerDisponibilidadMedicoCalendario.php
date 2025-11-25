<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once '../../../Persistencia/conexionBD.php';
$conn = ConexionBD::conectar();

$idMedico = $_POST['id_medico'] ?? $_GET['id_medico'] ?? null;

if (!$idMedico || !is_numeric($idMedico)) {
    echo json_encode(['error' => 'ID de médico inválido', 'recibido' => $idMedico]);
    exit;
}

// Definimos el rango de fechas para la disponibilidad
$fechaInicio = new DateTime();
$fechaFin = (clone $fechaInicio)->modify('+30 days'); // Se pueden ajustar los días según sea necesario

$intervalo = new DateInterval('P1D');
$periodo = new DatePeriod($fechaInicio, $intervalo, $fechaFin);

$eventos = [];

foreach ($periodo as $fecha) {
    $fechaStr = $fecha->format('Y-m-d');

    // Consulta los horarios disponibles para ese médico en la fecha
    $sql = "
        SELECT hora_inicio
        FROM agenda
        WHERE id_medico = ? AND disponible = 1 AND fecha = ?
        ORDER BY hora_inicio
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $idMedico, $fechaStr);
    $stmt->execute();
    $result = $stmt->get_result();

    // Arreglo para almacenar los horarios disponibles
    $horarios = [];
    while ($row = $result->fetch_assoc()) {
        $horarios[] = substr($row['hora_inicio'], 0, 5); // Formato hh:mm
    }

    // Si hay horarios disponibles, la fecha es "disponible"
    $disponible = count($horarios) > 0;

    // Se agrega el evento para el calendario
    $eventos[] = [
        'title' => $disponible ? 'Disponible' : 'Sin disponibilidad',
        'start' => $fechaStr,
        'color' => $disponible ? 'green' : 'red',  // Verde si hay disponibilidad, rojo si no
        'allDay' => true,
        'extendedProps' => [
            'horarios' => $horarios
        ]
    ];
}

// Si no hubo eventos, enviar un mensaje de error
if (empty($eventos)) {
    echo json_encode(['error' => 'No se encontraron eventos para el médico especificado.']);
} else {
    echo json_encode($eventos);
}

?>
