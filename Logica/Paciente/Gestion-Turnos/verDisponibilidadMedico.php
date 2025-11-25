<?php
// MOSTRAR ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once '../../../Persistencia/conexionBD.php';

$conn = ConexionBD::conectar();

$id_medico = $_POST['id_medico'] ?? null;

if (!$id_medico || !is_numeric($id_medico)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de médico inválido o faltante']);
    exit;
}

// Verificar existencia del médico
$checkStmt = $conn->prepare("SELECT id_medico FROM medicos WHERE id_medico = ?");
$checkStmt->bind_param("i", $id_medico);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Médico no encontrado']);
    exit;
}
$checkStmt->close();

// Usar nueva tabla `agenda` para obtener turnos disponibles
$sql = "
    SELECT fecha, hora_inicio, hora_fin
    FROM agenda
    WHERE id_medico = ?
      AND fecha >= CURDATE()
      AND disponible = 1
    ORDER BY fecha ASC, hora_inicio ASC
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
    'inicio' => substr($row['hora_inicio'], 0, 5) // Solo HH:MM
    // Omitir 'fin' si no se necesita
];
}

echo json_encode($horarios);
