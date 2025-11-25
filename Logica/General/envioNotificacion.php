<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../../librerias/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../librerias/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../librerias/PHPMailer/src/Exception.php';

function enviarNotificacionTurno($conn, $turnoId) {
    // Detectar tipo de turno
    $sqlTipo = "SELECT id_estudio, id_medico FROM turnos WHERE id_turno = ?";
    $stmt = $conn->prepare($sqlTipo);
    $stmt->bind_param("i", $turnoId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res['id_estudio']) {
        enviarNotificacionTurnoEstudio($conn, $turnoId);
    } elseif ($res['id_medico']) {
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
            um.nombre AS medico_nombre, um.apellido AS medico_apellido,
            e.nombre_especialidad,
            s.nombre AS nombre_sede, s.direccion AS direccion_sede,
            up.nombre AS paciente_nombre, up.apellido AS paciente_apellido, up.email AS paciente_email
        FROM turnos t
        JOIN medicos m ON t.id_medico = m.id_medico
        JOIN usuarios um ON m.id_usuario = um.id_usuario
        JOIN medico_especialidad me ON me.id_medico = m.id_medico
        JOIN especialidades e ON me.id_especialidad = e.id_especialidad
        JOIN agenda a ON a.id_medico = m.id_medico
        JOIN recursos r ON a.id_recurso = r.id_recurso
        JOIN sedes s ON r.id_sede = s.id_sede
        JOIN pacientes p ON t.id_paciente = p.id_paciente
        JOIN usuarios up ON p.id_usuario = up.id_usuario
        WHERE t.id_turno = ?
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

    $recomendaciones = ["Presentarse 15 minutos antes del horario."];
    if (!empty($turno['observaciones'])) {
        $recomendaciones[] = nl2br($turno['observaciones']);
    }

    enviarCorreoPHPMailer(
        $paciente_email,
        $paciente_nombre,
        $fecha,
        $hora,
        $titulo,
        $profesional,
        $direccion,
        $copago,
        $recomendaciones
    );
}

function enviarCorreoPHPMailer($email, $nombrePaciente, $fecha, $hora, $titulo, $profesional, $direccion, $copago, $recomendaciones) {
    $mensajeHTML = "
        <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding:20px;'>
            <div style='max-width:600px; margin:auto; background:#ffffff; border-radius:8px; 
                        box-shadow:0 2px 6px rgba(0,0,0,0.1); overflow:hidden;'>             
                <div style='background:#388E3C; padding:20px; text-align:center; color:#fff;'>
                    <h2 style='margin:0;'>Confirmación de Turno 📅</h2>
                </div>                
                <div style='padding:20px; color:#333;'>
                    <p>Estimado/a <b>{$nombrePaciente}</b>,</p>
                    <p>Su turno ha sido confirmado con éxito ✅:</p>

                    <ul style='list-style:none; padding:0; margin:20px 0;'>
                        <li><b>{$titulo}</b></li>
                        <li><b>Fecha:</b> {$fecha}</li>
                        <li><b>Hora:</b> {$hora}</li>
                        <li><b>Profesional:</b> {$profesional}</li>
                        <li><b>Dirección:</b> {$direccion}</li>
                        <li><b>Copago:</b> {$copago}</li>
                    </ul>

                    <p><b>Recomendaciones:</b></p>
                    <p style='background:#f5f5f5; padding:15px; border-radius:5px; color:#555;'>
                        " . implode("<br>", $recomendaciones) . "
                    </p>

                    <p style='margin-top:20px;'>Gracias por confiar en nosotros. 🙌</p>
                </div>                
                <div style='background:#f5f5f5; padding:10px; text-align:center; 
                            font-size:12px; color:#777;'>
                    © " . date("Y") . " Sistema de Gestión Turnos - 
                    Este es un mensaje automático, no respondas a este correo.
                </div>
            </div>
        </div>";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'xxjavicaixx@gmail.com';
        $mail->Password   = 'ycejgbxqrhueamqf';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->ContentType = 'text/html; charset=UTF-8';        
        $mail->setFrom('xxjavicaixx@gmail.com', 'no-responder-gestion-turnos');
        $mail->addAddress($email, $nombrePaciente);
        $mail->isHTML(true);
        $mail->Subject = "Confirmación de turno";
        $mail->Body = $mensajeHTML;

        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar mail: " . $mail->ErrorInfo);
    }
}