<?php
session_start();

require_once('../../Persistencia/conexionBD.php');
require_once('envioNotif.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // === Buscar al usuario en tabla `usuarios` ===
    $stmt = $conn->prepare("
        SELECT id_usuario, nombre, apellido, password_hash, id_rol
        FROM usuarios
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row        = $result->fetch_assoc();
        $id_usuario = (int)$row['id_usuario'];
        $nombre     = $row['nombre'];
        $apellido   = $row['apellido'];
        $rol_id     = (int)$row['id_rol'];
        $hashGuardado = $row['password_hash'];
        
        if (password_verify($password, $hashGuardado)) {
            // === Sesión base ===
            $_SESSION['id_usuario'] = $id_usuario;
            $_SESSION['nombre']     = $nombre;
            $_SESSION['apellido']   = $apellido;
            $_SESSION['rol_id']     = $rol_id;

            // Notificación de inicio (solo paciente rol=1)
            if ($rol_id === 1) {                
                $datosCorreo = [
                    'email'    => strtolower(trim($email)),
                    'nombre'   => $nombre,    
                    'apellido' => $apellido                 
                ]; 
                enviarNotificacion('login',$datosCorreo);
            }

            // === Redirecciones por rol ===
            switch ($rol_id) {
                case 1: // Paciente
                    $stmt2 = $conn->prepare("SELECT id_paciente FROM pacientes WHERE id_usuario = ? LIMIT 1");
                    $stmt2->bind_param("i", $id_usuario);
                    $stmt2->execute();
                    $stmt2->bind_result($id_paciente_real);
                    $stmt2->fetch();
                    $stmt2->close();

                    if (!$id_paciente_real) {
                        echo "<script>alert('❌ No se encontró paciente asociado a este usuario'); window.history.back();</script>";
                        exit;
                    }

                    $_SESSION['paciente_id']       = $id_usuario;
                    $_SESSION['id_paciente_token'] = $id_paciente_real;

                    header("Location: ../../interfaces/Paciente/principalPac.php");
                    exit;

                case 2: // Médico
                    // Buscar id_medico
                    $stmt2 = $conn->prepare("SELECT id_medico FROM medicos WHERE id_usuario = ? LIMIT 1");
                    $stmt2->bind_param("i", $id_usuario);
                    $stmt2->execute();
                    $stmt2->bind_result($id_medico);
                    $stmt2->fetch();
                    $stmt2->close();

                    if ($id_medico) {
                        $_SESSION['id_medico'] = $id_medico;
                    }

                    header("Location: ../../interfaces/Medico/principalMed.php");
                    exit;

                case 3: // Administrador
                    header("Location: ../../interfaces/Administrador/principalAdmi.php");
                    exit;

                case 4: // Técnico
                    // Buscar id_tecnico
                    $stmt2 = $conn->prepare("SELECT id_tecnico FROM tecnicos WHERE id_usuario = ? LIMIT 1");
                    $stmt2->bind_param("i", $id_usuario);
                    $stmt2->execute();
                    $stmt2->bind_result($id_tecnico);
                    $stmt2->fetch();
                    $stmt2->close();

                    if ($id_tecnico) {
                        $_SESSION['id_tecnico'] = $id_tecnico;
                    } else {
                        echo "<script>alert('❌ No se encontró técnico asociado a este usuario'); window.history.back();</script>";
                        exit;
                    }

                    header("Location: ../../interfaces/tecnico/principalTecnico.php");
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
}