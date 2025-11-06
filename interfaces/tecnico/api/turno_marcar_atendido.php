<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');
session_start();

if (!isset($_SESSION['id_tecnico'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$id_tecnico = (int)$_SESSION['id_tecnico'];
$id_turno   = (int)($_POST['id_turno'] ?? 0);

if ($id_turno <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID de turno inválido']);
    exit;
}

try {
    // Verificar que el turno pertenezca al técnico actual
    $sql = "SELECT id_estado FROM turnos WHERE id_turno = ? AND id_tecnico = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $id_turno, $id_tecnico);
    $stmt->execute();
    $result = $stmt->get_result();
    $turno = $result->fetch_assoc();

    if (!$turno) {
        echo json_encode(['ok' => false, 'msg' => 'Turno no encontrado o no asignado a este técnico']);
        exit;
    }

    if ($turno['id_estado'] == 3) { // 3 = atendido
        echo json_encode(['ok' => false, 'msg' => 'El turno ya fue marcado como atendido']);
        exit;
    }

    // Actualizar estado a "atendido" (solo id_estado)
    $update = $conn->prepare("UPDATE turnos SET id_estado = 3 WHERE id_turno = ? AND id_tecnico = ?");
    $update->bind_param('ii', $id_turno, $id_tecnico);
    $update->execute();

    echo json_encode(['ok' => true, 'msg' => 'Turno marcado como atendido correctamente']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error interno: ' . $e->getMessage()]);
}
