<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require_once __DIR__ . '/../../Persistencia/conexionBD.php';
$out = [];
try{
  $conn = ConexionBD::conectar();
  $conn->set_charset('utf8mb4');
  $res = $conn->query("SELECT id_estado, nombre_estado FROM estados ORDER BY id_estado");
  if($res){ while($row = $res->fetch_assoc()){ $out[] = $row; } }
  echo json_encode($out);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode([]);
}
