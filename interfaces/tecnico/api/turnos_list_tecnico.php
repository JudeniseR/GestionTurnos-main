<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');
session_start();

$id_tecnico = $_SESSION['id_tecnico'] ?? null;
if (!$id_tecnico && isset($_SESSION['id_usuario'])) {
  require_once('../../../Persistencia/conexionBD.php');
  $conn = ConexionBD::conectar();
  $stmt = $conn->prepare("SELECT id_tecnico FROM tecnicos WHERE id_usuario = ? LIMIT 1");
  $stmt->bind_param('i', $_SESSION['id_usuario']);
  $stmt->execute();
  $stmt->bind_result($id_tecnico);
  $stmt->fetch();
  $stmt->close();
  if ($id_tecnico) $_SESSION['id_tecnico'] = $id_tecnico;
}

if(!$id_tecnico){ 
  http_response_code(401); 
  echo json_encode(['ok'=>false,'items'=>[]]); 
  exit; 
}

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); 
$conn->set_charset('utf8mb4');

$estado = $_GET['estado'] ?? '';
$desde  = $_GET['desde']  ?? '';
$hasta  = $_GET['hasta']  ?? '';
$q      = trim($_GET['q'] ?? '');

try {
  $sql = "
    SELECT 
      t.id_turno,
      DATE(t.fecha) AS fecha,
      TIME_FORMAT(t.hora, '%H:%i:%s') AS hora,
      t.id_estado,
      t.id_orden_medica,
      t.observaciones,
      p.id_paciente,
      p.nro_documento AS dni,
      CONCAT(u.apellido, ', ', u.nombre) AS paciente,
      est.nombre AS estudio,
      CASE t.id_estado
        WHEN 1 THEN 'pendiente'
        WHEN 2 THEN 'confirmado'
        WHEN 3 THEN 'atendido'
        WHEN 4 THEN 'cancelado'
        WHEN 5 THEN 'reprogramado'
        ELSE 'pendiente'
      END AS nombre_estado
    FROM turnos t
    LEFT JOIN pacientes p   ON p.id_paciente = t.id_paciente
    LEFT JOIN usuarios u    ON u.id_usuario = p.id_usuario
    LEFT JOIN estudios est  ON est.id_estudio = t.id_estudio
    WHERE t.id_tecnico = ?
  ";

  $types = 'i';
  $params = [$id_tecnico];

  // filtros
  if ($estado !== '') {
  if ($estado === 'vencido') {
    // Vencidos: confirmados o reprogramados con más de 24h pasadas
    $sql .= " AND t.id_estado IN (2,5) 
              AND TIMESTAMPDIFF(HOUR, CONCAT(DATE(t.fecha), ' ', TIME(t.hora)), NOW()) >= 24";
  } else {
    $estadoMap = [
      'pendiente'    => 1,
      'confirmado'   => 2,
      'atendido'     => 3,
      'cancelado'    => 4,
      'reprogramado' => 5
    ];
    if (isset($estadoMap[$estado])) {
      $sql .= " AND t.id_estado = ?";
      $types .= 'i';
      $params[] = $estadoMap[$estado];
    }
  }
}

  if ($desde !== '') {
    $sql .= " AND DATE(t.fecha) >= ?";
    $types .= 's';
    $params[] = $desde;
  }

  if ($hasta !== '') {
    $sql .= " AND DATE(t.fecha) <= ?";
    $types .= 's';
    $params[] = $hasta;
  }

  if ($q !== '') {
    $sql .= " AND (
      u.nombre LIKE ? OR 
      u.apellido LIKE ? OR 
      p.nro_documento LIKE ? OR
      est.nombre LIKE ?
    )";
    $searchTerm = '%' . $q . '%';
    $types .= 'ssss';
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
  }

  $sql .= " ORDER BY DATE(t.fecha) DESC, TIME(t.hora) DESC";

  $stmt = $conn->prepare($sql);
  if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
  }
  
  $stmt->execute();
  $result = $stmt->get_result();

  $items = [];
  while ($row = $result->fetch_assoc()) {
    $items[] = [
  'id_turno'          => (int)$row['id_turno'],
  'fecha'             => $row['fecha'],
  'hora'              => $row['hora'],
  'id_estado'         => (int)$row['id_estado'],
  'estado'            => $row['nombre_estado'],
  'paciente_nombre'   => $row['paciente'] ?? 'Sin datos',   
  'paciente_dni'      => $row['dni'] ?? '',                  
  'id_paciente'       => (int)$row['id_paciente'],
  'estudio_nombre'    => $row['estudio'] ?? '',              
  'observaciones'     => $row['observaciones'] ?? '',
  'tiene_orden_medica'=> !empty($row['id_orden_medica']) ? 1 : 0
];

  }

  echo json_encode([
    'ok' => true,
    'items' => $items,
    'total' => count($items)
  ], JSON_UNESCAPED_UNICODE);

} catch(Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'items' => [],
    'error' => $e->getMessage()
  ]);
}
