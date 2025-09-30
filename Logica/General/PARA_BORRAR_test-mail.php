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

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'xxjavicaixx@gmail.com';
    $mail->Password   = 'ycejgbxqrhueamqf';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('xxjavicaixx@gmail.com', 'Prueba SMTP');
    $mail->addAddress('javo.lopez7l@gmail.com');

    $mail->isHTML(false);
    $mail->Subject = 'Correo de prueba';
    $mail->Body    = 'Hola, esto es una prueba.';

    $mail->send();
    echo '✅ Correo enviado correctamente';
} catch (Exception $e) {
    echo '❌ Error al enviar: ' . $mail->ErrorInfo;
}
