<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('../../Persistencia/conexionBD.php');
require_once('../../librerias/PHPMailer/src/PHPMailer.php');
require_once('../../librerias/PHPMailer/src/SMTP.php');
require_once('../../librerias/PHPMailer/src/Exception.php');

$conn = ConexionBD::conectar();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    //Verificar que existe el correo
    $stmt = $conn->prepare("SELECT id FROM pacientes WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($paciente_id);

    if ($stmt->fetch()) {
        $stmt->close();

        //Generar token
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
        //Guardar token en base de datos
        $stmt = $conn->prepare("INSERT INTO recuperacion_password (paciente_id, token, fecha_expiracion) VALUES (?, ?, ?)");
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

            $mail->setFrom('ml1708437@gmail.com', 'no-responder-gestion-turnos');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Recuperar acceso";
            $mail->Body = "
                <p>Hola,</p>
                <p>Has solicitado recuperar tu acceso.</p>
                <p>Haz clic aquí para continuar (válido por 1 hora):</p>
                <a href='$url_reset'>Recuperar acceso</a>
                <br><br>
                <small>Si no solicitaste este cambio, ignora este correo.</small>
            ";
            $mail->send();
        } catch (Exception $e) {
            echo "❌ Error al enviar correo: {$mail->ErrorInfo}";
        }
    }

    echo "<script>alert('Si tu correo está registrado, recibirás un enlace para restablecer tu contraseña.'); window.location.href='../../index.php';</script>";
}
