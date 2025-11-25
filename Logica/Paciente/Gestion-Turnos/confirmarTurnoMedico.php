<?php
// confirmarTurnoMedico.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require_once '../../../Persistencia/conexionBD.php';
require_once '../../General/envioNotif.php';

session_start();

try {
    $conn = ConexionBD::conectar();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
        exit;
    }

    // ID del paciente logueado (titular)
    $paciente_id_sesion = $_SESSION['id_paciente_token'] ?? null;
    if (!$paciente_id_sesion) {
        http_response_code(401);
        echo json_encode(['error' => 'Debe estar logueado para confirmar un turno.']);
        exit;
    }

    // ID del beneficiario (titular o afiliado)
    $id_beneficiario_raw = $_POST['beneficiario_id'] ?? null;
    if (!$id_beneficiario_raw) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo o ID del beneficiario no definido.']);
        exit;
    }

    // Separar tipo y valor
    if (!preg_match('/^(p|a)-(\d+)$/', $id_beneficiario_raw, $matches)) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de beneficiario inválido.']);
        exit;
    }
    $tipo_beneficiario = $matches[1]; // "p" o "a"
    $id_beneficiario = intval($matches[2]);

    // Determinar paciente y afiliado
    $paciente_id = $paciente_id_sesion;
    $afiliado_id = null;

    if ($tipo_beneficiario === 'a') {
        // Validar que el afiliado sea menor de 18 y activo
        $stmt_af = $conn->prepare("
            SELECT id 
            FROM afiliados
            WHERE id = ? 
              AND estado = 'activo' 
              AND TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) < 18
            LIMIT 1
        ");
        $stmt_af->bind_param("i", $id_beneficiario);
        $stmt_af->execute();
        $res_af = $stmt_af->get_result();
        $afiliado = $res_af->fetch_assoc();

        if ($res_af->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'No puede gestionar turnos para este afiliado.']);
            exit;
        }

        $afiliado_id = $id_beneficiario;
    }

    // Recibir parámetros del turno
    $id_medico   = intval($_POST['id_medico'] ?? 0);
    $fecha       = $_POST['fecha'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';

    if (!$id_medico || !$fecha || !$hora_inicio) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan datos para confirmar el turno.']);
        exit;
    }

    // Validar fecha
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de fecha inválido. Use YYYY-MM-DD.']);
        exit;
    }

    // Validar hora
    $hora_obj = DateTime::createFromFormat('H:i:s', $hora_inicio) ?: DateTime::createFromFormat('H:i', $hora_inicio);
    if (!$hora_obj) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de hora inválido. Use HH:MM o HH:MM:SS.']);
        exit;
    }
    $hora_inicio_norm = $hora_obj->format('H:i:s');

    // Obtener id_estado "confirmado"
    $resEstado = $conn->query("SELECT id_estado FROM estados WHERE nombre_estado = 'confirmado' LIMIT 1");
    if (!$resEstado || $resEstado->num_rows === 0) {
        http_response_code(500);
        echo json_encode(['error' => 'Estado "confirmado" no configurado en la base de datos.']);
        exit;
    }
    $id_estado_confirmado = intval($resEstado->fetch_assoc()['id_estado']);

    // Iniciar transacción
    $conn->begin_transaction();

    // 1️⃣ Verificar duplicado del turno
    $stmtDup = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM turnos 
    WHERE id_paciente = ?
      AND (id_afiliado = ? OR id_afiliado IS NULL)
      AND id_medico = ?
      AND id_estado = ?
");

    $afiliado_check = $afiliado_id ?? 0;
    $stmtDup->bind_param("iiii",
    $paciente_id,
    $afiliado_check,
    $id_medico,
    $id_estado_confirmado
);

    $stmtDup->execute();
    $cntDup = $stmtDup->get_result()->fetch_assoc()['cnt'] ?? 0;
    if ($cntDup > 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'Ya existe un turno confirmado para este paciente o afiliado con este médico en esa fecha.']);
        exit;
    }

    // 2️⃣ Verificar disponibilidad en agenda
    $stmtAgenda = $conn->prepare("
        SELECT id_agenda, id_recurso
        FROM agenda
        WHERE id_medico = ? AND fecha = ? AND hora_inicio = ? AND disponible = 1
        LIMIT 1 FOR UPDATE
    ");
    $stmtAgenda->bind_param("iss", $id_medico, $fecha, $hora_inicio_norm);
    $stmtAgenda->execute();
    $resAgenda = $stmtAgenda->get_result();
    if ($resAgenda->num_rows === 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'El turno seleccionado ya no está disponible.']);
        exit;
    }
    $rowAgenda = $resAgenda->fetch_assoc();
    $agendaId = intval($rowAgenda['id_agenda']);
    $idRecurso = intval($rowAgenda['id_recurso'] ?? 0);

    // 3️⃣ Insertar turno
    $observaciones = ($afiliado_id !== null) ? 'Turno solicitado por titular del grupo familiar' : '';
    $afiliado_id_bind = $afiliado_id; // puede ser NULL y está bien


$stmtTurno = $conn->prepare("
    INSERT INTO turnos (
        id_paciente, id_afiliado, id_medico, id_recurso, id_estado,
        fecha, hora, observaciones
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

if ($afiliado_id === null) {
    $stmtTurno->bind_param(
        "sisissss",
        $paciente_id,
        $afiliado_id,   // será NULL
        $id_medico,
        $idRecurso,
        $id_estado_confirmado,
        $fecha,
        $hora_inicio_norm,
        $observaciones
    );
} else {
    $stmtTurno->bind_param(
        "iiiissss",
        $paciente_id,
        $afiliado_id,
        $id_medico,
        $idRecurso,
        $id_estado_confirmado,
        $fecha,
        $hora_inicio_norm,
        $observaciones
    );
}


$stmtTurno->execute();

    $turnoId = $conn->insert_id;

    // 4️⃣ Actualizar agenda como no disponible
    $stmtUpdAgenda = $conn->prepare("UPDATE agenda SET disponible = 0 WHERE id_agenda = ?");
    $stmtUpdAgenda->bind_param("i", $agendaId);
    $stmtUpdAgenda->execute();

    $conn->commit();

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Error al confirmar turno: ' . $e->getMessage()]);
    exit;
}

// ---- Obtener datos para notificación ----
try {
    $sqlDatosTurno = "
        SELECT 
            t.fecha, t.hora, t.copago, t.observaciones,
            um.nombre AS medico_nombre, um.apellido AS medico_apellido,
            e.nombre_especialidad,
            s.nombre AS nombre_sede, s.direccion AS direccion_sede,
            up.nombre AS paciente_nombre, up.apellido AS paciente_apellido, up.email AS paciente_email
        FROM turnos t
        JOIN medicos m ON t.id_medico = m.id_medico
        JOIN usuarios um ON m.id_usuario = um.id_usuario
        LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
        LEFT JOIN especialidades e ON me.id_especialidad = e.id_especialidad
        LEFT JOIN agenda a ON a.id_agenda = ?
        LEFT JOIN recursos r ON a.id_recurso = r.id_recurso
        LEFT JOIN sedes s ON r.id_sede = s.id_sede
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        JOIN usuarios up ON p.id_usuario = up.id_usuario
        WHERE t.id_turno = ? LIMIT 1
    ";
    $stmtDatos = $conn->prepare($sqlDatosTurno);
    $stmtDatos->bind_param("ii", $agendaId, $turnoId);
    $stmtDatos->execute();
    $turno = $stmtDatos->get_result()->fetch_assoc();
} catch (Exception $e) {
    $turno = null;
    error_log("Error obteniendo datos del turno: " . $e->getMessage());
}

// Preparar y enviar notificación
$datosCorreo = null;
if ($turno) {
    $datosCorreo = [
        'email' => strtolower(trim($turno['paciente_email'] ?? '')),
        'nombre' => trim($turno['paciente_nombre'] ?? ''),
        'apellido' => trim($turno['paciente_apellido'] ?? ''),
        'fecha_turno' => isset($turno['fecha']) ? date('d/m/Y', strtotime($turno['fecha'])) : '',
        'hora_turno' => isset($turno['hora']) ? substr($turno['hora'], 0, 5) : '',
        'medico' => "Dr. " . trim(($turno['medico_nombre'] ?? '') . ' ' . ($turno['medico_apellido'] ?? '')),
        'especialidad' => $turno['nombre_especialidad'] ?? '',
        'sede' => $turno['nombre_sede'] ?? '',
        'direccion' => $turno['direccion_sede'] ?? '',
        'copago' => isset($turno['copago']) ? number_format($turno['copago'], 2) : '0.00',
        'observaciones' => $turno['observaciones'] ?? ''
    ];
}

$notificacionEnviada = false;
if ($datosCorreo && !empty($datosCorreo['email'])) {
    try {
        $notificacionEnviada = enviarNotificacion('turno_medico', $datosCorreo);
    } catch (Exception $e) {
        error_log("Error en enviarNotificacion: " . $e->getMessage());
    }
}

// Respuesta final
http_response_code(200);
echo json_encode([
    'success' => true,
    'mensaje' => '✅ Turno confirmado correctamente.',
    'id_turno' => $turnoId,
    'notificacion_enviada' => $notificacionEnviada
]);
exit;
