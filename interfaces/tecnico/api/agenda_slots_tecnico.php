<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','1');

session_start();

// --- obtener id_tecnico desde sesión ---
$id_tecnico = $_SESSION['id_tecnico'] ?? null;
if (!$id_tecnico && isset($_SESSION['id_usuario'])) {
    require_once('../../../Persistencia/conexionBD.php');
    $cn = ConexionBD::conectar();
    $stmt = $cn->prepare("SELECT id_tecnico FROM tecnicos WHERE id_usuario=? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['id_usuario']);
    $stmt->execute();
    $stmt->bind_result($id_tecnico);
    $stmt->fetch();
    $stmt->close();
    if ($id_tecnico) $_SESSION['id_tecnico'] = $id_tecnico;
}

if (!$id_tecnico) { 
    http_response_code(401); 
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']); 
    exit; 
}

require_once('../../../Persistencia/conexionBD.php');
$cn = ConexionBD::conectar(); 
$cn->set_charset('utf8mb4');

// --- obtener fecha ---
$fecha = $_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { 
    http_response_code(400); 
    echo json_encode(['ok'=>false,'msg'=>'Fecha inválida']); 
    exit; 
}

// --- funciones auxiliares ---
$hm2s = static function(string $h){ [$H,$M] = array_map('intval', explode(':',$h)); return $H*3600 + $M*60; };
$s2hm = static function(int $s){ return sprintf('%02d:%02d', intdiv($s,3600), intdiv($s%3600,60)); };

// --- obtener id_recurso asociado al técnico ---
$id_recurso = null;
$stmt = $cn->prepare("SELECT id_recurso FROM recursos WHERE id_tecnico=? LIMIT 1");
$stmt->bind_param('i', $id_tecnico);
$stmt->execute();
$stmt->bind_result($id_recurso);
$stmt->fetch();
$stmt->close();

if (!$id_recurso) {
    echo json_encode(['ok'=>false,'msg'=>'No se encontró recurso asignado al técnico']);
    exit;
}

try {
    // --- bloqueos de día ---
    $bloqDia = 0; $motivoDia = null;
    if ($cn->query("SHOW TABLES LIKE 'agenda_bloqueos'")->num_rows) {
        $q = $cn->prepare("SELECT COALESCE(motivo,'Día bloqueado') m
                             FROM agenda_bloqueos
                            WHERE id_tecnico=? AND fecha=? AND tipo='dia' AND (activo=1 OR activo IS NULL)
                            LIMIT 1");
        $q->bind_param('is', $id_tecnico, $fecha);
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

    // --- franjas del técnico ---
    $franjas = [];
    $q = $cn->prepare("SELECT TIME_FORMAT(hora_inicio,'%H:%i') hi, TIME_FORMAT(hora_fin,'%H:%i') hf
                         FROM agenda
                        WHERE id_tecnico=? AND DATE(fecha)=?
                     ORDER BY hora_inicio");
    $q->bind_param('is', $id_tecnico, $fecha); 
    $q->execute();
    $rs = $q->get_result();
    while($r = $rs->fetch_assoc()){ $franjas[] = ['hi'=>$r['hi'], 'hf'=>$r['hf']]; }
    $q->close();

    // --- turnos asignados al recurso ---
    $turnos = []; $turnos_det = [];
    $sql = "SELECT TIME_FORMAT(t.hora,'%H:%i') hh, t.id_turno, t.id_paciente, e.nombre AS estudio
              FROM turnos t
              LEFT JOIN estudios e ON e.id_estudio = t.id_estudio
             WHERE t.id_recurso=? AND DATE(t.fecha)=? AND t.id_estado IN (1,2,5)";
    $q = $cn->prepare($sql);
    $q->bind_param('is', $id_recurso, $fecha);
    $q->execute();
    $rs = $q->get_result();
    while($r=$rs->fetch_assoc()){ 
        $turnos[$r['hh']]=1; 
        $turnos_det[] = [
            'hora'=>$r['hh'],
            'id_turno'=>(int)$r['id_turno'],
            'id_paciente'=>(int)$r['id_paciente'],
            'estudio'=>$r['estudio']??''
        ]; 
    }
    $q->close();

    // --- bloqueos de slot ---
    $bloq = [];
    if ($cn->query("SHOW TABLES LIKE 'agenda_bloqueos'")->num_rows) {
        $q = $cn->prepare("SELECT TIME_FORMAT(hora,'%H:%i') hh, COALESCE(motivo,'Bloqueado') m
                             FROM agenda_bloqueos
                            WHERE id_tecnico=? AND fecha=? AND tipo='slot' AND (activo=1 OR activo IS NULL)");
        $q->bind_param('is', $id_tecnico, $fecha);
        $q->execute();
        $rs = $q->get_result();
        while($r=$rs->fetch_assoc()){ $bloq[$r['hh']]=$r['m']; }
        $q->close();
    }

    // helper para saber si hora cae dentro de alguna franja
    $enFranja = static function(string $hhmm, array $franjas, $hm2s): bool {
        if (!$franjas) return false;
        $t = $hm2s($hhmm);
        foreach ($franjas as $f) {
            $a=$hm2s($f['hi']); $b=$hm2s($f['hf']);
            if ($t >= $a && $t <= $b-30*60) return true;
        }
        return false;
    };

    // --- slots de 30 minutos ---
    $slots = [];
    for ($s=0; $s<= (24*60-30)*60; $s+=30*60){
        $hora = $s2hm($s);
        $isFranja = $enFranja($hora, $franjas, $hm2s);

        $estado = 'disponible'; $motivo=''; $es_turno=0;
        if ($bloqDia || $esFeriado) {
            $estado='ocupado'; 
            $motivo=$bloqDia?($motivoDia?:'Día bloqueado'):($feriado_desc?:'Feriado');
        } elseif (isset($turnos[$hora])) {
            $estado='ocupado'; $motivo='Turno asignado'; $es_turno=1;
        } elseif (isset($bloq[$hora])) {
            $estado='ocupado'; $motivo=$bloq[$hora];
        }

        $slots[] = [
            'hora'=>$hora,
            'en_franja'=> $isFranja ? 1 : 0,
            'estado'=>$estado,
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
