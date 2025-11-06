<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

require_once('../../../Persistencia/conexionBD.php');
$cn = ConexionBD::conectar(); $cn->set_charset('utf8mb4');

$id_medico = isset($_GET['id_medico']) ? (int)$_GET['id_medico']
            : (isset($_POST['id_medico']) ? (int)$_POST['id_medico']
            : (int)($_SESSION['id_medico'] ?? 0));

$fecha     = $_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Fecha inválida']); exit; }

$hm2s = static function(string $h){ [$H,$M] = array_map('intval', explode(':',$h)); return $H*3600 + $M*60; };
$s2hm = static function(int $s){ return sprintf('%02d:%02d', intdiv($s,3600), intdiv($s%3600,60)); };

try {
  // --- bloqueos de día ---
  $bloqDia = 0; $motivoDia = null;
  if ($cn->query("SHOW TABLES LIKE 'agenda_bloqueos'")->num_rows) {
    $q = $cn->prepare("SELECT COALESCE(motivo,'Día bloqueado') m
                         FROM agenda_bloqueos
                        WHERE id_medico=? AND fecha=? AND tipo='dia' AND (activo=1 OR activo IS NULL)
                        LIMIT 1");
    $q->bind_param('is', $id_medico, $fecha);
    $q->execute(); $q->bind_result($m);
    if ($q->fetch()) { $bloqDia=1; $motivoDia=$m; }
    $q->close();
  }

  // --- feriados ---
  $esFeriado = 0; $feriado_desc = null;
  if ($cn->query("SHOW TABLES LIKE 'feriados'")->num_rows) {
    $q = $cn->prepare("SELECT COALESCE(descripcion, motivo, 'Feriado') d FROM feriados WHERE fecha=? LIMIT 1");
    $q->bind_param('s', $fecha); $q->execute(); $q->bind_result($d);
    if ($q->fetch()) { $esFeriado=1; $feriado_desc=$d; }
    $q->close();
  }

  // --- franjas del médico (sólo referencia) ---
  $franjas = [];
  $q = $cn->prepare("SELECT TIME_FORMAT(hora_inicio,'%H:%i') hi, TIME_FORMAT(hora_fin,'%H:%i') hf
                       FROM agenda
                      WHERE id_medico=? AND DATE(fecha)=?
                   ORDER BY hora_inicio");
  $q->bind_param('is', $id_medico, $fecha); $q->execute();
  $rs = $q->get_result();
  while($r = $rs->fetch_assoc()){ $franjas[] = ['hi'=>$r['hi'], 'hf'=>$r['hf']]; }
  $q->close();

  // --- turnos que ocupan slot ---
  $turnos = []; $turnos_det = [];
  if ($cn->query("SHOW TABLES LIKE 'estado'")->num_rows) {
    $sql = "SELECT TIME_FORMAT(t.hora,'%H:%i') hh, t.id_turno, t.id_paciente
              FROM turnos t
              JOIN estados e ON e.id_estado=t.id_estado
             WHERE t.id_medico=? AND DATE(t.fecha)=? AND e.nombre_estado<>'cancelado'";
  } else {
    $sql = "SELECT TIME_FORMAT(t.hora,'%H:%i') hh, t.id_turno, t.id_paciente
              FROM turnos t
             WHERE t.id_medico=? AND DATE(t.fecha)=? AND (t.id_estado IS NULL OR t.id_estado<>4)";
  }
  $q = $cn->prepare($sql);
  $q->bind_param('is', $id_medico, $fecha); $q->execute();
  $rs = $q->get_result();
  while($r=$rs->fetch_assoc()){ $turnos[$r['hh']]=1; $turnos_det[]=['hora'=>$r['hh'],'id_turno'=>(int)$r['id_turno'],'id_paciente'=>(int)$r['id_paciente']]; }
  $q->close();

  // --- bloqueos de slot ---
  $bloq = [];
  if ($cn->query("SHOW TABLES LIKE 'agenda_bloqueos'")->num_rows) {
    $q = $cn->prepare("SELECT TIME_FORMAT(hora,'%H:%i') hh, COALESCE(motivo,'Bloqueado') m
                         FROM agenda_bloqueos
                        WHERE id_medico=? AND fecha=? AND tipo='slot' AND (activo=1 OR activo IS NULL)");
    $q->bind_param('is', $id_medico, $fecha); $q->execute();
    $rs = $q->get_result();
    while($r=$rs->fetch_assoc()){ $bloq[$r['hh']]=$r['m']; }
    $q->close();
  }

  // helper (sólo para marcar visualmente lo que cae dentro de franja)
  $enFranja = static function(string $hhmm, array $franjas, $hm2s): bool {
  // Si no hay franjas definidas, consideramos TODO el día disponible
  if (!$franjas) return true;
  $t = $hm2s($hhmm);
  foreach ($franjas as $f) {
    $a = $hm2s($f['hi']);
    $b = $hm2s($f['hf']);
    if ($t >= $a && $t <= $b - 30 * 60) return true;
  }
  return false;
};


  // --- SIEMPRE 48 SLOTS; todo disponible salvo ocupaciones/bloqueos/feriado ---
  $slots = [];
  for ($s=0; $s<= (24*60-30)*60; $s+=30*60){
    $hora = $s2hm($s);
    $isFranja = $enFranja($hora, $franjas, $hm2s);

    $estado = 'disponible'; $motivo=''; $es_turno=0;
    if ($bloqDia || $esFeriado) {
      $estado='ocupado'; $motivo=$bloqDia?($motivoDia?:'Día bloqueado'):($feriado_desc?:'Feriado');
    } elseif (isset($turnos[$hora])) {
      $estado='ocupado'; $motivo='Turno asignado'; $es_turno=1;
    } elseif (isset($bloq[$hora])) {
      $estado='ocupado'; $motivo=$bloq[$hora];
    } else {
      $estado='disponible'; $motivo='';
    }

    $slots[] = [
      'hora'=>$hora,
      'en_franja'=> $isFranja ? 1 : 0, // dato informativo
      'estado'=>$estado,               // disponible | ocupado
      'motivo'=>$motivo,
      'es_turno'=>$es_turno
    ];
  }

  echo json_encode([
    'ok'=>true,
    'day'=>[
      'fecha'=>$fecha,
      'bloqueado'=>(bool)$bloqDia,
      'motivo_bloqueo'=>$motivoDia,
      'feriado'=>(bool)$esFeriado,
      'feriado_desc'=>$feriado_desc
    ],
    'franjas'=>$franjas,
    'slots'=>$slots,
    'turnos'=>$turnos_det
  ], JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
