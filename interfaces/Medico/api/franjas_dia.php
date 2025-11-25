<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');

session_start();
if (!isset($_SESSION['id_medico'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$id_medico = (int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { echo json_encode([]); exit; }

try {
  $sql = "SELECT id_agenda, hora_inicio, hora_fin
            FROM agenda
           WHERE id_medico=? AND DATE(fecha)=?
        ORDER BY hora_inicio";
  $st = $conn->prepare($sql);
  $st->bind_param('is', $id_medico, $fecha);
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
