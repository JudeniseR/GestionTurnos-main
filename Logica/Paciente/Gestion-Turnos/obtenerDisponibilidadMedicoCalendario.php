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

$fechaInicio = new DateTime();
$fechaFin = (clone $fechaInicio)->modify('+30 days');

$intervalo = new DateInterval('P1D');
$periodo = new DatePeriod($fechaInicio, $intervalo, $fechaFin);

$eventos = [];

foreach ($periodo as $fecha) {
    $fechaStr = $fecha->format('Y-m-d');

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

    $horarios = [];
    while ($row = $result->fetch_assoc()) {
        $horarios[] = substr($row['hora_inicio'], 0, 5); // formato hh:mm
    }

    $disponible = count($horarios) > 0;

    $eventos[] = [
        'title' => $disponible ? 'Disponible' : 'Sin disponibilidad',
        'start' => $fechaStr,
        'color' => $disponible ? 'green' : 'red',  // Azul para médico, por ejemplo
        'allDay' => true,
        'extendedProps' => [
            'horarios' => $horarios
        ]
    ];
}

echo json_encode($eventos);
