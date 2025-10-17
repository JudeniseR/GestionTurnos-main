<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode([]); exit; }

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; $offset = ($page - 1) * $limit;

try {
  if ($q === '') { echo json_encode([]); exit; }

  $like = '%'.$conn->real_escape_string($q).'%';
  $sql = "
    SELECT p.id_paciente,
           u.nombre, u.apellido,
           p.nro_documento AS dni,
           p.telefono, p.email
    FROM pacientes p
    LEFT JOIN usuario u ON u.id_usuario = p.id_usuario
    WHERE u.nombre LIKE ? OR u.apellido LIKE ? OR p.nro_documento LIKE ?
    ORDER BY u.apellido, u.nombre
    LIMIT $limit OFFSET $offset";
  $st = $conn->prepare($sql);
  $st->bind_param('sss', $like, $like, $like);
  $st->execute();
  $rs = $st->get_result();

  $out = [];
  while($r = $rs->fetch_assoc()){
    $out[] = [
      'id_paciente' => (int)$r['id_paciente'],
      'nombre_completo' => trim(($r['apellido'] ?? '').', '.($r['nombre'] ?? '')),
      'dni' => $r['dni'] ?? '',
      'telefono' => $r['telefono'] ?? ''
    ];
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
