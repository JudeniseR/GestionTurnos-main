<?php
// Devuelve los estados de la tabla `estado` para poblar el filtro.
// Ruta: interfaces/Medico/api/estados_list.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode([]); exit; }

require_once('../../Persistencia/conexionBD.php');

function fail($code, $msg){ http_response_code($code); echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

try {
  $conn = ConexionBD::conectar();
  if (!$conn) { fail(500, 'No se pudo conectar a la BD'); }
  $conn->set_charset('utf8mb4');

  // IMPORTANTE: la tabla es `estado` (sin "s")
  $sql = "SELECT id_estado, nombre_estado FROM estado ORDER BY id_estado";
  $res = $conn->query($sql);
  if (!$res) { fail(500, 'SQL: '.$conn->error); }

  $out = [];
  while ($r = $res->fetch_assoc()) {
    $out[] = [
      'id'     => (int)$r['id_estado'],
      'nombre' => $r['nombre_estado'],
    ];
  }
  echo json_encode($out);
} catch (Throwable $e) {
  fail(500, 'throw: '.$e->getMessage());
}
