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
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']); 
  exit; 
}

$id_turno = (int)($_GET['id_turno'] ?? 0);

if ($id_turno <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'ID de turno inválido']);
  exit;
}

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); 
$conn->set_charset('utf8mb4');

try {
  // Primero verificar que el turno pertenece al técnico
  $stmt = $conn->prepare("SELECT id_estudio, id_paciente FROM turnos WHERE id_turno = ? AND id_tecnico = ? LIMIT 1");
  $stmt->bind_param('ii', $id_turno, $id_tecnico);
  $stmt->execute();
  $turno = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$turno) {
    echo json_encode(['ok'=>false,'msg'=>'Turno no encontrado o no autorizado']);
    exit;
  }

  // Buscar la orden médica asociada
  $sql = "
    SELECT 
      o.id_orden,
      o.fecha_emision,
      o.estado,
      o.observaciones,
      o.archivo_orden,
      est.nombre AS estudio,
      CONCAT(um.apellido, ', ', um.nombre) AS medico
    FROM ordenes_estudio o
    LEFT JOIN estudios est ON est.id_estudio = o.id_estudio
    LEFT JOIN medicos m ON m.id_medico = o.id_medico
    LEFT JOIN usuarios um ON um.id_usuario = m.id_usuario
    WHERE o.id_paciente = ? 
      AND o.id_estudio = ?
    ORDER BY o.fecha_emision DESC
    LIMIT 1
  ";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ii', $turno['id_paciente'], $turno['id_estudio']);
  $stmt->execute();
  $orden = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$orden) {
    echo json_encode([
      'ok' => false,
      'msg' => 'No se encontró orden médica para este turno'
    ]);
    exit;
  }

  echo json_encode([
    'ok' => true,
    'orden' => [
      'id_orden'       => (int)$orden['id_orden'],
      'fecha_emision'  => $orden['fecha_emision'],
      'estado'         => $orden['estado'],
      'observaciones'  => $orden['observaciones'] ?? '',
      'archivo_orden'  => $orden['archivo_orden'] ?? '',
      'estudio'        => $orden['estudio'] ?? '',
      'medico'         => $orden['medico'] ?? 'No especificado'
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch(Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'msg' => 'Error al obtener la orden médica: ' . $e->getMessage()
  ]);
}