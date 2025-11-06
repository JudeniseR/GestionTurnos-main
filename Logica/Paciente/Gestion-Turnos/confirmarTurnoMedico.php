<?php
// confirmarTurnoMedico.php (versión optimizada)
// Muestra errores en dev; en producción poner display_errors = 0
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require_once '../../../Persistencia/conexionBD.php';
require_once '../../General/envioNotif.php';

session_start();

try {
    // Conectar
    $conn = ConexionBD::conectar();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
        exit;
    }

    // Obtener paciente desde sesión
    $paciente_id = $_SESSION['id_paciente_token'] ?? null;
    if (!$paciente_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Debe estar logueado para confirmar un turno.']);
        exit;
    }

    // Recibir y validar parámetros
    $id_medico   = $_POST['id_medico'] ?? null;
    $fecha       = $_POST['fecha'] ?? null;        // esperar YYYY-MM-DD
    $hora_inicio = $_POST['hora_inicio'] ?? null;  // esperar HH:MM o HH:MM:SS

    if (!$id_medico || !$fecha || !$hora_inicio) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan datos para confirmar el turno.']);
        exit;
    }

    if (!is_numeric($id_medico)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de médico inválido.']);
        exit;
    }
    $id_medico = intval($id_medico);

    // Validar fecha (YYYY-MM-DD)
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de fecha inválido. Use YYYY-MM-DD.']);
        exit;
    }

    // Validar hora (HH:MM o HH:MM:SS) -> normalizar a HH:MM:SS
    $hora_obj = DateTime::createFromFormat('H:i:s', $hora_inicio) ?: DateTime::createFromFormat('H:i', $hora_inicio);
    if (!$hora_obj) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de hora inválido. Use HH:MM o HH:MM:SS.']);
        exit;
    }
    $hora_inicio_norm = $hora_obj->format('H:i:s');

    // Preparar: obtener id_estado de "confirmado"
    $sqlEstado = "SELECT id_estado FROM estados WHERE nombre_estado = 'confirmado' LIMIT 1";
    $resEstado = $conn->query($sqlEstado);
    if (!$resEstado || $resEstado->num_rows === 0) {
        http_response_code(500);
        echo json_encode(['error' => 'Estado "confirmado" no configurado en la base de datos.']);
        exit;
    }
    $rowEstado = $resEstado->fetch_assoc();
    $id_estado_confirmado = intval($rowEstado['id_estado']);

    // ---- Inicio de transacción ----
    $conn->begin_transaction();

    // 1) Verificar duplicado: mismo paciente, mismo médico y misma fecha (excluimos turnos cancelados)
    $sqlDup = "SELECT COUNT(*) AS cnt FROM turnos WHERE id_paciente = ? AND id_medico = ? AND fecha = ? AND (id_estado IS NULL OR id_estado <> ?)";
    $stmtDup = $conn->prepare($sqlDup);
    if (!$stmtDup) throw new Exception('Error preparando verificación duplicado.');
    $stmtDup->bind_param("iisi", $paciente_id, $id_medico, $fecha, $id_estado_confirmado /* usamos confirmado como referencia para evitar comparar solo cancelados */);
    $stmtDup->execute();
    $resDup = $stmtDup->get_result();
    $cntDup = $resDup->fetch_assoc()['cnt'] ?? 0;
    // Nota: la condición evita insertar si ya existe un turno (no cancelado) mismo día

    if ($cntDup > 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'Ya existe un turno para este paciente con ese médico en la misma fecha.']);
        exit;
    }

    // 2) Bloquear fila correspondiente en agenda (FOR UPDATE) y verificar disponibilidad
    $sqlCheckAgenda = "
        SELECT id_agenda, id_recurso
        FROM agenda
        WHERE id_medico = ? AND fecha = ? AND hora_inicio = ? AND disponible = 1
        LIMIT 1 FOR UPDATE
    ";
    $stmt = $conn->prepare($sqlCheckAgenda);
    if (!$stmt) throw new Exception('Error preparando consulta de agenda.');
    $stmt->bind_param("iss", $id_medico, $fecha, $hora_inicio_norm);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'El turno seleccionado ya no está disponible.']);
        exit;
    }

    $rowAgenda = $res->fetch_assoc();
    $agendaId = intval($rowAgenda['id_agenda']);
    $idRecurso = isset($rowAgenda['id_recurso']) ? intval($rowAgenda['id_recurso']) : null;

    // 3) Insertar en turnos
    $sqlInsertTurno = "
        INSERT INTO turnos (
            id_paciente, id_medico, id_recurso, id_estado, id_estudio,
            fecha, hora, copago, observaciones
        ) VALUES (?, ?, ?, ?, NULL, ?, ?, 0.00, '')
    ";
    $stmt2 = $conn->prepare($sqlInsertTurno);
    if (!$stmt2) throw new Exception('Error preparando insert de turno.');
    $stmt2->bind_param("iiiiss", $paciente_id, $id_medico, $idRecurso, $id_estado_confirmado, $fecha, $hora_inicio_norm);
    $stmt2->execute();
    $turnoId = $conn->insert_id;

    // 4) Marcar agenda como no disponible
    $sqlUpdateAgenda = "UPDATE agenda SET disponible = 0 WHERE id_agenda = ?";
    $stmt3 = $conn->prepare($sqlUpdateAgenda);
    if (!$stmt3) throw new Exception('Error preparando actualización de agenda.');
    $stmt3->bind_param("i", $agendaId);
    $stmt3->execute();

    // Commit transacción
    $conn->commit();

} catch (Exception $e) {
    // En caso de excepción asegurar rollback si la conexión está abierta y en transacción
    if (isset($conn) && $conn instanceof mysqli && $conn->errno === 0) {
        // intenta rollback solo si es necesario; en algunos entornos mysqli lanza excepción antes
        @$conn->rollback();
    }
    http_response_code(500);
    // No expongas mensajes de excepción crudos en producción; este mensaje ayuda en dev
    echo json_encode(['error' => 'Error al confirmar turno: ' . $e->getMessage()]);
    exit;
}

// ---- Obtener datos del turno para notificación ----
try {
    $sqlDatosTurno = "
        SELECT 
            t.fecha, t.hora, t.copago, t.observaciones,
            um.nombre  AS medico_nombre, 
            um.apellido AS medico_apellido,
            e.nombre_especialidad,
            s.nombre     AS nombre_sede, 
            s.direccion  AS direccion_sede,
            up.nombre    AS paciente_nombre, 
            up.apellido  AS paciente_apellido, 
            up.email     AS paciente_email
        FROM turnos t
        JOIN medicos m                ON t.id_medico = m.id_medico
        JOIN usuarios um              ON m.id_usuario = um.id_usuario
        LEFT JOIN medico_especialidad me   ON me.id_medico = m.id_medico
        LEFT JOIN especialidades e         ON me.id_especialidad = e.id_especialidad
        LEFT JOIN agenda a                 ON a.id_agenda = ?
        LEFT JOIN recursos r               ON a.id_recurso = r.id_recurso
        LEFT JOIN sedes s                  ON r.id_sede = s.id_sede
        JOIN pacientes p              ON t.id_paciente = p.id_paciente
        JOIN usuarios up              ON p.id_usuario = up.id_usuario
        WHERE t.id_turno = ?
        LIMIT 1
    ";

    $stmt4 = $conn->prepare($sqlDatosTurno);
    if (!$stmt4) {
        // No crítico: log y continuar
        error_log("Warning: no se pudo preparar consulta de datos de turno: " . $conn->error);
        $turno = null;
    } else {
        $stmt4->bind_param("ii", $agendaId, $turnoId);
        $stmt4->execute();
        $turno = $stmt4->get_result()->fetch_assoc();
    }
} catch (Exception $e) {
    // No crítico: log y continuar
    error_log("Error obteniendo datos de turno para notificación: " . $e->getMessage());
    $turno = null;
}

// Preparar datos para notificación si tenemos datos
$datosCorreo = null;
if ($turno) {
    $datosCorreo = [
        'email'         => strtolower(trim($turno['paciente_email'] ?? '')),
        'nombre'        => trim($turno['paciente_nombre'] ?? ''),
        'apellido'      => trim($turno['paciente_apellido'] ?? ''),
        'fecha_turno'   => isset($turno['fecha']) ? date('d/m/Y', strtotime($turno['fecha'])) : '',
        'hora_turno'    => isset($turno['hora']) ? substr($turno['hora'], 0, 5) : '',
        'medico'        => "Dr. " . trim(($turno['medico_nombre'] ?? '') . ' ' . ($turno['medico_apellido'] ?? '')),
        'especialidad'  => $turno['nombre_especialidad'] ?? '',
        'sede'          => $turno['nombre_sede'] ?? '',
        'direccion'     => $turno['direccion_sede'] ?? '',
        'copago'        => isset($turno['copago']) ? number_format($turno['copago'], 2) : '0.00',
        'observaciones' => $turno['observaciones'] ?? ''
    ];
}

// Enviar notificación (si corresponde) - protegemos salida con buffering
$notificacionEnviada = true;
if ($datosCorreo && !empty($datosCorreo['email'])) {
    ob_start();
    try {
        $notificacionEnviada = enviarNotificacion('turno_medico', $datosCorreo);
    } catch (Exception $e) {
        $notificacionEnviada = false;
        error_log("Error en enviarNotificacion: " . $e->getMessage());
    }
    // limpiar cualquier salida accidental generada por la función
    @ob_end_clean();
} else {
    // si no hay datos de correo, marcar como no enviado pero no fallar la operación
    $notificacionEnviada = false;
    error_log("No se enviará correo: faltan datos de correo para el turno ID {$turnoId}.");
}

// Respuesta final
http_response_code(200);
$response = [
    'success' => true,
    'mensaje' => '✅ Turno confirmado correctamente.',
    'id_turno' => $turnoId,
    'notificacion_enviada' => $notificacionEnviada ? true : false
];

echo json_encode($response);
exit;
