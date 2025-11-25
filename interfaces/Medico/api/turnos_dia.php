<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');
session_start();
if(!isset($_SESSION['id_medico'])){ http_response_code(401); echo json_encode([]); exit; }

$id_medico = (int)$_SESSION['id_medico'];
$fecha = $_GET['fecha'] ?? date('Y-m-d');

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){ echo json_encode([]); exit; }

try{
  $sql = "
    SELECT t.id_turno, DATE(t.fecha) fecha, TIME_FORMAT(t.hora,'%H:%i') hora, t.observaciones,
           e.nombre_estado AS estado,
           p.id_paciente, p.nro_documento AS dni,
           CONCAT(u.apellido, ', ', u.nombre) AS paciente
      FROM turnos t
      LEFT JOIN estados e    ON e.id_estado=t.id_estado
      LEFT JOIN pacientes p ON p.id_paciente=t.id_paciente
      LEFT JOIN usuarios u   ON u.id_usuario=p.id_usuario
     WHERE t.id_medico=? AND DATE(t.fecha)=?
  ORDER BY t.hora ASC";
  $st = $conn->prepare($sql);
  $st->bind_param('is', $id_medico, $fecha);
  $st->execute();
  $rs = $st->get_result();

  $out=[];
  while($r=$rs->fetch_assoc()){
    $out[] = [
      'id_turno'      => (int)$r['id_turno'],
      'fecha'         => $r['fecha'],
      'hora'          => $r['hora'],
      'paciente'      => $r['paciente'],
      'dni'           => $r['dni'],
      'cobertura'     => '', // si luego agregás obra social, la completás acá
      'estado'        => $r['estado'],
      'observaciones' => $r['observaciones'] ?? '',
      'id_paciente'   => (int)$r['id_paciente'],
    ];
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
