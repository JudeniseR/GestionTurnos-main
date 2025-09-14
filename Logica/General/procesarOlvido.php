<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('../../Persistencia/conexionBD.php');
require_once('../../librerias/PHPMailer/src/PHPMailer.php');
require_once('../../librerias/PHPMailer/src/SMTP.php');
require_once('../../librerias/PHPMailer/src/Exception.php');

$conn = ConexionBD::conectar();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Verificar que existe el correo y obtener paciente_id asociado
    $stmt = $conn->prepare("
        SELECT p.id 
        FROM pacientes p
        INNER JOIN perfiles pe ON p.id_perfil = pe.id_perfil
        WHERE pe.email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($paciente_id);

    if ($stmt->fetch()) {
        $stmt->close();

        // Generar token
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Guardar token
        $stmt = $conn->prepare("
            INSERT INTO recuperacion_password (paciente_id, token, fecha_expiracion) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iss", $paciente_id, $token, $expiracion);
        $stmt->execute();
        $stmt->close();

        // Construir URL dinámica para reset de contraseña
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $url_reset = $protocol . "://" . $host . "/interfaces/resetPassword.php?token=" . $token;

        //Enviar correo
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'ml1708437@gmail.com';
            $mail->Password   = 'vijrvdovvgxhpqli';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->ContentType = 'text/html; charset=UTF-8';
            $mail->setFrom('ml1708437@gmail.com', 'no-responder-gestion-turnos');            
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Recuperar acceso";
            $mail->Body = "
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
                    <div style='background:#f5f5f5; padding:10px; text-align:center; 
                                font-size:12px; color:#777;'>
                        © " . date("Y") . " Sistema de Gestión Turnos - Este es un mensaje automático, no respondas a este correo.
                    </div>
                </div>
            </div>";
            $mail->send();
        } catch (Exception $e) {
            echo "❌ Error al enviar correo: {$mail->ErrorInfo}";
        }
    }

    echo "<script>alert('Si tu correo está registrado, recibirás un enlace para restablecer tu contraseña.'); window.location.href='../../index.php';</script>";
}
