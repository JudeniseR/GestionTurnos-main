<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json'); // MUY IMPORTANTE

require_once '../../../Persistencia/conexionBD.php';

try {
    $conn = ConexionBD::conectar();

    $estudioId = $_POST['estudioId'] ?? null;
    $sedeId = $_POST['sedeId'] ?? null;
    $fecha = $_POST['fecha'] ?? null;

    if (!$estudioId || !$sedeId || !$fecha) {
        echo json_encode(['error' => 'Faltan datos']);
        exit;
    }

    $sql = "
        SELECT a.hora_inicio, a.hora_fin 
        FROM agenda a
        JOIN recursos r ON a.recurso_id = r.id
        WHERE a.estudio_id = ? AND r.sede_id = ? AND a.fecha = ? AND a.disponible = TRUE
        ORDER BY a.hora_inicio
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['error' => 'Falló la preparación de la consulta: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("iis", $estudioId, $sedeId, $fecha); // i i s
    $stmt->execute();
    $result = $stmt->get_result();

    $horarios = [];
    while ($row = $result->fetch_assoc()) {
        $horarios[] = $row;
    }

    echo json_encode($horarios);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
