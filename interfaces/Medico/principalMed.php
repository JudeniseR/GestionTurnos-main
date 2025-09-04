<?php
require_once('../../Logica/General/verificarSesion.php');
verificarRol(2); // 2 = Médico
?>
<h1>Bienvenido Dr. <?php echo $_SESSION['nombre']; ?></h1>
