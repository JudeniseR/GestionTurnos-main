<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../Persistencia/conexionBD.php';
require_once __DIR__ . '/../../librerias/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../librerias/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../librerias/PHPMailer/src/Exception.php';

function configurarMailer(): PHPMailer
{/* Configura y devuelve un objeto PHPMailer listo para usar*/
    $mail = new PHPMailer(true);
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'xxjavicaixx@gmail.com';
        $mail->Password   = 'ycejgbxqrhueamqf';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->ContentType = 'text/html; charset=UTF-8';
        $mail->setFrom('xxjavicaixx@gmail.com', 'no-responder-gestion-turnos');
        $mail->isHTML(true);
    } catch (Exception $e) {
        error_log("Error configurando PHPMailer: {$mail->ErrorInfo}");
    }
    return $mail;
}

function registrarNotificacion(string $accion, array $datos, string $estado, ?string $error = null, $conn = null)
{
    $closeConn = false;
    if ($conn === null) {
        if (!class_exists('ConexionBD')) {
            error_log("registrarNotificacion: no existe ConexionBD y no se pasó conexión.");
            return false;
        }
        $conn = ConexionBD::conectar();
        $closeConn = true;
    }

    try {
        // Obtener id_tipo_notificacion según el código ($accion)
        $stmt = $conn->prepare("SELECT id_tipo_notificacion FROM tipo_notificaciones WHERE nombre = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception("Error prepare tipo_notificacion: " . $conn->error);
        }
        $stmt->bind_param("s", $accion);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (empty($row['id_tipo_notificacion'])) {
            error_log("registrarNotificacion: tipo_notificacion no encontrado para código '{$accion}'");
            if ($closeConn) $conn->close();
            return false;
        }
        $idTipo = (int)$row['id_tipo_notificacion'];

        $id_turno    = $datos['id_turno']    ?? null;
        $id_paciente = $datos['id_paciente'] ?? null;
        $id_usuario  = $datos['id_usuario']  ?? null;

        // Intentar deducir id_paciente o id_usuario a partir de email, token, etc.
        if (!$id_paciente && !empty($datos['email'])) {
            $stmt = $conn->prepare("SELECT id_paciente FROM pacientes WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $datos['email']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($fila = $res->fetch_assoc()) {
                $id_paciente = (int)$fila['id_paciente'];
            }
            $stmt->close();
        }

        if (!$id_usuario && isset($_SESSION['id_usuario'])) {
            $id_usuario = (int)$_SESSION['id_usuario'];
        }

        if (!$id_turno && isset($datos['token_turno'])) {
            $stmt = $conn->prepare("SELECT id_turno FROM turnos WHERE token = ? LIMIT 1");
            $stmt->bind_param("s", $datos['token_turno']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($fila = $res->fetch_assoc()) {
                $id_turno = (int)$fila['id_turno'];
            }
            $stmt->close();
        }

        // ---- Datos generales ----
        $email  = $datos['email'] ?? ($datos['email_destino'] ?? '');
        $asunto = $datos['asunto'] ?? ($datos['subject'] ?? ($datos['titulo'] ?? ''));
        $cuerpo = $datos['cuerpo'] ?? ($datos['body'] ?? ($datos['mensaje'] ?? ''));

        // ---- Inserción ----
        $sql = "INSERT INTO notificaciones 
                (id_turno, id_paciente, id_usuario, id_tipo_notificacion, email_destino, asunto, cuerpo, estado, mensaje_error)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("prepare insert notificaciones: " . $conn->error);

        $mensajeError = $error ?? null;
        $stmt->bind_param(
            "iiiisssss",
            $id_turno,
            $id_paciente,
            $id_usuario,
            $idTipo,
            $email,
            $asunto,
            $cuerpo,
            $estado,
            $mensajeError
        );

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            error_log("registrarNotificacion: error al insertar notificacion: " . $err);
            if ($closeConn) $conn->close();
            return false;
        }

        $insertId = $stmt->insert_id;
        $stmt->close();
        if ($closeConn) $conn->close();

        return $insertId;

    } catch (Exception $e) {
        error_log("registrarNotificacion EXCEPTION: " . $e->getMessage());
        if (isset($stmt) && $stmt) $stmt->close();
        if ($closeConn && isset($conn)) $conn->close();
        return false;
    }
}

function enviarNotificacion(string $accion, array $datos): bool
{
    $mail = configurarMailer();
    $conn = ConexionBD::conectar();

    if (empty($datos['email'])) {
        error_log("No se especificó email para la acción $accion");
        return false;
    }
    // Construir nombre completo
    $nombreCompleto = trim(($datos['nombre'] ?? '') . ' ' . ($datos['apellido'] ?? ''));
    $mail->addAddress($datos['email'], $nombreCompleto);

    // Seleccionar plantilla según acción
    switch ($accion) {
        case 'registro':
            $mail->Subject = "Registro exitoso en Sistema de Gestión Turnos";
            $mail->Body    = plantillaRegistro($datos);
            break;
        case 'login':
            $mail->Subject = 'Notificación de inicio de sesión en tu cuenta';
            $mail->Body    = plantillaLogin($datos);
            break;
        case 'recupero':
            $mail->Subject = 'Recuperar acceso';
            $mail->Body    = plantillaRecupero($datos);
            break;
        case 'restablecido':
            $mail->Subject = '🔐 Contraseña actualizada correctamente';
            $mail->Body    = plantillaRestablecido($datos);
            break;
        case 'turno_medico':
            $mail->Subject = 'Confirmación de turno médico ✅';
            $mail->Body    = plantillaTurnoMedico($datos);
            break;
        case 'turno_estudio':
            $mail->Subject = 'Confirmación de turno estudio ✅';
            $mail->Body    = plantillaTurnoEstudio($datos);
            break;
        case 'recordatorio_turno':
            $mail->Subject = 'Recordatorio de turno';
            $mail->Body    = plantillaRecordatorioTurno($datos);
            break;
        case 'cancelar_turno':
            $mail->Subject = 'Turno cancelado';
            $mail->Body    = plantillaCancelarTurno($datos);
            break;
        default:
            throw new Exception("Acción '$accion' no soportada.");
    }
    // Cargar datos para persistencia
    $datos['asunto'] = $mail->Subject;
    $datos['cuerpo'] = $mail->Body;

    try {
        $enviado = $mail->send();
        registrarNotificacion(
            $accion,
            $datos,
            $enviado ? 'enviado' : 'error',
            $enviado ? null : $mail->ErrorInfo,
            $conn
        );
        return $enviado;
    } catch (Exception $e) {
        error_log("❌ Error enviando notificación ($accion): {$mail->ErrorInfo}");
        registrarNotificacion($accion, $datos, 'error', $mail->ErrorInfo ?? $e->getMessage(), $conn);
        return false;
    } finally {
        $conn->close();
    }
}

/* Plantillas HTML de correo */

function plantillaRegistro($d)
{
    $nombre = htmlspecialchars($d['nombre'] ?? '');
    $apellido = htmlspecialchars($d['apellido'] ?? '');
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $url_login = $protocol . "://" . $host . "/interfaces/Paciente/login.php";
    return "
        <div style='font-family:Arial,sans-serif;padding:20px;background:#f9f9f9'>
          <div style='max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden'>
            <div style='background:#1976d2;color:#fff;padding:20px;text-align:center'>
              <h2 style='margin:0'>Bienvenido/a, {$nombre} {$apellido} 🎉</h2>
            </div>
            <div style='padding:20px;color:#333'>
              <p>Tu registro fue exitoso. Ya podés iniciar sesión.</p>
              <div style='text-align:center;margin:30px 0'>
                <a href='{$url_login}' style='background:#4CAF50;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none'>Iniciar sesión</a>
              </div>
            </div>
            <div style='background:#f5f5f5;color:#777;padding:10px;text-align:center;font-size:12px'>
              © " . date('Y') . " Sistema de Gestión Turnos
            </div>
          </div>
        </div>";
}

function plantillaLogin($d)
{
    $nombre = htmlspecialchars($d['nombre'] ?? '');
    $apellido = htmlspecialchars($d['apellido'] ?? '');
    $email = htmlspecialchars($d['email'] ?? '');
    $fechaHora = date("d/m/Y H:i:s");
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host     = $_SERVER['HTTP_HOST'];
    $url_generar = $protocol . "://" . $host . "/Logica/General/generarTokenCambio.php?email=" . urlencode($email);

    return "
        <head><meta charset='UTF-8'></head>
                    <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;'>
                        <div style='max-width: 600px; margin: auto; background: #ffffff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); overflow: hidden;'>
                            <div style='background: #1976d2; padding: 20px; text-align: center; color: #fff;'>
                                <h2 style='margin: 0;'>Sistema de Gestión Turnos</h2>
                            </div>
                            <div style='padding: 20px; color: #333;'>
                                <h3 style='margin-top: 0;'>Hola, {$nombre} {$apellido} 👋</h3>
                                <p>Se inició sesión en tu cuenta:</p>
                                <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                                    <tr>
                                        <td style='padding: 8px; font-weight: bold; background: #f0f0f0;'>Fecha y hora</td>
                                        <td style='padding: 8px;'>{$fechaHora}</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 8px; font-weight: bold; background: #f0f0f0;'>Dirección IP</td>
                                        <td style='padding: 8px;'>{$ip}</td>
                                    </tr>
                                </table>
                                <p style='margin-top: 20px;'>Si fuiste vos, no hace falta que hagas nada.</p>
                                <p style='color: #d32f2f; font-weight: bold;'>Si no reconocés esta actividad, cambiá tu contraseña ahora.</p>
                                <div style='text-align: center; margin-top: 20px;'>
                                    <a href='{$url_generar}' 
                                       style='background: #1976d2; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 5px;'>
                                       Cambiar Contraseña
                                    </a>
                                </div>
                            </div>
                            <div style='background: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; color: #777;'>
                                © " . date("Y") . " Sistema de Gestión Turnos — Mensaje automático.
                            </div>
                        </div>
                    </div>";
}

function plantillaRecupero($d)
{
    $nombre = htmlspecialchars($d['nombre'] ?? '');
    $apellido = htmlspecialchars($d['apellido'] ?? '');
    $token = htmlspecialchars($d['token'] ?? '');

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $url_reset = $protocol . "://" . $host . "/interfaces/resetPassword.php?token=" . $token;
    return "
        <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding:20px;'>
                <div style='max-width:600px; margin:auto; background:#ffffff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); overflow:hidden;'>
                    <div style='background:#1976d2; padding:20px; text-align:center; color:#fff;'>
                        <h2 style='margin:0;'>Recuperación de Acceso 🔑</h2>
                    </div>
                    <div style='padding:20px; color:#333;'>
                        <p>Hola <b>{$nombre} {$apellido}</b>,</p>
                        <p>Hemos recibido una solicitud para <b>restablecer tu contraseña</b> en el 
                        <b>Sistema de Gestión de Turnos</b>.</p>
                        <p>Si realizaste esta solicitud, haz clic en el botón a continuación para crear una nueva contraseña:</p>                        
                        <div style='text-align:center; margin:30px 0;'>
                            <a href='{$url_reset}' style='background: #E53935; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                                Restablecer Contraseña
                            </a>
                        </div>
                        <p style='font-size:14px; color:#555;'>⚠️ Este enlace es válido solo por <b>1 hora</b>. Si no solicitaste este cambio, simplemente ignora este mensaje.</p>
                    </div>                    
                    <div style='background:#f5f5f5; padding:10px; text-align:center; font-size:12px; color:#777;'>
                        © " . date("Y") . " Sistema de Gestión Turnos - Este es un mensaje automático, no respondas a este correo.
                    </div>
                </div>
            </div>";
}

function plantillaRestablecido($d)
{
    $nombre = htmlspecialchars($d['nombre'] ?? '');
    $apellido = htmlspecialchars($d['apellido'] ?? '');
    return "
        <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding:20px;'>
             <div style='max-width:600px; margin:auto; background:#ffffff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); overflow:hidden;'>
                 <div style='background:#1976D2; padding:20px; text-align:center; color:#fff;'>
                     <h2 style='margin:0;'>Confirmación de Cambio de Contraseña 🔐</h2>
                 </div>
                 <div style='padding:20px; color:#333;'>
                     <p>Estimado/a <b>{$nombre}</b>, {$apellido}</p>
                     <p>Tu contraseña ha sido actualizada correctamente ✅.</p>                    
                     <p style='margin-top:20px;'>Gracias por confiar en nosotros 🙌.</p>
                 </div>
                 <div style='background:#f5f5f5; padding:10px; text-align:center; font-size:12px; color:#777;'>
                     © " . date("Y") . " Sistema de Gestión Turnos - Este es un mensaje automático, no respondas a este correo.
                 </div>
             </div>
         </div>";
}

function plantillaTurnoMedico($d)
{

    $nombreCompleto = htmlspecialchars($d['nombreCompleto'] ?? '');
    $fecha = htmlspecialchars($d['fecha'] ?? '');
    $hora = htmlspecialchars($d['hora'] ?? '');
    $especialidad = htmlspecialchars($d['especialidad'] ?? '');
    $profesional = htmlspecialchars($d['profesional'] ?? '');
    $direccion = htmlspecialchars($d['direccion'] ?? '');
    $copago = htmlspecialchars($d['copago'] ?? '');
    $recomendaciones = $d['recomendaciones'] ?? [];
    $recsHTML = implode("<br>", array_map(fn($r) => nl2br(htmlspecialchars($r)), $recomendaciones));
    $recomendaciones = ["Presentarse 15 minutos antes del horario."];

    return "
        <div style='font-family: Arial,sans-serif; background-color:#f9f9f9; padding:20px;'>
            <div style='max-width:600px;margin:auto;background:#ffffff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);'>
                <div style='background:#388E3C; padding:20px; text-align:center; color:#fff;'>
                    <h2 style='margin:0;'>Confirmación de Turno Médico 📅</h2>
                </div>
                <div style='padding:20px; color:#333;'>
                    <p>Estimado/a <b>{$nombreCompleto}</b>,</p>
                    <p>Su turno ha sido confirmado ✅:</p>
                    <ul style='list-style:none; padding:0;'>
                        <li><b>Especialidad:</b> {$especialidad}</li>
                        <li><b>Fecha:</b> {$fecha}</li>
                        <li><b>Hora:</b> {$hora}</li>
                        <li><b>Profesional:</b> {$profesional}</li>
                        <li><b>Dirección:</b> {$direccion}</li>
                        <li><b>Copago:</b> {$copago}</li>
                    </ul>
                    <p><b>Recomendaciones:</b></p>
                    <p style='background:#f5f5f5; padding:15px; border-radius:5px;'>{$recsHTML}</p>
                    <p style='margin-top:20px;'>Gracias por confiar en nosotros 🙌</p>
                </div>
                <div style='background:#f5f5f5; padding:10px; text-align:center; font-size:12px; color:#777;'>
                    © " . date("Y") . " Sistema de Gestión Turnos – Mensaje automático.
                </div>
            </div>
        </div>";
}

function plantillaTurnoEstudio($d)
{

    $nombreCompleto = htmlspecialchars($d['nombreCompleto'] ?? '');
    $fecha = htmlspecialchars($d['fecha'] ?? '');
    $hora = htmlspecialchars($d['hora'] ?? '');
    $estudio = htmlspecialchars($d['estudio'] ?? '');
    $profesional = htmlspecialchars($d['profesional'] ?? '');
    $direccion = htmlspecialchars($d['direccion'] ?? '');
    $copago = htmlspecialchars($d['copago'] ?? '');
    $recomendaciones = $d['recomendaciones'] ?? [];
    // $recsHTML = implode("<br>", array_map('htmlspecialchars', $recomendaciones));
    $recsHTML = '<ul style="padding-left:20px;">' .
        implode('', array_map(fn($r) => "<li>" . htmlspecialchars($r) . "</li>", $recomendaciones)) .
        '</ul>';

    return "
        <div style='font-family: Arial,sans-serif; background-color:#f9f9f9; padding:20px;'>
            <div style='max-width:600px;margin:auto;background:#ffffff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.1);'>
                <div style='background:#0288D1; padding:20px; text-align:center; color:#fff;'>
                    <h2 style='margin:0;'>Confirmación de Estudio Médico 📑</h2>
                </div>
                <div style='padding:20px; color:#333;'>
                    <p>Estimado/a <b>{$nombreCompleto}</b>,</p>
                    <p>Su turno para estudio ha sido confirmado ✅:</p>
                    <ul style='list-style:none; padding:0;'>
                        <li><b>Estudio:</b> {$estudio}</li>
                        <li><b>Fecha:</b> {$fecha}</li>
                        <li><b>Hora:</b> {$hora}</li>
                        <li><b>Profesional/Equipo:</b> {$profesional}</li>
                        <li><b>Dirección:</b> {$direccion}</li>
                        <li><b>Copago:</b> {$copago}</li>
                    </ul>
                    <p><b>Recomendaciones:</b></p>
                    <p style='background:#f5f5f5; padding:15px; border-radius:5px;'>{$recsHTML}</p>
                    <p style='margin-top:20px;'>Gracias por confiar en nosotros 🙌</p>
                </div>
                <div style='background:#f5f5f5; padding:10px; text-align:center; font-size:12px; color:#777;'>
                    © " . date("Y") . " Sistema de Gestión Turnos – Mensaje automático.
                </div>
            </div>
        </div>";
}

function plantillaRecordatorioTurno($d)
{
    $protocol = 'http';
    $host = '192.168.0.35'; //agregar ip (hostname -I) ya que cron Job no tiene contexto web al ejecutarse en MV
    $url_cancelar = $protocol . "://" . $host . "/interfaces/Paciente/login.php";

    $nombre = htmlspecialchars($d['nombre'] ?? '');
    $apellido = htmlspecialchars($d['apellido'] ?? '');
    $fecha = htmlspecialchars($d['fecha'] ?? '');
    $hora = htmlspecialchars($d['hora'] ?? '');
    $tipo = htmlspecialchars($d['tipo'] ?? '');
    $detalle = htmlspecialchars($d['detalle'] ?? '');
    $profesional = htmlspecialchars($d['profesional'] ?? '');
    $direccion = htmlspecialchars($d['direccion'] ?? '');

    return "
        <div style='font-family: Arial, sans-serif; background:#f5f5f5; padding:20px;'>
            <div style='max-width:600px; margin:auto; background:#fff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1);'>
                <div style='background:#1976D2; padding:20px; text-align:center; color:#fff;'>
                    <h2 style='margin:0;'>Recordatorio de Turno ⏰</h2>
                </div>
                <div style='padding:20px; color:#333;'>
                    <p>Estimado/a <b>{$nombre} {$apellido}</b>,</p>
                    <p>Le recordamos que tiene un turno programado dentro de las próximas <b>48 horas</b>:</p>
                    <ul style='list-style:none; padding:0;'>
                        <li><b>Tipo:</b> {$tipo}</li>
                        <li><b>Detalle:</b> {$detalle}</li>
                        <li><b>Fecha:</b> {$fecha}</li>
                        <li><b>Hora:</b> {$hora}</li>
                        <li><b>Profesional/Equipo:</b> {$profesional}</li>
                        <li><b>Dirección:</b> {$direccion}</li>
                    </ul>
                     <div style='text-align:center; margin:25px 0;'>
                        <a href='{$url_cancelar}' style='display:inline-block; padding:12px 24px; background:#1976D2; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;'>
                            Cancelar turno
                        </a>
                    </div>

                    <p style='margin-top:20px; font-weight:bold; text-align:center; color:#444;'>
                        Recuerde que las cancelaciones solo se realizan a través del sistema.
                        <br><i>No se aceptan cancelaciones por otro medio.</i>
                    </p>
                </div>
                <div style='background:#f5f5f5; padding:10px; text-align:center; font-size:12px; color:#777; border-radius:0 0 8px 8px;'>
                    © " . date('Y') . " Sistema de Gestión de Turnos – Mensaje automático.
                </div>
            </div>
        </div>";
}

function plantillaCancelarTurno($d)
{

    $nombre = htmlspecialchars($d['nombre'] ?? '');
    $apellido = htmlspecialchars($d['apellido'] ?? '');
    $fecha = htmlspecialchars($d['fecha'] ?? '');
    $hora = htmlspecialchars($d['hora'] ?? '');
    $tipo = htmlspecialchars($d['tipo'] ?? '');
    $detalle = htmlspecialchars($d['detalle'] ?? '');
    $profesional = htmlspecialchars($d['profesional'] ?? '');
    $direccion = htmlspecialchars($d['direccion'] ?? '');

    return "
        <div style='font-family: Arial, sans-serif; background:#f9f9f9; padding:20px;'>
            <div style='max-width:600px; margin:auto; background:#fff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1);'>
                <div style='background:#D32F2F; padding:20px; text-align:center; color:#fff;'>
                    <h2 style='margin:0;'>Cancelación de Turno ❌</h2>
                </div>
                <div style='padding:20px; color:#333;'>
                    <p>Estimado/a <b>{$nombre} {$apellido}</b>,</p>
                    <p>Se ha <b>cancelado</b> su turno de <b>{$tipo}</b>:</p>
                    <ul style='list-style:none; padding:0;'>
                        <li><b>Detalle:</b> {$detalle}</li>
                        <li><b>Fecha:</b> {$fecha}</li>
                        <li><b>Hora:</b> {$hora}</li>
                        <li><b>Profesional/Equipo:</b> {$profesional}</li>
                        <li><b>Dirección:</b> {$direccion}</li>
                    </ul>
                    <p>Lamentamos los inconvenientes y esperamos atenderlo pronto.</p>
                </div>
                <div style='background:#f5f5f5; padding:10px; text-align:center; font-size:12px; color:#777;'>
                    © " . date('Y') . " Sistema de Gestión Turnos – Mensaje automático.
                </div>
            </div>
        </div>";
}
