<?php
require_once('../../Logica/General/verificarSesion.php');
verificarRol(3); // 3 = Administrador
?>
<h1>Bienvenido Administrador <?php echo $_SESSION['nombre']; ?></h1>
