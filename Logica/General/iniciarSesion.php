<?php
session_start();
require_once('../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, nombre, password_hash FROM pacientes WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $nombre, $hash);
        $stmt->fetch();

        if (password_verify($password, $hash)) {
            // Inicio de sesión correcto
            $_SESSION['paciente_id'] = $id;
            $_SESSION['paciente_nombre'] = $nombre;
            header("Location: ../../interfaces/Paciente/principal.php");
            exit;
        } else {
            // Contraseña incorrecta
            echo "<script>alert('❌ Contraseña incorrecta'); window.history.back();</script>";
        }
    } else {
        // Email no encontrado
        echo "<script>alert('❌ El correo no está registrado'); window.history.back();</script>";
    }

    $stmt->close();
    $conn->close();
}
?>
