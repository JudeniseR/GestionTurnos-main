<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

$id_medico = $_SESSION['id_medico'] ?? null;
$fecha = $_GET['fecha'] ?? null;

try {
  if(!$id_medico || !$fecha){ echo json_encode([]); exit; }

  require_once('../../../Persistencia/conexionBD.php'); // <-- RUTA CORRECTA
  $conn = ConexionBD::conectar();
  $conn->set_charset('utf8mb4');

  // Día bloqueado completo / feriado
  $isDiaBloq = false; $motivoDia = null;
  $st = $conn->prepare("SELECT motivo FROM agenda_bloqueos WHERE id_medico=? AND fecha=? AND tipo='dia' LIMIT 1");
  $st->bind_param('is',$id_medico,$fecha);
  $st->execute(); $st->bind_result($mot);
  if($st->fetch()){ $isDiaBloq=true; $motivoDia=$mot ?: 'Bloqueado'; }
  $st->close();

  $st = $conn->prepare("SELECT descripcion FROM feriados WHERE fecha=? LIMIT 1");
  $st->bind_param('s',$fecha);
  $st->execute(); $st->bind_result($fer);
  if($st->fetch()){ $isDiaBloq=true; $motivoDia=$fer ?: 'Feriado'; }
  $st->close();

  // Rangos de agenda del día
  $sqlAgenda = "SELECT hora_inicio, hora_fin
                FROM agenda
                WHERE id_medico=? AND fecha=? AND disponible=1
                ORDER BY hora_inicio";
  $st = $conn->prepare($sqlAgenda);
  $st->bind_param('is',$id_medico,$fecha);
  $st->execute();
  $res = $st->get_result();
  $rangos = [];
  while($r=$res->fetch_assoc()){ $rangos[] = [$r['hora_inicio'],$r['hora_fin']]; }
  $st->close();

  // REGLA NUEVA: si no hay agenda, usamos un rango por defecto 09:00–17:00
  if(empty($rangos)){ $rangos = [['09:00:00','17:00:00']]; }

  // Turnos ocupados (no cancelado)
  $sqlTurnos = "SELECT hora FROM turnos t
                JOIN estado e ON e.id_estado=t.id_estado
                WHERE t.id_medico=? AND t.fecha=? AND e.nombre_estado <> 'cancelado'";
  $st = $conn->prepare($sqlTurnos);
  $st->bind_param('is',$id_medico,$fecha);
  $st->execute();
  $res = $st->get_result();
  $ocupados = [];
  while($r=$res->fetch_assoc()){ $ocupados[substr($r['hora'],0,5)] = true; }
  $st->close();

  // Bloqueos de slot puntual
  $sqlBloq = "SELECT hora, motivo FROM agenda_bloqueos WHERE id_medico=? AND fecha=? AND tipo='slot'";
  $st = $conn->prepare($sqlBloq);
  $st->bind_param('is',$id_medico,$fecha);
  $st->execute();
  $res = $st->get_result();
  $bloq = [];
  while($r=$res->fetch_assoc()){ $bloq[substr($r['hora'],0,5)] = $r['motivo'] ?: 'Bloqueado'; }
  $st->close();

  // Generar cada 30 min
  $INTERVALO_MIN = 30;
  $out = [];
  foreach($rangos as [$desde,$hasta]){
    $t  = strtotime($fecha.' '.$desde);
    $tf = strtotime($fecha.' '.$hasta);
    for($i=$t; $i<$tf; $i+=$INTERVALO_MIN*60){
      $hh = date('H:i',$i);
      $estado = 'disponible'; $motivo = null;

      if($isDiaBloq){ $estado='ocupado'; $motivo=$motivoDia; }
      if(isset($bloq[$hh])){ $estado='ocupado'; $motivo=$bloq[$hh]; }
      if(isset($ocupados[$hh])){ $estado='ocupado'; $motivo='Turno asignado'; }

      $out[] = ['hora'=>$hh, 'estado'=>$estado, 'motivo'=>$motivo];
    }
  }
  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>true,'msg'=>'agenda_slots: '.$e->getMessage()]);
}
