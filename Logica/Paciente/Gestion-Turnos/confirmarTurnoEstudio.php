<?php
/**
 * ==========================================
 * CONFIRMAR TURNO DE ESTUDIO (versión final con lógica de beneficiarios)
 * ==========================================
 * - Permite turnos para el titular o sus afiliados menores activos.
 * - Evita duplicados confirmados.
 * - Marca agenda como no disponible y envía notificación por correo.
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

    $paciente_id_sesion = $_SESSION['id_paciente_token'] ?? null;

    // === Datos recibidos ===
    $fecha         = $_POST['fecha'] ?? '';
    $horaInicio    = $_POST['hora_inicio'] ?? '';
    $idEstudio     = isset($_POST['id_estudio']) ? intval($_POST['id_estudio']) : null;
    $idOrdenMedica = isset($_POST['id_orden_medica']) ? intval($_POST['id_orden_medica']) : null;
    $beneficiario_raw = $_POST['beneficiario_id'] ?? null;

    // === Validación básica ===
    if (!$paciente_id_sesion || !$fecha || !$horaInicio || !$idEstudio || !$beneficiario_raw) {
        echo json_encode([
            'success' => false,
            'error' => 'Faltan datos obligatorios o sesión inválida.'
        ]);
        exit;
    }

    // === 1️⃣ Determinar tipo de beneficiario ===
    if (!preg_match('/^(p|a)-(\d+)$/', $beneficiario_raw, $m)) {
        echo json_encode(['success' => false, 'error' => 'Formato de beneficiario inválido.']);
        exit;
    }

    $tipo_beneficiario = $m[1];
    $id_beneficiario = intval($m[2]);

    $id_paciente_beneficiario = $paciente_id_sesion; // por defecto es el mismo paciente

    if ($tipo_beneficiario === 'a') {
    // 🔹 PASO 1: Obtener el documento del paciente en sesión
    $stmt_doc = $conn->prepare("SELECT nro_documento FROM pacientes WHERE id_paciente = ?");
    $stmt_doc->bind_param("i", $paciente_id_sesion);
    $stmt_doc->execute();
    $res_doc = $stmt_doc->get_result();
    
    if ($res_doc->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Paciente titular no encontrado.']);
        exit;
    }
    
    $nro_doc_titular = $res_doc->fetch_assoc()['nro_documento'];
    $stmt_doc->close();
    
    // 🔹 PASO 2: Obtener el ID del titular en la tabla afiliados
    $stmt_titular = $conn->prepare("
        SELECT id 
        FROM afiliados 
        WHERE numero_documento = ? 
          AND tipo_beneficiario = 'titular' 
        LIMIT 1
    ");
    $stmt_titular->bind_param("s", $nro_doc_titular);
    $stmt_titular->execute();
    $res_titular = $stmt_titular->get_result();
    
    if ($res_titular->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No está registrado como titular en el sistema de afiliados.']);
        exit;
    }
    
    $id_titular_afiliado = (int)$res_titular->fetch_assoc()['id'];
    $stmt_titular->close();
    
    // 🔹 PASO 3: Verificar que el afiliado menor pertenezca a este titular
    $stmt_af = $conn->prepare("
        SELECT id, numero_documento
        FROM afiliados
        WHERE id = ?
          AND id_titular = ?
          AND estado = 'activo'
          AND tipo_beneficiario IN ('hijo menor', 'hijo mayor')
          AND TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) < 18
        LIMIT 1
    ");
    $stmt_af->bind_param("ii", $id_beneficiario, $id_titular_afiliado);
    $stmt_af->execute();
    $res_af = $stmt_af->get_result();

    if ($res_af->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'No tiene permiso para gestionar turnos de este afiliado (debe ser menor de 18 años, estar activo y pertenecer a su grupo familiar).'
        ]);
        exit;
    }

    // 🔹 PASO 4: Obtener el nro_documento del afiliado para buscar su id_paciente
    $afiliado_data = $res_af->fetch_assoc();
    $nro_doc_afiliado = $afiliado_data['numero_documento'];
    $stmt_af->close();
    
    // 🔹 PASO 5: Buscar el id_paciente del afiliado (si existe)
    $stmt_pac_af = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nro_documento = ? LIMIT 1");
    $stmt_pac_af->bind_param("s", $nro_doc_afiliado);
    $stmt_pac_af->execute();
    $res_pac_af = $stmt_pac_af->get_result();
    
    if ($res_pac_af->num_rows > 0) {
        // El afiliado tiene registro en pacientes
        $id_paciente_beneficiario = (int)$res_pac_af->fetch_assoc()['id_paciente'];
    } else {
        // El afiliado NO tiene registro en pacientes, crear uno
        $stmt_crear = $conn->prepare("
            INSERT INTO pacientes (nro_documento, tipo_documento, fecha_nacimiento)
            SELECT numero_documento, 'DNI', fecha_nacimiento
            FROM afiliados
            WHERE id = ?
        ");
        $stmt_crear->bind_param("i", $id_beneficiario);
        
        if (!$stmt_crear->execute()) {
            echo json_encode(['success' => false, 'error' => 'No se pudo crear el registro del paciente para el afiliado.']);
            exit;
        }
        
        $id_paciente_beneficiario = $conn->insert_id;
        $stmt_crear->close();
    }
    
    $stmt_pac_af->close();
}

    $idPaciente = $id_paciente_beneficiario;

    // === Validar formato de fecha y hora ===
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    $h = DateTime::createFromFormat('H:i:s', $horaInicio) ?: DateTime::createFromFormat('H:i', $horaInicio);
    if (!$d || !$h) {
        echo json_encode(['success' => false, 'error' => 'Formato de fecha u hora inválido.']);
        exit;
    }
    $horaInicioNorm = $h->format('H:i:s');

    // === Iniciar transacción ===
    $conn->begin_transaction();

    // === 2️⃣ Evitar duplicados confirmados ===
    // Validamos que no exista un turno CONFIRMADO o PENDIENTE para el mismo estudio
    // Tanto para el titular como para afiliados menores
    
    if ($tipo_beneficiario === 'a') {
        // 👶 Para AFILIADO MENOR: Buscar turnos activos del afiliado
        $sqlDup = "
            SELECT COUNT(*) AS cnt
            FROM turnos t
            WHERE t.id_afiliado = ?
              AND t.id_estudio = ?
              AND t.id_estado IN (
                  SELECT id_estado 
                  FROM estados 
                  WHERE nombre_estado IN ('confirmado', 'pendiente')
              )
        ";
        $stmtDup = $conn->prepare($sqlDup);
        $stmtDup->bind_param("ii", $id_beneficiario, $idEstudio);
        
    } else {
        // 👤 Para TITULAR: Buscar turnos activos del paciente titular
        $sqlDup = "
            SELECT COUNT(*) AS cnt
            FROM turnos t
            WHERE t.id_paciente = ?
              AND t.id_afiliado IS NULL
              AND t.id_estudio = ?
              AND t.id_estado IN (
                  SELECT id_estado 
                  FROM estados 
                  WHERE nombre_estado IN ('confirmado', 'pendiente')
              )
        ";
        $stmtDup = $conn->prepare($sqlDup);
        $stmtDup->bind_param("ii", $paciente_id_sesion, $idEstudio);
    }
    
    $stmtDup->execute();
    $cntDup = $stmtDup->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmtDup->close();

    if ($cntDup > 0) {
        $conn->rollback();
        
        $mensaje_error = ($tipo_beneficiario === 'a') 
            ? 'Ya existe un turno activo para este afiliado con este estudio. Debe cancelarlo o esperar a que sea atendido antes de solicitar uno nuevo.'
            : 'Ya tiene un turno activo para este estudio. Debe cancelarlo o esperar a que sea atendido antes de solicitar uno nuevo.';
        
        echo json_encode([
            'success' => false,
            'error' => $mensaje_error
        ]);
        exit;
    }

    // === 3️⃣ Buscar turno disponible en agenda ===
    $sqlAgenda = "
        SELECT a.id_agenda, a.id_tecnico, r.id_recurso
        FROM agenda a
        JOIN tecnico_estudio te ON te.id_tecnico = a.id_tecnico
        LEFT JOIN recursos r ON r.id_recurso = a.id_recurso
        WHERE te.id_estudio = ? AND a.fecha = ? AND a.hora_inicio = ? AND a.disponible = 1
        LIMIT 1 FOR UPDATE
    ";
    $stmt = $conn->prepare($sqlAgenda);
    $stmt->bind_param("iss", $idEstudio, $fecha, $horaInicioNorm);
    $stmt->execute();
    $resAgenda = $stmt->get_result();

    if ($resAgenda->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Turno no disponible o ya reservado.']);
        exit;
    }

    $agenda = $resAgenda->fetch_assoc();
    $idAgenda  = (int)$agenda['id_agenda'];
    $idTecnico = (int)$agenda['id_tecnico'];
    $idRecurso = isset($agenda['id_recurso']) ? (int)$agenda['id_recurso'] : null;

    // === 4️⃣ Marcar agenda como no disponible ===
    $updateAgenda = $conn->prepare("UPDATE agenda SET disponible = 0 WHERE id_agenda = ?");
    $updateAgenda->bind_param("i", $idAgenda);
    if (!$updateAgenda->execute() || $updateAgenda->affected_rows === 0) {
        throw new Exception('No se pudo actualizar la disponibilidad de la agenda.');
    }

    // === 5️⃣ Obtener estado "confirmado" ===
    $resEstado = $conn->query("SELECT id_estado FROM estados WHERE nombre_estado = 'confirmado' LIMIT 1");
    if (!$resEstado || $resEstado->num_rows === 0) {
        throw new Exception('Estado "confirmado" no configurado en la base de datos.');
    }
    $idEstadoConfirmado = (int)$resEstado->fetch_assoc()['id_estado'];

    // === 6️⃣ Insertar turno ===
$observaciones = ($tipo_beneficiario === 'a') 
    ? 'Turno de estudio solicitado por titular del grupo familiar' 
    : '';

// 🔹 Determinar valores correctos para la inserción
$id_paciente_insert = null;
$id_afiliado_turno = null;
$id_titular_turno = null;

if ($tipo_beneficiario === 'a') {
    // 👶 Es un afiliado menor
    $id_paciente_insert = $paciente_id_sesion;  // ID del TITULAR (quien solicita)
    $id_afiliado_turno = $id_beneficiario;      // ID del afiliado menor
    $id_titular_turno = null;                   // NULL porque id_paciente ya es el titular
} else {
    // 👤 Es el titular para sí mismo
    $id_paciente_insert = $paciente_id_sesion;  // ID del titular
    $id_afiliado_turno = null;
    $id_titular_turno = null;
}

$sqlInsert = "
    INSERT INTO turnos
        (id_paciente, id_afiliado, id_titular, id_tecnico, id_estudio, 
         id_orden_medica, id_recurso, fecha, hora, id_estado, copago, observaciones)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?)
";

$stmtIns = $conn->prepare($sqlInsert);
$stmtIns->bind_param(
    "iiiiiiissis",
    $id_paciente_insert,   // ✅ Siempre es el ID del titular
    $id_afiliado_turno,    // NULL si es titular, ID del menor si es afiliado
    $id_titular_turno,     // Siempre NULL (redundante con id_paciente)
    $idTecnico,
    $idEstudio,
    $idOrdenMedica,
    $idRecurso,
    $fecha,
    $horaInicioNorm,
    $idEstadoConfirmado,
    $observaciones
);

if (!$stmtIns->execute()) {
    throw new Exception('Error al insertar turno: ' . $stmtIns->error);
}

$idTurno = $conn->insert_id;

    // === 7️⃣ Obtener datos del turno para correo ===
if ($tipo_beneficiario === 'a') {
    // 👶 Para afiliados menores: datos del titular + info del menor
    $sqlDatos = "
        SELECT 
            t.fecha, t.hora, t.copago, t.observaciones,
            e.nombre AS nombre_estudio,
            e.instrucciones,
            r.nombre AS nombre_recurso, r.tipo AS tipo_recurso,
            s.nombre AS nombre_sede, s.direccion AS direccion_sede,
            
            -- Datos del TITULAR (quien recibe el email)
            u_titular.nombre AS paciente_nombre,
            u_titular.apellido AS paciente_apellido,
            u_titular.email AS paciente_email,
            
            -- Datos del MENOR (para el contenido del email)
            a_menor.nombre AS menor_nombre,
            a_menor.apellido AS menor_apellido,
            TIMESTAMPDIFF(YEAR, a_menor.fecha_nacimiento, CURDATE()) AS menor_edad
            
        FROM turnos t
        JOIN estudios e ON t.id_estudio = e.id_estudio
        LEFT JOIN recursos r ON t.id_recurso = r.id_recurso
        LEFT JOIN sedes s ON r.id_sede = s.id_sede
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        
        -- Obtener datos del afiliado menor
        JOIN afiliados a_menor ON a_menor.numero_documento = p.nro_documento
        
        -- Obtener datos del titular
        JOIN afiliados a_titular ON a_titular.id = a_menor.id_titular
        JOIN pacientes p_titular ON p_titular.nro_documento = a_titular.numero_documento
        JOIN usuarios u_titular ON u_titular.id_usuario = p_titular.id_usuario
        
        WHERE t.id_turno = ?
        LIMIT 1
    ";
} else {
    // 👤 Para titular: datos normales
    $sqlDatos = "
        SELECT 
            t.fecha, t.hora, t.copago, t.observaciones,
            e.nombre AS nombre_estudio,
            e.instrucciones,
            r.nombre AS nombre_recurso, r.tipo AS tipo_recurso,
            s.nombre AS nombre_sede, s.direccion AS direccion_sede,
            up.nombre AS paciente_nombre,
            up.apellido AS paciente_apellido,
            up.email AS paciente_email,
            NULL AS menor_nombre,
            NULL AS menor_apellido,
            NULL AS menor_edad
        FROM turnos t
        JOIN estudios e ON t.id_estudio = e.id_estudio
        LEFT JOIN recursos r ON t.id_recurso = r.id_recurso
        LEFT JOIN sedes s ON r.id_sede = s.id_sede
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        JOIN usuarios up ON p.id_usuario = up.id_usuario
        WHERE t.id_turno = ?
        LIMIT 1
    ";
}

$stmtDatos = $conn->prepare($sqlDatos);
$stmtDatos->bind_param("i", $idTurno);
$stmtDatos->execute();
$turno = $stmtDatos->get_result()->fetch_assoc();

if (!$turno) {
    // Si aún así falla, registrar error pero no bloquear
    error_log("⚠️ No se pudo obtener datos del turno ID $idTurno para enviar email");
    $conn->commit();
    echo json_encode([
        'success' => true,
        'mensaje' => '✅ Turno confirmado (sin notificación por email)',
        'id_turno' => $idTurno
    ]);
    exit;
}

$conn->commit();

// === 8️⃣ Enviar notificación por correo ===
$recomendaciones = ["Presentarse 15 minutos antes del horario asignado."];
if (!empty($turno['instrucciones'])) $recomendaciones[] = $turno['instrucciones'];
if (!empty($turno['observaciones'])) $recomendaciones[] = $turno['observaciones'];

// 🔹 Construir asunto del email
$destinatario_display = ($tipo_beneficiario === 'a' && !empty($turno['menor_nombre']))
    ? "{$turno['menor_nombre']} {$turno['menor_apellido']} ({$turno['menor_edad']} años)"
    : "{$turno['paciente_nombre']} {$turno['paciente_apellido']}";

$datosCorreo = [
    'email'           => $turno['paciente_email'],
    'nombre'          => $turno['paciente_nombre'],
    'apellido'        => $turno['paciente_apellido'],
    'fecha'           => $turno['fecha'],
    'hora'            => substr($turno['hora'], 0, 5),
    'especialidad'    => $turno['nombre_estudio'],
    'profesional'     => ucfirst($turno['tipo_recurso'] ?? 'Técnico') . ': ' . ($turno['nombre_recurso'] ?? 'Asignado'),
    'direccion'       => $turno['direccion_sede'] ?? 'Dirección no disponible',
    'copago'          => '$' . number_format($turno['copago'] ?? 0, 2),
    'recomendaciones' => $recomendaciones
];

// 🔹 Si es un menor, agregar info adicional al email
if ($tipo_beneficiario === 'a' && !empty($turno['menor_nombre'])) {
    $datosCorreo['observaciones_extra'] = "Este turno fue solicitado para {$destinatario_display}, miembro de su grupo familiar.";
}

try {
    enviarNotificacion('turno_estudio', $datosCorreo);
} catch (Exception $e) {
    error_log("⚠️ Error al enviar email: " . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'mensaje' => '✅ Turno de estudio confirmado y notificación enviada.',
    'id_turno' => $idTurno,
    'para' => $destinatario_display
]);

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->rollback();
    }
    error_log("❌ Error en confirmarTurnoEstudio.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '❌ ' . $e->getMessage()
    ]);
}
exit;
?>


