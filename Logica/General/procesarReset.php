<?php
require_once('../../Persistencia/conexionBD.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = ConexionBD::conectar();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if ($password !== $confirm) {
        die("❌ Las contraseñas no coinciden.");
    }
    // Buscar token
    $stmt = $conn->prepare("SELECT paciente_id, fecha_expiracion, usado FROM recuperacion_password WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($paciente_id, $fecha_expiracion, $usado);

    if ($stmt->fetch()) {
        if ($usado || strtotime($fecha_expiracion) < time()) {
            die("❌ El enlace ya no es válido.");
        }
    } else {
        die("❌ Token no encontrado.");
    }
    $stmt->close();
    // Actualizar contraseña
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE pacientes SET password_hash = ? WHERE id = ?");
    $stmt->bind_param("si", $hash, $paciente_id);
    $stmt->execute();
    $stmt->close();
    // Marcar token como usado
    $stmt = $conn->prepare("UPDATE recuperacion_password SET usado = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    
    echo "<script>alert('✅ Contraseña actualizada correctamente.'); window.location.href='../../index.php';</script>";
}
