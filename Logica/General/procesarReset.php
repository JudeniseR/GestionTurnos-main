<?php
require_once('../../Persistencia/conexionBD.php');

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

    //Validar token
    $stmt = $conn->prepare("
        SELECT rp.paciente_id, rp.fecha_expiracion, rp.usado, p.id_perfil
        FROM recuperacion_password rp
        LEFT JOIN pacientes p ON p.id = rp.paciente_id
        WHERE rp.token = ?
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($paciente_id, $fecha_expiracion, $usado, $id_perfil);

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
    if (empty($id_perfil)) {
        die("❌ No se encontró un perfil asociado a este paciente.");
    }

    //Actualizar contraseña en perfiles
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE perfiles SET password_hash = ? WHERE id_perfil = ?");
    $stmt->bind_param("si", $hash, $id_perfil);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        die("⚠️ No se pudo actualizar la contraseña. Verifica el perfil asociado.");
    }
    $stmt->close();

    //Marcar token como usado
    $stmt = $conn->prepare("UPDATE recuperacion_password SET usado = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();

    echo "<script>
        alert('✅ Contraseña actualizada correctamente.');
        window.location.href='../../index.php';
    </script>";
}
