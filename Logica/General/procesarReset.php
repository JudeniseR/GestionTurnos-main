<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('../../Persistencia/conexionBD.php');
require_once('../../librerias/PHPMailer/src/PHPMailer.php');
require_once('../../librerias/PHPMailer/src/SMTP.php');
require_once('../../librerias/PHPMailer/src/Exception.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = ConexionBD::conectar();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = $_POST['token']   ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if ($password !== $confirm) {
        die("❌ Las contraseñas no coinciden.");
    }
    if (strlen($password) < 8) {
        die("❌ La contraseña debe tener al menos 8 caracteres.");
    }

    // Validar token y obtener usuario
    $stmt = $conn->prepare("
        SELECT rp.id_usuario, rp.fecha_expiracion, rp.usado, u.nombre, u.apellido, u.email
        FROM recuperacion_password rp
        JOIN usuarios u ON u.id_usuario = rp.id_usuario
        WHERE rp.token = ?
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($id_usuario, $fecha_expiracion, $usado, $nombre, $apellido, $email);

    if (!$stmt->fetch()) {
        $stmt->close();
        die("❌ Token no encontrado.");
    }
    $stmt->close();

    if ((int)$usado === 1) {
        die("❌ El enlace ya fue utilizado.");
    }
    if (strtotime($fecha_expiracion) < time()) {
        die("❌ El enlace ya expiró.");
    }

    // Actualizar contraseña
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?");
    $stmt->bind_param("si", $hash, $id_usuario);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        die("⚠️ No se pudo actualizar la contraseña. Verifica el usuario asociado.");
    }
    $stmt->close();

    // Marcar token como usado
    $stmt = $conn->prepare("UPDATE recuperacion_password SET usado = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();

    // Enviar correo de confirmación
    $mail = new PHPMailer(true);
    try {
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
        $mail->addAddress($email, $nombre . ' ' . $apellido);
        $mail->isHTML(true);
        $mail->Subject = '🔐 Contraseña actualizada correctamente';

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding:20px;'>
            <div style='max-width:600px; margin:auto; background:#ffffff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); overflow:hidden;'>
                <div style='background:#1976D2; padding:20px; text-align:center; color:#fff;'>
                    <h2 style='margin:0;'>Confirmación de Cambio de Contraseña 🔐</h2>
                </div>
                <div style='padding:20px; color:#333;'>
                    <p>Estimado/a <b>{$nombre}</b>,</p>
                    <p>Tu contraseña ha sido actualizada correctamente ✅.</p>                    
                    <p style='margin-top:20px;'>Gracias por confiar en nosotros 🙌.</p>
                </div>
                <div style='background:#f5f5f5; padding:10px; text-align:center; font-size:12px; color:#777;'>
                    © " . date("Y") . " Sistema de Gestión Turnos - Este es un mensaje automático, no respondas a este correo.
                </div>
            </div>
        </div>";

        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar el correo: {$mail->ErrorInfo}");
        // No detener flujo
    }

    echo "<script>
        alert('✅ Contraseña actualizada correctamente. Revisa tu correo para la confirmación.');
        window.location.href='../../index.php';
    </script>";
}
