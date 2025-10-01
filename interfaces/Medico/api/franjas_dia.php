<?php
// Lista las franjas (rangos) de agenda del día
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

$id_medico = $_SESSION['id_medico'] ?? null;
$fecha     = $_GET['fecha'] ?? null;

try {
  if(!$id_medico || !$fecha){ echo json_encode([]); exit; }

  require_once('../../../Persistencia/conexionBD.php');
  $conn = ConexionBD::conectar();
  $conn->set_charset('utf8mb4');

  $sql = "SELECT id_agenda, TIME_FORMAT(hora_inicio, '%H:%i') AS hora_inicio,
                         TIME_FORMAT(hora_fin, '%H:%i')    AS hora_fin
          FROM agenda
          WHERE id_medico=? AND fecha=? AND disponible=1
          ORDER BY hora_inicio";
  $st = $conn->prepare($sql);
  $st->bind_param('is', $id_medico, $fecha);
  $st->execute();
  $res = $st->get_result();

  $out = [];
  while($r = $res->fetch_assoc()){
    $out[] = [
      'id_agenda'   => (int)$r['id_agenda'],
      'hora_inicio' => $r['hora_inicio'],
      'hora_fin'    => $r['hora_fin']
    ];
  }
  $st->close();

  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'franjas_dia: '.$e->getMessage()]);
}
