<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

try{
  $rs = $conn->query("SELECT id_estado, nombre_estado FROM estados ORDER BY nombre_estado");
  $out=[]; while($r=$rs->fetch_assoc()){ $out[]=['id'=>(int)$r['id_estado'],'nombre'=>$r['nombre_estado']]; }
  echo json_encode($out);
}catch(Throwable $e){ http_response_code(500); echo json_encode([]); }
