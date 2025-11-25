<?php
session_start();

require_once('../../Persistencia/conexionBD.php');
require_once('envioNotif.php');
require_once('../../interfaces/mostrarAlerta.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // === Buscar al usuario ===
    $stmt = $conn->prepare("
    SELECT id_usuario, nombre, apellido, password_hash, id_rol, activo, genero
    FROM usuarios
    WHERE email = ?
    LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row          = $result->fetch_assoc();
        $id_usuario   = (int)$row['id_usuario'];
        $nombre       = $row['nombre'];
        $apellido     = $row['apellido'];
        $rol_id       = (int)$row['id_rol'];
        $hashGuardado = $row['password_hash'];

        // Comprobar si la cuenta está activa
        if ((int)$row['activo'] === 0) {
            $_SESSION['error_message'] = '❌ Cuenta inactiva. Comuníquese con el administrador.';
            header('Location: ../../interfaces/Paciente/login.php');
            exit;
        }

        // Verificar la contraseña
        if (password_verify($password, $hashGuardado)) {
            // === Sesión base ===
            $_SESSION['id_usuario'] = $id_usuario;
            $_SESSION['nombre']     = $nombre;
            $_SESSION['apellido']   = $apellido;
            $_SESSION['rol_id']     = $rol_id;
            $_SESSION['genero']     = $row['genero'] ?? 'M'; 

            // === Notificación solo para pacientes ===
            if ($rol_id === 1) {
                $datosCorreo = [
                    'email'    => strtolower(trim($email)),
                    'nombre'   => $nombre,
                    'apellido' => $apellido
                ];
                enviarNotificacion('login', $datosCorreo);
            }

            // === Redirección según rol ===
            switch ($rol_id) {
                case 1: // Paciente
                    // Redirigir al paciente
                    header("Location: ../../interfaces/Paciente/principalPac.php");
                    exit;
                case 2: // Médico
                    header("Location: ../../interfaces/Medico/principalMed.php");
                    exit;
                case 3: // Administrador
                    header("Location: ../../interfaces/Administrador/principalAdmi.php");
                    exit;
                case 4: // Técnico
                    header("Location: ../../interfaces/tecnico/principalTecnico.php");
                    exit;
                case 5: // Administrativo
                    header("Location: ../../interfaces/Administrativo/principalAdministrativo.php");
                    exit;
                default:
                    $_SESSION['error_message'] = '❌ Rol no válido.';
                    header('Location: ../../interfaces/Paciente/login.php');
                    exit;
            }
        } else {
            $_SESSION['error_message'] = '❌ El correo o la contraseña son incorrectos.';
            header('Location: ../../interfaces/Paciente/login.php');
            exit;
        }
    } else {
        $_SESSION['error_message'] = '❌ El correo no está registrado.';
        header('Location: ../../interfaces/Paciente/login.php');
        exit;
    }
}
?>
