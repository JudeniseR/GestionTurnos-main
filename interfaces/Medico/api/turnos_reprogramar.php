<?php
require_once('../../../Datos/Conexion.php');
session_start();

$id_turno = $_POST['id_turno'] ?? null;
$nueva_fecha = $_POST['fecha'] ?? null;
$nueva_hora = $_POST['hora'] ?? null;

try {
    $conexion = Conexion::conectar();

    // 1️⃣ Verificar que el turno esté confirmado antes de reprogramar
    $check = $conexion->prepare("SELECT id_estado FROM turnos WHERE id_turno = ?");
    $check->execute([$id_turno]);
    $estado_actual = $check->fetchColumn();

    if ($estado_actual != 2) { // 2 = confirmado
        echo json_encode(['error' => 'Solo los turnos confirmados pueden reprogramarse.']);
        exit;
    }

    // 2️⃣ Actualizar turno como reprogramado
    $stmt = $conexion->prepare("UPDATE turnos 
                                SET fecha = ?, hora = ?, id_estado = 5 
                                WHERE id_turno = ?");
    $stmt->execute([$nueva_fecha, $nueva_hora, $id_turno]);

    echo json_encode(['success' => true, 'msg' => 'Turno reprogramado correctamente.']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
