<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

$id_medico = $_SESSION['id_medico'] ?? null;
$anio = (int)($_GET['anio'] ?? date('Y'));
$mes  = (int)($_GET['mes']  ?? date('n'));

try {
  if(!$id_medico || $anio < 2000 || $mes < 1 || $mes > 12){
    echo json_encode([]); exit;
  }

  require_once('../../../Persistencia/conexionBD.php'); // <-- RUTA CORRECTA
  $conn = ConexionBD::conectar();
  $conn->set_charset('utf8mb4');

  $desde = sprintf('%04d-%02d-01', $anio, $mes);
  $hasta = date('Y-m-t', strtotime($desde));

  // Agenda del mes (rangos disponibles)
  $sqlAgenda = "
    SELECT DATE(fecha) f, COUNT(*) cant
    FROM agenda
    WHERE id_medico = ? AND fecha BETWEEN ? AND ? AND disponible = 1
    GROUP BY DATE(fecha)";
  $st = $conn->prepare($sqlAgenda);
  $st->bind_param('iss', $id_medico, $desde, $hasta);
  $st->execute();
  $res = $st->get_result();
  $byDay = [];
  while($r = $res->fetch_assoc()){
    $byDay[$r['f']] = ['slots'=>(int)$r['cant'], 'ocupados'=>0, 'bloqDia'=>0];
  }
  $st->close();

  // Turnos ocupando (no cancelados)
  $sqlTurnos = "
    SELECT t.fecha f, COUNT(*) usados
    FROM turnos t
    JOIN estado e ON e.id_estado = t.id_estado
    WHERE t.id_medico=? AND t.fecha BETWEEN ? AND ? AND e.nombre_estado <> 'cancelado'
    GROUP BY t.fecha";
  $st = $conn->prepare($sqlTurnos);
  $st->bind_param('iss', $id_medico, $desde, $hasta);
  $st->execute();
  $res = $st->get_result();
  while($r=$res->fetch_assoc()){
    $f=$r['f'];
    if(!isset($byDay[$f])) $byDay[$f] = ['slots'=>0,'ocupados'=>0,'bloqDia'=>0];
    $byDay[$f]['ocupados'] = (int)$r['usados'];
  }
  $st->close();

  // Bloqueo de día
  $sqlBloqDia = "
    SELECT fecha f, COUNT(*) c
    FROM agenda_bloqueos
    WHERE id_medico=? AND fecha BETWEEN ? AND ? AND tipo='dia'
    GROUP BY fecha";
  $st = $conn->prepare($sqlBloqDia);
  $st->bind_param('iss', $id_medico, $desde, $hasta);
  $st->execute();
  $res = $st->get_result();
  while($r=$res->fetch_assoc()){
    $f=$r['f'];
    if(!isset($byDay[$f])) $byDay[$f] = ['slots'=>0,'ocupados'=>0,'bloqDia'=>0];
    $byDay[$f]['bloqDia'] += (int)$r['c'];
  }
  $st->close();

  // Feriados
  $sqlFer = "SELECT fecha f FROM feriados WHERE fecha BETWEEN ? AND ?";
  $st = $conn->prepare($sqlFer);
  $st->bind_param('ss', $desde, $hasta);
  $st->execute();
  $res = $st->get_result();
  while($r=$res->fetch_assoc()){
    $f=$r['f'];
    if(!isset($byDay[$f])) $byDay[$f] = ['slots'=>0,'ocupados'=>0,'bloqDia'=>0];
    $byDay[$f]['bloqDia'] += 1;
  }
  $st->close();

  $ultimo = (int)date('t', strtotime($desde));
  $out = [];
  for($d=1;$d<=$ultimo;$d++){
    $f = sprintf('%04d-%02d-%02d',$anio,$mes,$d);

    // REGLA NUEVA:
    // Si no existe info para el día, lo devolvemos como "verde" (disponible)
    // salvo que haya feriado/bloqueo (ya quedó en $byDay con bloqDia > 0).
    if(!isset($byDay[$f])){
      $estado = 'verde';
    } else {
      if($byDay[$f]['bloqDia']>0)      $estado='rojo';
      elseif($byDay[$f]['slots']>0)    $estado=($byDay[$f]['ocupados'] < $byDay[$f]['slots']) ? 'verde' : 'rojo';
      else                              $estado='verde'; // sin agenda -> disponible
    }

    $out[] = [
      'dia'=>$d,
      'estado'=>$estado,
      'libres'=>isset($byDay[$f]) ? max(0,$byDay[$f]['slots']-$byDay[$f]['ocupados']) : null,
      'ocupados'=>isset($byDay[$f]) ? $byDay[$f]['ocupados'] : 0
    ];
  }
  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>true,'msg'=>'agenda_estado: '.$e->getMessage()]);
}
