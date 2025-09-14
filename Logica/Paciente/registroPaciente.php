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

// Función: Sanitiza input
function clean_input($data) {
    return htmlspecialchars(trim($data));
}

// Función: Obtener datos del formulario
function get_post_data() {
    return [
        'nombre'             => clean_input($_POST['nombre']),
        'apellido'           => clean_input($_POST['apellido']),
        'tipo_documento'     => $_POST['tipo_documento'],
        'numero_documento'   => clean_input($_POST['numero_documento']),
        'genero'             => $_POST['genero'],
        'fecha_nacimiento'   => $_POST['fecha_nacimiento'],
        'domicilio'          => clean_input($_POST['domicilio']),
        'numero_contacto'    => clean_input($_POST['numero_contacto']),
        'cobertura_salud'    => $_POST['cobertura_salud'],
        'numero_afiliado'    => clean_input($_POST['numero_afiliado']),
        'email'              => clean_input($_POST['email']),
        // 'password'           => $_POST['password'],
        'password_hash'      => password_hash($_POST['password'], PASSWORD_DEFAULT),
    ];
}

// Función: Procesar imagen del DNI
function procesar_imagen_dni() {
    if (isset($_FILES['imagen_dni']) && is_uploaded_file($_FILES['imagen_dni']['tmp_name'])) {
        return base64_encode(file_get_contents($_FILES['imagen_dni']['tmp_name']));
    } else {
        throw new Exception("❌ Error: no se subió correctamente la imagen del DNI.");
    }
}

// Función: Validar si el afiliado existe y está activo
function validar_afiliado($conn, $documento, $afiliado) {
    $stmt = $conn->prepare("SELECT 1 FROM afiliados WHERE numero_documento = ? AND numero_afiliado = ? AND estado = 'activo'");
    $stmt->bind_param("ss", $documento, $afiliado);
    $stmt->execute();
    $stmt->store_result();
    $valid = $stmt->num_rows > 0;
    $stmt->close();
    return $valid;
}

// Función: Obtener ID del afiliado
function obtener_id_afiliado($conn, $documento, $afiliado) {
    $stmt = $conn->prepare("SELECT id FROM afiliados WHERE numero_documento = ? AND numero_afiliado = ?");
    $stmt->bind_param("ss", $documento, $afiliado);
    $stmt->execute();
    $stmt->bind_result($id);
    if ($stmt->fetch()) {
        $stmt->close();
        return $id;
    } else {
        $stmt->close();
        throw new Exception("❌ No se encontró el ID del afiliado.");
    }
}

function crear_perfil($conn, $data) {
    $rol_id = 1; // Paciente
    $stmt = $conn->prepare("INSERT INTO perfiles (nombre, apellido, email, password_hash, rol_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $data['nombre'], $data['apellido'], $data['email'], $data['password_hash'], $rol_id);
    
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception("❌ Error al crear perfil: $error");
    }
}

// Función: Registrar paciente
function registrar_paciente($conn, $data, $imagen_base64, $id_afiliado, $token_qr, $id_perfil) {
    $stmt = $conn->prepare("
        INSERT INTO pacientes 
        (nombre, apellido, tipo_documento, numero_documento, img_dni, genero, fecha_nacimiento, domicilio, numero_contacto, cobertura_salud, numero_afiliado, email, id_afiliado, token_qr, id_perfil)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "sssssssssssssss",
        $data['nombre'],
        $data['apellido'],
        $data['tipo_documento'],
        $data['numero_documento'],
        $imagen_base64,
        $data['genero'],
        $data['fecha_nacimiento'],
        $data['domicilio'],
        $data['numero_contacto'],
        $data['cobertura_salud'],
        $data['numero_afiliado'],
        $data['email'],        
        $id_afiliado,
        $token_qr,
        $id_perfil
    );

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception("❌ Error al registrar paciente: $error");
    }

    $stmt->close();
}

function enviar_mail_registro($email, $nombre, $apellido) {
    try {
        $mail = new PHPMailer(true);
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
        $mail->addAddress($email, $nombre . " " . $apellido);                                        
        $mail->isHTML(true);
        $mail->Subject = "Registro exitoso en Sistema de Gestión Turnos";
        //formar url
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $url_login = $protocol . "://" . $host . "/interfaces/Paciente/login.php";
        // Cuerpo del correo
        $mail->isHTML(true);
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding:20px;'>
            <div style='max-width:600px; margin:auto; background:#ffffff; border-radius:8px; 
                        box-shadow:0 2px 6px rgba(0,0,0,0.1); overflow:hidden;'>
                <div style='background:#1976d2; padding:20px; text-align:center; color:#fff;'>
                    <h2 style='margin:0;'>Bienvenido/a, {$nombre} {$apellido} 🎉</h2>
                </div>
                <div style='padding:20px; color:#333;'>
                    <p>¡Tu registro en el <b>Sistema de Gestión de Turnos</b> fue exitoso! ✅</p>
                    <p>Ya puedes iniciar sesión con tu correo y contraseña para acceder a tus turnos y gestionar tu información.</p>
                    
                    <div style='text-align:center; margin:30px 0;'>
                        <a href='{$url_login}' style='background: #4CAF50; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                            Iniciar Sesión
                        </a>
                    </div>

                    <p style='font-size:14px; color:#555;'>Si no solicitaste este registro, por favor ignora este mensaje.</p>
                </div>
                <div style='background:#f5f5f5; padding:10px; text-align:center; font-size:12px; color:#777;'>
                    © " . date("Y") . " Sistema de Gestión Turnos - Este es un mensaje automático, no respondas a este correo.
                </div>
            </div>
        </div>";

        $mail->send();
    } catch (Exception $e) {
        error_log("❌ Error al enviar correo de registro: {$mail->ErrorInfo}");
    }
}

// Main
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = get_post_data();
        $imagen_dni = procesar_imagen_dni();

        if (!validar_afiliado($conn, $data['numero_documento'], $data['numero_afiliado'])) {
            throw new Exception("❌ No estás registrado como afiliado activo.");
        }

        $id_afiliado = obtener_id_afiliado($conn, $data['numero_documento'], $data['numero_afiliado']);
        $token_qr = bin2hex(random_bytes(16));

        // 1. Crear perfil
        $id_perfil = crear_perfil($conn, $data);

        // 2. Registrar paciente con id_perfil
        registrar_paciente($conn, $data, $imagen_dni, $id_afiliado, $token_qr, $id_perfil);

        // 3. Enviar correo de confirmación de registro
        enviar_mail_registro($data['email'], $data['nombre'], $data['apellido']);

        echo "<script>alert('✅ Registro exitoso.'); window.location.href = '../../interfaces/Paciente/login.php';</script>";
    } catch (mysqli_sql_exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'numero_documento') !== false) {
            echo "<script>alert('❌ El número de documento ya está registrado.'); window.history.back();</script>";
        } elseif (strpos($msg, 'email') !== false) {
            echo "<script>alert('❌ El correo electrónico ya está registrado.'); window.history.back();</script>";
        } else {
            echo "<script>alert('❌ Error inesperado: " . addslashes($msg) . "'); window.history.back();</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('" . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    } finally {
        $conn->close();
    }
}
?>

