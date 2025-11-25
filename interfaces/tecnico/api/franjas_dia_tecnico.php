<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');

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

if (!$id_tecnico) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { echo json_encode([]); exit; }

try {
  $sql = "SELECT id_agenda, hora_inicio, hora_fin
            FROM agenda
           WHERE id_tecnico=? AND DATE(fecha)=?
        ORDER BY hora_inicio";
  $st = $conn->prepare($sql);
  $st->bind_param('is', $id_tecnico, $fecha);
  $st->execute();
  $rs = $st->get_result();

  $out = [];
  while ($r = $rs->fetch_assoc()) {
    $id = (int)$r['id_agenda'];
    $out[] = [
      'id_agenda'   => $id,
      'id'          => $id,
      'id_franja'   => $id,
      'franja_id'   => $id,
      'hora_inicio' => substr((string)$r['hora_inicio'], 0, 5),
      'hora_fin'    => substr((string)$r['hora_fin'], 0, 5),
    ];
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}