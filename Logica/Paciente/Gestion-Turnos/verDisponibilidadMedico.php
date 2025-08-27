<?php
// MOSTRAR ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json'); // 👈 Importante: siempre antes de echo JSON

require_once '../../../Persistencia/conexionBD.php';

$conn = ConexionBD::conectar();

$id_medico = $_POST['id_medico'] ?? null;

if (!$id_medico) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta ID de médico']);
    exit;
}

// Verificar si el médico existe (opcional)
$checkStmt = $conn->prepare("SELECT id_medico FROM medicos WHERE id_medico = ?");
$checkStmt->bind_param("i", $id_medico);
$checkStmt->execute();
$result = $checkStmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Médico no encontrado']);
    exit;
}

// Obtener horarios disponibles futuros
$sql = "
    SELECT fecha, hora_inicio, hora_fin
    FROM agenda_medica
    WHERE id_medico = ?
      AND fecha >= CURDATE()
      AND disponible = 1
    ORDER BY fecha, hora_inicio
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_medico);
$stmt->execute();
$result = $stmt->get_result();

$horarios = [];

while ($row = $result->fetch_assoc()) {
    $fecha = $row['fecha'];
    if (!isset($horarios[$fecha])) {
        $horarios[$fecha] = [];
    }
    $horarios[$fecha][] = [
        'inicio' => $row['hora_inicio'],
        'fin' => $row['hora_fin']
    ];
}

echo json_encode($horarios);
