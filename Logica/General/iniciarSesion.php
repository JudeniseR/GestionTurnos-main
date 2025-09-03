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

    // Buscar usuario en tabla usuarios
    $stmt = $conn->prepare("SELECT id, email, password, rol, id_paciente, id_medico FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();

        if (password_verify($password, $usuario['password'])) {
            // Guardamos sesión
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['rol'] = $usuario['rol'];
            $_SESSION['id_paciente'] = $usuario['id_paciente'];
            $_SESSION['id_medico'] = $usuario['id_medico'];

            // Redirigir según rol
            switch ($usuario['rol']) {
                case 'admin':
                    header("Location: ../../Presentacion/admin/dashboard.php");
                    break;
                case 'medico':
                    header("Location: ../../Presentacion/medico/horarios.php");
                    break;
                case 'paciente':
                    header("Location: ../../Presentacion/paciente/turnos.php");
                    break;
                default:
                    echo "<script>alert('❌ Rol no válido'); window.history.back();</script>";
            }
            exit;
        } else {
            echo "<script>alert('❌ Contraseña incorrecta'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('❌ El correo no está registrado'); window.history.back();</script>";
    }

    $stmt->close();
    $conn->close();
}
?>
