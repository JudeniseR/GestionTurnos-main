<?php
// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Conexión a la base de datos
require_once '../../../Persistencia/conexionBD.php';
$conn = ConexionBD::conectar();

// Obtener filtros
$sede = $_POST['sede'] ?? null;
$tipoEstudio = $_POST['tipoEstudio'] ?? null;

// Consulta base (AJUSTADA)
$sql = "
    SELECT DISTINCT 
        e.id_estudio,
        e.nombre AS nombre_estudio,
        e.instrucciones AS descripcion,
        s.nombre AS nombre_sede
    FROM estudios e
    JOIN tecnico_estudio te ON te.id_estudio = e.id_estudio
    JOIN tecnicos t ON t.id_tecnico = te.id_tecnico
    JOIN recursos r ON r.id_recurso = t.id_recurso AND r.tipo = 'tecnico'  -- ✅ Relación correcta
    JOIN sedes s ON s.id_sede = r.id_sede
    JOIN agenda a ON a.id_recurso = r.id_recurso AND a.disponible = 1
    WHERE 1=1
";

$params = [];
$types = "";

// Filtro por tipo de estudio (id_estudio)
if (!empty($tipoEstudio)) {
    $sql .= " AND e.id_estudio = ?";
    $params[] = $tipoEstudio;
    $types .= "i";
}

// Filtro por sede
if (!empty($sede)) {
    $sql .= " AND s.id_sede = ?";
    $params[] = $sede;
    $types .= "i";
}

// Preparar y ejecutar
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['error' => 'Error en la preparación: ' . $conn->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Construir respuesta
$estudios = [];
while ($row = $result->fetch_assoc()) {
    $estudios[] = $row;
}

// Devolver en JSON
echo json_encode($estudios);
