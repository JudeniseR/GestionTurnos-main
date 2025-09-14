<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([]);
    exit;
}

$conn = ConexionBD::conectar();

$tipoEstudio = $_POST['tipoEstudio'] ?? '';
$sede = $_POST['sede'] ?? '';

$sql = "
    SELECT DISTINCT 
        e.id AS id_estudio, 
        e.nombre AS nombre_estudio, 
        e.instrucciones_preparacion AS descripcion
    FROM estudios e
    JOIN agenda_estudios ae ON ae.estudio_id = e.id
    JOIN recursos r ON ae.recurso_id = r.id
    WHERE ae.disponible = 1
";

$params = [];
$types = "";

// Filtro por tipo de estudio
if (!empty($tipoEstudio)) {
    $sql .= " AND e.tipo_estudio_id = ?";
    $params[] = $tipoEstudio;
    $types .= "i";
}

// Filtro por sede (a través del recurso)
if (!empty($sede)) {
    $sql .= " AND r.sede_id = ?";
    $params[] = $sede;
    $types .= "i";
}

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['error' => "Error preparando consulta: " . $conn->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$estudios = [];
while ($row = $result->fetch_assoc()) {
    $estudios[] = $row;
}

echo json_encode($estudios);
exit;
