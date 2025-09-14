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
    SELECT m.id_medico, m.nombre, m.apellido, s.nombre AS sede,
           GROUP_CONCAT(e.nombre_especialidad) AS especialidades
    FROM medicos m
    JOIN medico_especialidad me ON me.id_medico = m.id_medico
    JOIN especialidades e ON e.id_especialidad = me.id_especialidad
    JOIN agenda_medica a ON a.id_medico = m.id_medico
    JOIN sedes s ON s.id = a.sede_id
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
    $sql .= " AND s.id = ?";
    $params[] = $sede;
    $types .= 'i';
}
if ($nombre) {
    $sql .= " AND CONCAT(m.nombre, ' ', m.apellido) LIKE ?";
    $params[] = "%$nombre%";
    $types .= 's';
}

$sql .= " GROUP BY m.id_medico";

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
