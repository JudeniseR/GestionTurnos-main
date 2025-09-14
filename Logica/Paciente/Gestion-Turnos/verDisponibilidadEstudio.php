<?php
require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesion.php';

header('Content-Type: application/json');

$conn = ConexionBD::conectar();

$idEstudio = $_POST['id_estudio'] ?? '';

if (empty($idEstudio)) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT fecha, hora_inicio, hora_fin
        FROM agenda_estudios
        WHERE estudio_id = ? AND disponible = 1
        ORDER BY fecha, hora_inicio";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idEstudio);
$stmt->execute();
$result = $stmt->get_result();

$disponibilidad = [];

while ($row = $result->fetch_assoc()) {
    $fecha = $row['fecha'];
    if (!isset($disponibilidad[$fecha])) {
        $disponibilidad[$fecha] = [];
    }

    $disponibilidad[$fecha][] = [
        'inicio' => $row['hora_inicio'],
        'fin' => $row['hora_fin'],
    ];
}

echo json_encode($disponibilidad);
?>
