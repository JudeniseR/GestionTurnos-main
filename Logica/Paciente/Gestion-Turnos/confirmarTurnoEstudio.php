<?php
/**
 * ==========================================
 * CONFIRMAR TURNO DE ESTUDIO
 * ==========================================
 * - Asocia el turno al paciente logueado.
 * - Vincula el técnico, el estudio, el recurso y la orden médica.
 * - Marca el turno como "confirmado" en la agenda.
 * - Envía notificación por email al paciente.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesion.php';
require_once '../../General/envioNotif.php';

try {
    // === Conexión y sesión ===
    $conn = ConexionBD::conectar();
    $conn->set_charset('utf8mb4');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $idPaciente = $_SESSION['id_paciente_token'] ?? null;

    // === Datos recibidos ===
    $fecha          = $_POST['fecha'] ?? '';
    $horaInicio     = $_POST['hora_inicio'] ?? '';
    $idEstudio      = (int)($_POST['id_estudio'] ?? 0);
    $idOrdenMedica  = (int)($_POST['id_orden_medica'] ?? 0); // Nuevo campo

    // === Validación ===
    if (!$idPaciente || !$fecha || !$horaInicio || !$idEstudio) {
        echo json_encode([
            'success' => false,
            'error' => 'Faltan datos obligatorios o sesión inválida.'
        ]);
        exit;
    }

    // === Iniciar transacción ===
    $conn->begin_transaction();

    // === Buscar un turno disponible en la agenda ===
    $sql = "
        SELECT 
            a.id_agenda,
            a.id_tecnico,
            t.id_recurso
        FROM agenda a
        JOIN tecnico_estudio te ON te.id_tecnico = a.id_tecnico
        JOIN tecnicos t ON t.id_tecnico = a.id_tecnico
        WHERE 
            te.id_estudio = ?
            AND a.fecha = ?
            AND a.hora_inicio = ?
            AND a.disponible = 1
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $idEstudio, $fecha, $horaInicio);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Turno no disponible o ya reservado.');
    }

    $data = $result->fetch_assoc();
    $idAgenda  = (int)$data['id_agenda'];
    $idTecnico = (int)$data['id_tecnico'];
    $idRecurso = (int)$data['id_recurso'];

    // === Marcar agenda como no disponible ===
    $update = $conn->prepare("UPDATE agenda SET disponible = 0 WHERE id_agenda = ?");
    $update->bind_param("i", $idAgenda);

    if (!$update->execute() || $update->affected_rows === 0) {
        throw new Exception('No se pudo actualizar la disponibilidad de la agenda.');
    }

    // === Obtener estado "confirmado" ===
    $resEstado = $conn->query("SELECT id_estado FROM estados WHERE nombre_estado = 'confirmado' LIMIT 1");
    if (!$resEstado || $resEstado->num_rows === 0) {
        throw new Exception('Estado "confirmado" no configurado en la base de datos.');
    }
    $idEstadoConfirmado = (int)$resEstado->fetch_assoc()['id_estado'];

    // === Insertar turno confirmado ===
    $sqlInsert = "
        INSERT INTO turnos
            (id_paciente, id_tecnico, id_estudio, id_orden_medica, id_recurso, fecha, hora, id_estado, copago, observaciones)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00, '')
    ";

    $stmtIns = $conn->prepare($sqlInsert);
    $stmtIns->bind_param(
        "iiiiissi",
        $idPaciente,
        $idTecnico,
        $idEstudio,
        $idOrdenMedica,
        $idRecurso,
        $fecha,
        $horaInicio,
        $idEstadoConfirmado
    );

    if (!$stmtIns->execute()) {
        throw new Exception('Error al confirmar el turno: ' . $stmtIns->error);
    }

    $idTurno = $conn->insert_id;

    // === Obtener datos completos del turno para el correo ===
    $sqlDatos = "
        SELECT 
            t.fecha, t.hora, t.copago, t.observaciones,
            e.nombre AS nombre_estudio,
            e.instrucciones,
            r.nombre AS nombre_recurso, r.tipo AS tipo_recurso,
            s.nombre AS nombre_sede, s.direccion AS direccion_sede,
            up.nombre AS paciente_nombre, up.apellido AS paciente_apellido, up.email AS paciente_email
        FROM turnos t
        JOIN estudios e  ON t.id_estudio = e.id_estudio
        LEFT JOIN recursos r ON t.id_recurso = r.id_recurso
        LEFT JOIN sedes s ON r.id_sede = s.id_sede
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        JOIN usuarios up ON p.id_usuario = up.id_usuario
        WHERE t.id_turno = ?
        LIMIT 1
    ";

    $stmtDatos = $conn->prepare($sqlDatos);
    $stmtDatos->bind_param("i", $idTurno);
    $stmtDatos->execute();
    $resultDatos = $stmtDatos->get_result();

    if ($resultDatos->num_rows === 0) {
        throw new Exception('No se pudieron obtener los datos del turno para el correo.');
    }

    $turno = $resultDatos->fetch_assoc();

    // === Confirmar transacción ===
    $conn->commit();

    // === Enviar correo al paciente ===
    $recomendaciones = ["Presentarse 15 minutos antes del horario asignado."];
    if (!empty($turno['instrucciones'])) $recomendaciones[] = $turno['instrucciones'];
    if (!empty($turno['observaciones'])) $recomendaciones[] = $turno['observaciones'];

    $datosCorreo = [
        'email'           => $turno['paciente_email'],
        'nombre'          => $turno['paciente_nombre'],
        'apellido'        => $turno['paciente_apellido'],
        'fecha'           => $turno['fecha'],
        'hora'            => $turno['hora'],
        'especialidad'    => $turno['nombre_estudio'],
        'profesional'     => ucfirst($turno['tipo_recurso'] ?? 'Técnico') . ': ' . ($turno['nombre_recurso'] ?? 'Asignado'),
        'direccion'       => $turno['direccion_sede'] ?? 'Dirección no disponible',
        'copago'          => '$' . number_format($turno['copago'], 2),
        'recomendaciones' => $recomendaciones
    ];

    enviarNotificacion('turno_estudio', $datosCorreo);

    echo json_encode([
        'success' => true,
        'mensaje' => '✅ Turno de estudio confirmado y notificación enviada.'
    ]);

} catch (Exception $e) {
    // === Rollback ante error ===
    if ($conn && $conn->errno === 0) {
        $conn->rollback();
    }

    echo json_encode([
        'success' => false,
        'error' => '❌ ' . $e->getMessage()
    ]);
}

exit;
?>
