<?php
$rol_requerido = 3; // Administrador
require_once('../../Logica/General/verificarSesion.php');

$nombre = $_SESSION['nombre'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inicio - Administrador</title>
</head>
<body>
    <h1>Bienvenido, <?= htmlspecialchars($nombre) ?> (Administrador)</h1>
    <p>Este es tu panel de administrador.</p>
    <a href="../../Logica/General/cerrarSesion.php">Cerrar sesión</a>
</body>
</html>