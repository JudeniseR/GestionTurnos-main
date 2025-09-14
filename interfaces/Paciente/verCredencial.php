<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir el archivo con las funciones de credencial
require_once('../../Logica/Paciente/credencialVirtual.php'); 
require_once('../../Logica/General/verificarSesion.php');

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Conexión a la base de datos
$conn = ConexionBD::conectar();

// Verificar si se pasó un token en la URL
if (isset($_GET['token'])) {
    // Si el token está en la URL, usamos ese token
    $token = $_GET['token'];

    // Obtener los datos del paciente usando el token
    $datos = obtenerDatosPacientePorToken($conn, $token);

    if (!$datos) {
        die("❌ El token no es válido.");
    }

    // Mostrar los datos del paciente
    echo "<h2>Credencial Virtual del Paciente</h2>";
    mostrarDatosPaciente($datos); // Función que muestra los datos del paciente

    // Generar el QR para la credencial
    $qr_url = "http://192.168.1.7/interfaces/Paciente/credencialSimplificada.php?token=$token";
    ob_start();
    QRcode::png($qr_url, null, QR_ECLEVEL_L, 4);
    $qr_data = ob_get_contents();
    ob_end_clean();

    echo "<h3>🔳 Escanea para ver la credencial</h3>";
    echo "<img src='data:image/png;base64," . base64_encode($qr_data) . "' alt='QR para ver credencial' width='200'>";

    // Enlace para descargar el PDF de la credencial
    echo "<br><a href='credencialVirtual.php?descargar=1&token=$token'>📄 Descargar Credencial en PDF</a>";

} elseif (isset($_SESSION['id_paciente_token'])) {
    // Si no se pasó un token en la URL, usamos el ID de la sesión para obtener el token desde la base de datos
    $id_paciente = $_SESSION['id_paciente_token'];

    // Verificar existencia del paciente en la base
    $stmt = $conn->prepare("SELECT id, token_qr FROM pacientes WHERE id = ?");
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $result = $stmt->get_result();
    $paciente = $result->fetch_assoc();
    $stmt->close();

    if (!$paciente) {
        die("❌ No existe ningún paciente con ID = $id_paciente en la tabla `pacientes`.");
    }

    // Paciente encontrado, obtenemos token
    $token = $paciente['token_qr'];

    if (!$token) {
        die("❌ No se encontró un token válido.");
    }

    // Obtener los datos del paciente usando el token
    $datos = obtenerDatosPacientePorToken($conn, $token);

    if (!$datos) {
        die("❌ El token no es válido.");
    }

    // Mostrar los datos del paciente
    echo "<h2>Credencial Virtual del Paciente</h2>";
    mostrarDatosPaciente($datos); // Función que muestra los datos del paciente

    // Generar el QR para la credencial GENERAR LOGICA Q CONSTRUYE EL URL
    $qr_url = "http://192.168.1.7/interfaces/Paciente/credencialSimplificada.php?token=$token";
    ob_start();
    QRcode::png($qr_url, null, QR_ECLEVEL_L, 4);
    $qr_data = ob_get_contents();
    ob_end_clean();

    echo "<h3>🔳 Escanea para ver la credencial</h3>";
    echo "<img src='data:image/png;base64," . base64_encode($qr_data) . "' alt='QR para ver credencial' width='200'>";

    // Enlace para descargar el PDF de la credencial
    echo "<br><a href='../../Logica/Paciente/credencialVirtual.php?descargar=1&token=$token'>📄 Descargar Credencial en PDF</a>";

} else {
    // Si no hay un token y no hay sesión activa, mostramos un error
    die("❌ No se proporcionó un token válido.");
}
?>
