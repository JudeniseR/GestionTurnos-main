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

//Validar que el email exista en perfiles
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

    //Redirigir al formulario de reset con el token generado
    header("Location: ../../interfaces/resetPassword.php?token=" . urlencode($token));
    exit;

    $conn->close();
}
