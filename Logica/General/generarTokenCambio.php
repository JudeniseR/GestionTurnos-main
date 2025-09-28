<?php
session_start();
require_once('../../Persistencia/conexionBD.php');

$conn = ConexionBD::conectar();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['email'])) {
    echo "❌ Parámetro inválido.";
    exit;
}

$email = trim($_GET['email']);

// Validar que el email exista en usuarios y obtener id_usuario
$stmt = $conn->prepare("
        SELECT u.id_usuario, p.id_paciente
        FROM pacientes p
        INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE u.email = ?
    ");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($usuario_id, $paciente_id);

if ($stmt->fetch()) {
    $stmt->close();

    // Generar token y expiración
    $token = bin2hex(random_bytes(32));
    $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Guardar token usando id_usuario
    $stmt = $conn->prepare("
            INSERT INTO recuperacion_password (id_usuario, token, fecha_expiracion) 
            VALUES (?, ?, ?)
        ");
    $stmt->bind_param("iss", $usuario_id, $token, $expiracion);
    $stmt->execute();
    $stmt->close();

    // Redirigir al formulario de reset
    header("Location: ../../interfaces/resetPassword.php?token=" . urlencode($token));
    exit;

    $conn->close();
} else {
    echo "❌ No se encontró ningún paciente con ese email.";
    $stmt->close();
    $conn->close();
}
