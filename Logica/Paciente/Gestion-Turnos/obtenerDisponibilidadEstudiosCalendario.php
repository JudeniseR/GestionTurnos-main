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

foreach ($periodo as $fecha) {
    $fechaStr = $fecha->format('Y-m-d');

    // Obtener horarios directamente desde agenda por estudio
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
        $horarios[] = substr($row['hora_inicio'], 0, 5); // recortado hh:mm
    }

    $disponible = count($horarios) > 0;

    $eventos[] = [
        'title' => $disponible ? 'Disponible' : 'Sin disponibilidad',
        'start' => $fechaStr,
        'color' => $disponible ? 'green' : 'red',
        'allDay' => true,
        'extendedProps' => [
            'horarios' => $horarios
        ]
    ];
}

echo json_encode($eventos);


