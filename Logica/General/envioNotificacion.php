<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../../librerias/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../librerias/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../librerias/PHPMailer/src/Exception.php';

function enviarNotificacionTurno($conn, $turnoId) {
    // Detectar tipo de turno
    $sqlTipo = "SELECT estudio_id, medico_id FROM turnos WHERE id = ?";
    $stmt = $conn->prepare($sqlTipo);
    $stmt->bind_param("i", $turnoId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res['estudio_id']) {
        enviarNotificacionTurnoEstudio($conn, $turnoId);
    } elseif ($res['medico_id']) {
        enviarNotificacionTurnoMedico($conn, $turnoId);
    } else {
        error_log("⚠️ Tipo de turno no identificado para ID: $turnoId");
    }
}

function enviarNotificacionTurnoEstudio($conn, $turnoId) {
    $sql = "
        SELECT 
            t.fecha, t.hora, t.copago, t.observaciones,
            e.nombre AS nombre_estudio,
            e.requiere_acompaniante, e.requiere_ayuno, e.requiere_orden_medica,
            e.instrucciones_preparacion,
            r.nombre AS nombre_recurso, r.tipo AS tipo_recurso,
            s.nombre AS nombre_sede, s.direccion AS direccion_sede,
            p.nombre AS paciente_nombre, p.apellido AS paciente_apellido, p.email AS paciente_email
        FROM turnos t
        JOIN estudios e ON t.estudio_id = e.id
        JOIN recursos r ON t.recurso_id = r.id
        JOIN sedes s ON r.sede_id = s.id
        JOIN pacientes p ON t.paciente_id = p.id
        WHERE t.id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $turnoId);
    $stmt->execute();
    $turno = $stmt->get_result()->fetch_assoc();
    if (!$turno) return;

    $paciente_nombre = $turno['paciente_nombre'] . ' ' . $turno['paciente_apellido'];
    $paciente_email = $turno['paciente_email'];
    $fecha = $turno['fecha'];
    $hora = $turno['hora'];
    $titulo = "Estudio: " . $turno['nombre_estudio'];
    $profesional = ucfirst($turno['tipo_recurso']) . ": " . $turno['nombre_recurso'];
    $direccion = $turno['direccion_sede'];
    $copago = "$" . number_format($turno['copago'], 2);

    $recomendaciones = [];
    $recomendaciones[] = "Presentarse 15 minutos antes del horario.";
    if ($turno['requiere_ayuno']) $recomendaciones[] = "Debe concurrir en ayunas.";
    if ($turno['requiere_acompaniante']) $recomendaciones[] = "Debe asistir con un acompañante.";
    if ($turno['requiere_orden_medica']) $recomendaciones[] = "Debe traer la orden médica.";
    if (!empty($turno['instrucciones_preparacion'])) $recomendaciones[] = nl2br($turno['instrucciones_preparacion']);
    if (!empty($turno['observaciones'])) $recomendaciones[] = nl2br($turno['observaciones']);

    enviarCorreoPHPMailer($paciente_email, $paciente_nombre, $fecha, $hora, $titulo, $profesional, $direccion, $copago, $recomendaciones);
}

function enviarNotificacionTurnoMedico($conn, $turnoId) {
    $sql = "
        SELECT 
            t.fecha, t.hora, t.copago, t.observaciones,
            m.nombre AS medico_nombre, m.apellido AS medico_apellido,
            e.nombre_especialidad,
            s.nombre AS nombre_sede, s.direccion AS direccion_sede,
            p.nombre AS paciente_nombre, p.apellido AS paciente_apellido, p.email AS paciente_email
        FROM turnos t
        JOIN medicos m ON t.medico_id = m.id_medico
        JOIN medico_especialidad me ON me.id_medico = m.id_medico
        JOIN especialidades e ON e.id_especialidad = me.id_especialidad
        JOIN sedes s ON s.id = (SELECT sede_id FROM agenda_medica WHERE id_medico = m.id_medico LIMIT 1)
        JOIN pacientes p ON t.paciente_id = p.id
        WHERE t.id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $turnoId);
    $stmt->execute();
    $turno = $stmt->get_result()->fetch_assoc();
    if (!$turno) return;

    $paciente_nombre = $turno['paciente_nombre'] . ' ' . $turno['paciente_apellido'];
    $paciente_email = $turno['paciente_email'];
    $fecha = $turno['fecha'];
    $hora = $turno['hora'];
    $titulo = "Consulta médica - " . $turno['nombre_especialidad'];
    $profesional = "Dr. " . $turno['medico_nombre'] . ' ' . $turno['medico_apellido'];
    $direccion = $turno['direccion_sede'];
    $copago = "$" . number_format($turno['copago'], 2);

    $recomendaciones = [];
    $recomendaciones[] = "Presentarse 15 minutos antes del horario.";
    if (!empty($turno['observaciones'])) $recomendaciones[] = nl2br($turno['observaciones']);

    enviarCorreoPHPMailer($paciente_email, $paciente_nombre, $fecha, $hora, $titulo, $profesional, $direccion, $copago, $recomendaciones);
}

function enviarCorreoPHPMailer($email, $nombrePaciente, $fecha, $hora, $titulo, $profesional, $direccion, $copago, $recomendaciones) {
    $mensajeHTML = "
        <p>Estimado/a <strong>$nombrePaciente</strong>,</p>
        <p>Su turno ha sido confirmado:</p>
        <ul>
            <li><strong>$titulo</strong></li>
            <li><strong>Fecha:</strong> $fecha</li>
            <li><strong>Hora:</strong> $hora</li>
            <li><strong>Profesional:</strong> $profesional</li>
            <li><strong>Dirección:</strong> $direccion</li>
            <li><strong>Copago:</strong> $copago</li>
        </ul>
        <p><strong>Recomendaciones:</strong></p>
        <p>" . implode("<br>", $recomendaciones) . "</p>
        <p>Gracias por confiar en nosotros.</p>
    ";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ml1708437@gmail.com';
        $mail->Password   = 'vijrvdovvgxhpqli'; // https://myaccount.google.com/apppasswords?pli=1&rapt=AEjHL4MO0g7tQo4GtfVasgyNo8Dl9J1nS5pkskzeff_1S3y93AGYlXkMneaYqteDWSPiK606Z54GcYxhkOHyTnGxV5cHbWpwQXBVKdJr03uYHU1NByXX284
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('ml1708437@gmail.com', 'Clínica Central');
        $mail->addAddress($email, $nombrePaciente);
        $mail->isHTML(true);
        $mail->Subject = "Confirmación de turno";
        $mail->Body = $mensajeHTML;

        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar mail: " . $mail->ErrorInfo);
    }
}
