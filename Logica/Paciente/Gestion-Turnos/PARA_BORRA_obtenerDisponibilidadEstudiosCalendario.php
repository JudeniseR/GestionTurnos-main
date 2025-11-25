<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once '../../../Persistencia/conexionBD.php';
$conn = ConexionBD::conectar();

$idEstudio = $_POST['id_estudio'] ?? $_GET['id_estudio'] ?? null;

if (!$idEstudio || !is_numeric($idEstudio)) {
    echo json_encode(['error' => 'ID de estudio inválido', 'recibido' => $idEstudio]);
    exit;
}

// Fechas desde hoy hasta 30 días
$fechaInicio = new DateTime();
$fechaFin = (clone $fechaInicio)->modify('+30 days');

$intervalo = new DateInterval('P1D');
$periodo = new DatePeriod($fechaInicio, $intervalo, $fechaFin);

$eventos = [];

// Procesar cada día dentro del período
foreach ($periodo as $fecha) {
    $fechaStr = $fecha->format('Y-m-d');

    // Obtener horarios directamente desde la agenda por estudio
    $sql = "
        SELECT hora_inicio
        FROM agenda
        WHERE id_estudio = ? AND disponible = 1 AND fecha = ?
        ORDER BY hora_inicio
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $idEstudio, $fechaStr);
    $stmt->execute();
    $result = $stmt->get_result();

    $horarios = [];
    while ($row = $result->fetch_assoc()) {
        $horarios[] = substr($row['hora_inicio'], 0, 5); // recortado a hh:mm
    }

    // Comprobamos si hay disponibilidad para el día
    $disponible = count($horarios) > 0;

    // Solo almacenamos el evento si hay horarios disponibles
    if ($disponible) {
        $eventos[] = [
            'start' => $fechaStr,
            'color' => 'green', // Día con disponibilidad (verde)
            'allDay' => true,
            'extendedProps' => [
                'horarios' => $horarios
            ]
        ];
    } else {
        // Si no hay disponibilidad, también lo marcamos (rojo)
        $eventos[] = [
            'start' => $fechaStr,
            'color' => 'red', // Día sin disponibilidad (rojo)
            'allDay' => true
        ];
    }
}

echo json_encode($eventos);
