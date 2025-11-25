<?php
// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Conexión a la base de datos
require_once '../../../Persistencia/conexionBD.php';
$conn = ConexionBD::conectar();

// Obtener filtros del formulario
$sede = $_POST['sede'] ?? null;
$tipoEstudio = $_POST['estudio'] ?? null; // tu formulario usa "estudio", no "tipoEstudio"

// ================================================================
// 🧩 CASO 1: SIN FILTROS (mostrar solo estudios con disponibilidad)
// ================================================================
if (empty($sede) && empty($tipoEstudio)) {
    $sql = "
        SELECT DISTINCT
            e.id_estudio,
            e.nombre AS nombre_estudio,
            s.nombre AS sede
        FROM estudios e
        JOIN tecnico_estudio te ON te.id_estudio = e.id_estudio
        JOIN tecnicos t ON t.id_tecnico = te.id_tecnico
        JOIN recursos r ON r.id_recurso = t.id_recurso AND r.tipo = 'tecnico'
        JOIN sedes s ON s.id_sede = r.id_sede
        JOIN agenda a ON a.id_recurso = r.id_recurso AND a.disponible = 1
        ORDER BY e.nombre ASC
    ";

    $result = $conn->query($sql);
    $estudios = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $estudios[] = [
                'id_estudio' => $row['id_estudio'],
                'nombre' => $row['nombre_estudio'],
                'sede' => $row['sede']
            ];
        }
    }

    echo json_encode($estudios, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $conn->close();
    exit;
}

// ================================================================
// 🧩 CASO 2: CON FILTROS (sede y/o estudio específicos) - SIN CAMBIOS
// ================================================================
$sql = "
    SELECT 
        e.id_estudio,
        e.nombre AS nombre_estudio,
        e.instrucciones,
        e.requiere_preparacion,
        s.id_sede,
        s.nombre AS sede,
        GROUP_CONCAT(DISTINCT u.nombre SEPARATOR ', ') AS tecnicos
    FROM estudios e
    JOIN tecnico_estudio te ON te.id_estudio = e.id_estudio
    JOIN tecnicos t ON t.id_tecnico = te.id_tecnico
    JOIN usuarios u ON u.id_usuario = t.id_usuario
    JOIN recursos r ON r.id_recurso = t.id_recurso AND r.tipo = 'tecnico'
    JOIN sedes s ON s.id_sede = r.id_sede
    JOIN agenda a ON a.id_recurso = r.id_recurso AND a.disponible = 1
    WHERE 1=1
";

$params = [];
$types = "";

// Filtro por tipo de estudio
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

$sql .= "
    GROUP BY e.id_estudio, s.id_sede
    ORDER BY e.nombre
";

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

// Construir respuesta JSON
$estudios = [];
while ($row = $result->fetch_assoc()) {
    $estudios[] = [
        'id_estudio' => $row['id_estudio'],
        'nombre' => $row['nombre_estudio'],
        'instrucciones' => $row['instrucciones'],
        'requiere_preparacion' => $row['requiere_preparacion'],
        'sede' => $row['sede'],
        'tecnicos' => $row['tecnicos']
    ];
}

// Devolver respuesta
echo json_encode($estudios, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$conn->close();
?>