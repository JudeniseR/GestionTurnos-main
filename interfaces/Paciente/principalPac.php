<?php
require_once('../../Logica/General/verificarSesion.php');
verificarRol(1); // 1 = Paciente
?>
<h1>Bienvenido Paciente <?php echo $_SESSION['nombre']; ?></h1>
