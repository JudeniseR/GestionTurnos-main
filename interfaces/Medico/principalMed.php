<?php
$rol_requerido = 2; // Médico
require_once('../../Logica/General/verificarSesion.php');

$nombre = $_SESSION['nombre'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inicio - Médico</title>
</head>
<body>
    <h1>Bienvenido, Dr./Dra. <?= htmlspecialchars($nombre) ?> (Médico)</h1>
    <p>Este es tu panel médico.</p>
    <a href="../../Logica/General/cerrarSesion.php">Cerrar sesión</a>
</body>
</html>
