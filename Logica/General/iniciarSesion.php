<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('../../Persistencia/conexionBD.php');
require_once('../../librerias/PHPMailer/src/PHPMailer.php');
require_once('../../librerias/PHPMailer/src/SMTP.php');
require_once('../../librerias/PHPMailer/src/Exception.php');

$conn = ConexionBD::conectar();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Buscar al usuario en tabla usuario
    $stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, password_hash, id_rol 
                            FROM usuarios 
                            WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        $id_usuario = $row['id_usuario'];
        $nombre = $row['nombre'];
        $apellido = $row['apellido'];
        $hashGuardado = $row['password_hash'];
        $rol_id = $row['id_rol'];

        // Verificar contraseña
        if (password_verify($password, $hashGuardado)) {
            // Guardar datos en sesión
            $_SESSION['id_usuario'] = $id_usuario;
            $_SESSION['nombre'] = $nombre;
            $_SESSION['apellido'] = $apellido;
            $_SESSION['rol_id'] = $rol_id;

            // Notificación de inicio de sesión (solo para paciente)
            if ((int)$rol_id === 1) {
                $fechaHora = date("d/m/Y H:i:s");
                $ip = $_SERVER['REMOTE_ADDR'];

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
                    $mail->addAddress($email, $nombre . " " . $apellido);
                    $mail->isHTML(true);
                    $mail->Subject = 'Notificación de inicio de sesión en tu cuenta';

                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    $url_generar = $protocol . "://" . $host . "/Logica/General/generarTokenCambio.php?email=" . urlencode($email);

                    $mail->Body = "<head><meta charset='UTF-8'></head>
                    <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;'>
                        <div style='max-width: 600px; margin: auto; background: #ffffff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); overflow: hidden;'>
                            <div style='background: #1976d2; padding: 20px; text-align: center; color: #fff;'>
                                <h2 style='margin: 0;'>Sistema de Gestión Turnos</h2>
                            </div>
                            <div style='padding: 20px; color: #333;'>
                                <h3 style='margin-top: 0;'>Hola, {$nombre} {$apellido} 👋</h3>
                                <p>Queremos informarte que se ha iniciado sesión en tu cuenta:</p>
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
                                <p style='margin-top: 20px;'>Si fuiste tú, no es necesario realizar ninguna acción.</p>
                                <p style='color: #d32f2f; font-weight: bold;'>Si no reconoces esta actividad, cambia tu contraseña inmediatamente.</p>
                                <div style='text-align: center; margin-top: 20px;'>
                                    <a href='{$url_generar}' 
                                       style='background: #1976d2; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 5px;'>
                                       Cambiar Contraseña
                                    </a>
                                </div>
                            </div>
                            <div style='background: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; color: #777;'>
                                © " . date("Y") . " Sistema de Gestión Turnos - Este es un mensaje automático, por favor no responda.
                            </div>
                        </div>
                    </div>";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Error al enviar correo de inicio de sesión: {$mail->ErrorInfo}");
                }
            }

            // Redirigir según rol
            switch ((int)$rol_id) {
                case 1: // Paciente
                    // Buscar el id real del paciente desde la tabla pacientes usando id_usuario
                    $stmt2 = $conn->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ?");
                    $stmt2->bind_param("i", $id_usuario);
                    $stmt2->execute();
                    $stmt2->bind_result($id_paciente_real);
                    $stmt2->fetch();
                    $stmt2->close();

                    if (!$id_paciente_real) {
                        echo "<script>alert('❌ No se encontró paciente asociado a este usuario'); window.history.back();</script>";
                        exit;
                    }

                    // Guardar en sesión
                    $_SESSION['paciente_id'] = $id_usuario; // ID del usuario
                    $_SESSION['id_paciente_token'] = $id_paciente_real; // ID real del paciente (PK de la tabla pacientes)

                    header("Location: ../../interfaces/Paciente/principalPac.php");
                    exit;

                case 2: // Médico
                    header("Location: ../../interfaces/Medico/principalMed.php");
                    exit;

                case 3: // Administrador
                    header("Location: ../../interfaces/Administrador/principalAdmi.php");
                    exit;

                default:
                    echo "<script>alert('❌ Rol no válido'); window.history.back();</script>";
                    exit;
            }

        } else {
            echo "<script>alert('❌ Contraseña incorrecta'); window.history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('❌ El correo no está registrado'); window.history.back();</script>";
        exit;
    }

    $stmt->close();
    $conn->close();
}
?>
