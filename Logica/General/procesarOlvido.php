<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../Persistencia/conexionBD.php');
require_once('envioNotif.php');

$conn = ConexionBD::conectar();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Buscar usuario y paciente asociado
    $stmt = $conn->prepare("
        SELECT u.id_usuario, u.nombre, u.apellido
        FROM usuarios u
        WHERE u.email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id_usuario, $nombre, $apellido);

    if ($stmt->fetch()) {
        $stmt->close();

        // Generar token seguro
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Insertar token en tabla recuperacion_password
        $stmt = $conn->prepare("
            INSERT INTO recuperacion_password (id_usuario, token, fecha_expiracion) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iss", $id_usuario, $token, $expiracion);
        $stmt->execute();
        $stmt->close();

        // Enviar correo
        $datosCorreo = [                
                'nombre'   => $nombre,    
                'apellido' => $apellido,   
                'email'    => $email,            
                'token'    => $token
        ]; enviarNotificacion('recupero',$datosCorreo);

    // Mensaje genérico para evitar revelar existencia del correo
    echo "<script>alert('Si tu correo está registrado, recibirás un enlace para restablecer tu contraseña.'); window.location.href='../../index.php';</script>";
}
}
