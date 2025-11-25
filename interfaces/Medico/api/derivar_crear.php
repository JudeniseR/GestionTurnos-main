<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');

session_start();
if(!isset($_SESSION['id_medico'])){ 
    http_response_code(401); 
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']); 
    exit; 
}
$med_origen = (int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); 
$conn->set_charset('utf8mb4');

// Recibir datos del POST
$id_turno_origen = (int)($_POST['id_turno'] ?? 0);
$med_dest        = (int)($_POST['id_medico_dest'] ?? 0);
$fecha           = $_POST['fecha'] ?? '';
$hora            = $_POST['hora'] ?? '';

// Validar datos
if($id_turno_origen <= 0 || $med_dest <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !preg_match('/^\d{2}:\d{2}$/', $hora)){
    http_response_code(400); 
    echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); 
    exit;
}

try{
    // Obtener paciente del turno original
    $q = $conn->prepare("SELECT id_paciente FROM turnos WHERE id_turno=? AND id_medico=?");
    $q->bind_param('ii', $id_turno_origen, $med_origen);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    if(!$row){ 
        http_response_code(404); 
        echo json_encode(['ok'=>false,'msg'=>'Turno origen no encontrado']); 
        exit; 
    }
    $id_pac = (int)$row['id_paciente'];

    // Obtener el id_estado para "Derivado"
    $result = $conn->query("SELECT id_estado FROM estados WHERE nombre_estado='Derivado' LIMIT 1");
    if(!$result) throw new Exception("Error en query de estados: " . $conn->error);
    $estado = $result->fetch_assoc();
    $id_estado = (int)($estado['id_estado'] ?? 0);

    if($id_estado <= 0){
        throw new Exception("No se encontró el estado 'Derivado' en la tabla estados. Por favor insertarlo primero.");
    }

    // Insertar el turno derivado
    $ins = $conn->prepare("INSERT INTO turnos (id_paciente,id_medico,fecha,hora,id_estado,observaciones) VALUES (?,?,?,?,?,?)");
    $obs = 'Derivado desde turno #' . $id_turno_origen;
    $h2 = $hora . ':00'; // Formato TIME para MySQL
    $ins->bind_param('iissis', $id_pac, $med_dest, $fecha, $h2, $id_estado, $obs);
    $ins->execute();

    if($ins->affected_rows === 0){
        throw new Exception("No se pudo crear el turno derivado.");
    }

    // Actualizar observaciones del turno original
    $upd = $conn->prepare("UPDATE turnos SET observaciones = CONCAT(COALESCE(observaciones,''), ' | Derivado a médico ? (? ?)') WHERE id_turno=? AND id_medico=?");
    $upd->bind_param('ssiii', $med_dest, $fecha, $h2, $id_turno_origen, $med_origen);
    $upd->execute();

    echo json_encode(['ok'=>true,'nuevo_turno'=>$conn->insert_id]);

}catch(Throwable $e){
    http_response_code(500); 
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
