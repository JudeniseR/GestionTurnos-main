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

$idSede = $_POST['sede'] ?? '';

$sql = "
    SELECT DISTINCT 
        e.id_estudio,
        e.nombre AS nombre_estudio,
        e.instrucciones
    FROM estudios e
    WHERE EXISTS (
        SELECT 1
        FROM agenda a
        JOIN recursos r ON r.id_recurso = a.id_recurso
        WHERE a.disponible = 1
          AND r.tipo IN ('tecnico', 'equipo')
";

// Si se especificó una sede, la agregamos al filtro
$params = [];
$types = "";

if (!empty($idSede)) {
    $sql .= " AND r.id_sede = ?";
    $params[] = $idSede;
    $types .= "i";
}

$sql .= "
    )
";

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
