<?php

// MOSTRAR ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once '../../../Persistencia/conexionBD.php';
$conn = ConexionBD::conectar();

$especialidad = $_POST['especialidad'] ?? null;
$sede = $_POST['sede'] ?? null;
$nombre = $_POST['nombre_medico'] ?? null;

$sql = "
    SELECT 
        m.id_medico, 
        u.nombre, 
        u.apellido, 
        s.nombre AS sede,
        GROUP_CONCAT(DISTINCT e.nombre_especialidad) AS especialidades
    FROM medicos m
    JOIN usuarios u ON m.id_usuario = u.id_usuario
    JOIN medico_especialidad me ON me.id_medico = m.id_medico
    JOIN especialidades e ON e.id_especialidad = me.id_especialidad
    JOIN agenda a ON a.id_medico = m.id_medico
    JOIN recursos r ON r.id_recurso = a.id_recurso
    JOIN sedes s ON s.id_sede = r.id_sede
    WHERE 1=1
";

$params = [];
$types = '';

if ($especialidad) {
    $sql .= " AND e.id_especialidad = ?";
    $params[] = $especialidad;
    $types .= 'i';
}
if ($sede) {
    $sql .= " AND s.id_sede = ?";
    $params[] = $sede;
    $types .= 'i';
}
if ($nombre) {
    $sql .= " AND CONCAT(u.nombre, ' ', u.apellido) LIKE ?";
    $params[] = "%$nombre%";
    $types .= 's';
}

$sql .= " GROUP BY m.id_medico, u.nombre, u.apellido, s.nombre";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$medicos = [];

while ($row = $result->fetch_assoc()) {
    $row['especialidades'] = explode(',', $row['especialidades']);
    $medicos[] = $row;
}

echo json_encode($medicos);
