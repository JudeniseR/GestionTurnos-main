<?php
// MOSTRAR ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once '../../../Persistencia/conexionBD.php';

$conn = ConexionBD::conectar();

$id_estudio = $_POST['id_estudio'] ?? null;

if (!$id_estudio || !is_numeric($id_estudio)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de estudio inválido o faltante']);
    exit;
}

// Validar que exista el estudio
$checkStmt = $conn->prepare("SELECT id_estudio FROM estudios WHERE id_estudio = ?");
$checkStmt->bind_param("i", $id_estudio);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Estudio no encontrado']);
    exit;
}
$checkStmt->close();

// Obtener disponibilidad de técnicos que hacen ese estudio
$sql = "
    SELECT a.fecha, a.hora_inicio, a.hora_fin
    FROM agenda a
    JOIN recursos r ON a.id_recurso = r.id_recurso
    JOIN tecnicos t ON t.id_recurso = r.id_recurso  -- ✅ Relación correcta
    JOIN tecnico_estudio te ON t.id_tecnico = te.id_tecnico
    WHERE te.id_estudio = ?
      AND a.disponible = 1
      AND a.fecha >= CURDATE()
    ORDER BY a.fecha ASC, a.hora_inicio ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_estudio);
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
        'fin' => $row['hora_fin']
    ];
}

echo json_encode($disponibilidad);
