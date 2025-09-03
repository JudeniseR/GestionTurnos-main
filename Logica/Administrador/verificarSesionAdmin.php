<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../../Paciente/Gestion/login.php");
    exit();
}
?>
