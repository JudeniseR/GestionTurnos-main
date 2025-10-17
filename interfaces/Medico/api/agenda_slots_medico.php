<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if(!isset($_SESSION['id_medico'])){ http_response_code(401); echo json_encode([]); exit; }
require_once('../../../Persistencia/conexionBD.php');

$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$id_medico = (int)$_SESSION['id_medico'];              // médico logueado
$fecha     = $_GET['fecha'] ?? date('Y-m-d');

if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
  http_response_code(400); echo json_encode([]); exit;
}

try{
  // Ventanas (franjas) habilitadas para ese día
  $q = $conn->prepare("SELECT hora_inicio, hora_fin
                       FROM agenda
                       WHERE id_medico=? AND fecha=? AND (disponible IS NULL OR disponible=1)
                       ORDER BY hora_inicio");
  $q->bind_param('is',$id_medico,$fecha);
  $q->execute();
  $ventanas = $q->get_result()->fetch_all(MYSQLI_ASSOC);

  // Turnos no cancelados (ocupan horario)
  $ocup = [];
  $q = $conn->prepare("SELECT t.hora
                         FROM turnos t
                         JOIN estado e ON e.id_estado=t.id_estado
                        WHERE t.id_medico=? AND t.fecha=? AND e.nombre_estado <> 'cancelado'");
  $q->bind_param('is',$id_medico,$fecha);
  $q->execute();
  $rs = $q->get_result();
  while($r = $rs->fetch_assoc()){
    $ocup[substr($r['hora'],0,5)] = true;
  }

  // Bloqueos de slot (si existe esa tabla)
  $bloq = [];
  if($conn->query("SHOW TABLES LIKE 'agenda_bloqueos'")->num_rows){
    $q = $conn->prepare("SELECT hora FROM agenda_bloqueos WHERE id_medico=? AND fecha=? AND tipo='slot'");
    $q->bind_param('is',$id_medico,$fecha);
    $q->execute();
    $rb = $q->get_result();
    while($b=$rb->fetch_assoc()){
      $bloq[substr($b['hora'],0,5)] = true;
    }
  }

  // Si no hay ventanas cargadas, opcionalmente podés devolver vacío o un horario por defecto.
  // Aquí devolvemos VACÍO para respetar la agenda real:
  if(empty($ventanas)){
    echo json_encode([]); exit;
  }

  // Construir slots cada 30'
  $out = [];
  foreach($ventanas as $v){
    $hi = strtotime($fecha.' '.$v['hora_inicio']);
    $hf = strtotime($fecha.' '.$v['hora_fin']);
    for($t = $hi; $t < $hf; $t += 30*60){
      $hh = date('H:i',$t);
      $disponible = !isset($ocup[$hh]) && !isset($bloq[$hh]);
      $out[] = ['hora'=>$hh, 'disponible'=>$disponible];
    }
  }

  echo json_encode($out);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode([]);
}
