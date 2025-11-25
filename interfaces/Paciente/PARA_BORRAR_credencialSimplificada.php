<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir las funciones necesarias
require_once('../../Logica/Paciente/credencialVirtual.php'); // Incluir las funciones
require_once '../../Logica/General/verificarSesion.php';

// Conexión a la base de datos
$conn = ConexionBD::conectar();

// Verificar si el token está en la URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Obtener los datos del paciente usando el token
    $datos = obtenerDatosPacientePorToken($conn, $token);
    if (!$datos) {
        die("❌ El token no es válido.");
    }

    // Mostrar los datos del paciente en la página
    echo "<h2>Credencial Virtual del Paciente</h2>";
    mostrarDatosPaciente($datos);  // Usamos la función de `credencialVirtual.php` para mostrar los datos

    // Mostrar el enlace para descargar el PDF
    echo "<br><a href='?descargar=1&token=$token'>📄 Descargar Credencial en PDF</a>";

    // Si se ha solicitado la descarga
    if (isset($_GET['descargar']) && $_GET['descargar'] == '1') {
        // Llamamos a la función que genera y descarga el PDF desde `credencialVirtual.php`
        descargarCredencial($conn, $token);
    }
    
    exit;
} else {
    die("❌ No se proporcionó un token.");
}
?>
