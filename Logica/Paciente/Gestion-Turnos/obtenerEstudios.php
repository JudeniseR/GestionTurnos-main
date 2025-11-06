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
$tipoEstudio = $_POST['estudio'] ?? null; // 👈 tu formulario usa "estudio", no "tipoEstudio"

// Si NO hay filtros (como en el caso de órdenes médicas), devolver TODOS los estudios simples
if (empty($sede) && empty($tipoEstudio)) {
    $sql = "SELECT id_estudio, nombre AS nombre_estudio FROM estudios ORDER BY nombre ASC";
    $result = $conn->query($sql);
    
    $estudios = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $estudios[] = [
                'id_estudio' => $row['id_estudio'],
                'nombre' => $row['nombre_estudio']  // Mantengo 'nombre' para compatibilidad con JS
            ];
        }
    }
    
    echo json_encode($estudios, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $conn->close();
    exit; // Salir aquí para no ejecutar el query complejo
}

// Si HAY filtros, usar el query original (para compatibilidad con otras funcionalidades)
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

// Construir respuesta
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

// Devolver JSON
echo json_encode($estudios, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$conn->close();
?>