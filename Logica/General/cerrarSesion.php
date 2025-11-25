<?php
session_start();
session_unset(); // Elimina todas las variables de sesión
session_destroy(); // Destruye la sesión activa

header('Location: ../../index.php'); // Redirige al login
exit;
?>
