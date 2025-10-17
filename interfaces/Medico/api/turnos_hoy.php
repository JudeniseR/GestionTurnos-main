<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if(!isset($_SESSION['id_medico'])){ http_response_code(401); echo json_encode([]); exit; }
$id_medico = (int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$hoy = date('Y-m-d');

try{
  $sql = "
    SELECT t.id_turno, t.fecha, t.hora, t.observaciones,
           e.nombre_estado AS estado,
           p.id_paciente, p.nro_documento AS dni,
           CONCAT(u.apellido, ', ', u.nombre) AS paciente
    FROM turnos t
    LEFT JOIN estado e    ON e.id_estado=t.id_estado
    LEFT JOIN pacientes p ON p.id_paciente=t.id_paciente
    LEFT JOIN usuario u   ON u.id_usuario=p.id_usuario
    WHERE t.id_medico=? AND t.fecha=?
    ORDER BY t.hora ASC";
  $st = $conn->prepare($sql);
  $st->bind_param('is', $id_medico, $hoy);
  $st->execute();
  $rs = $st->get_result();

  $out=[];
  while($r=$rs->fetch_assoc()){
    $out[] = [
      'id_turno' => (int)$r['id_turno'],
      'hora' => substr($r['hora'],0,5),
      'paciente' => $r['paciente'],
      'dni' => $r['dni'],
      'cobertura' => '', // no existe en pacientes
      'estado' => $r['estado'],
      'observaciones' => $r['observaciones'] ?? '',
      'id_paciente' => (int)$r['id_paciente'],
    ];
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
