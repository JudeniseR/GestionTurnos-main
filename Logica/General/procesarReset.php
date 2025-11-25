<?php

require_once('../../Persistencia/conexionBD.php');
require_once('envioNotif.php');

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
    $datosCorreo = [                 
                    'nombre'   => $nombre,    
                    'email'    => $email,
                    'apellido' => $apellido                 
    ]; enviarNotificacion('restablecido',$datosCorreo);

    echo "<script>
        alert('✅ Contraseña actualizada correctamente. Revisa tu correo para la confirmación.');
        window.location.href='../../index.php';
    </script>";
}
