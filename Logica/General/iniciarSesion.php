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

    // Buscar al usuario en perfiles
    $stmt = $conn->prepare("
        SELECT id_perfil, nombre, password_hash, rol_id 
        FROM perfiles 
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id_perfil, $nombre, $hashGuardado, $rol_id);
        $stmt->fetch();

        // Verificar contraseña (bcrypt recomendado)
        if (password_verify($password, $hashGuardado)) {
            // Guardar datos en sesión
            $_SESSION['id_perfil'] = $id_perfil;
            $_SESSION['nombre'] = $nombre;
            $_SESSION['rol_id'] = $rol_id;

            // Redirigir según rol
            switch ((int)$rol_id) {
                case 1: // Paciente
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
