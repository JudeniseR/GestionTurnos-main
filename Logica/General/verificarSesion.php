<?php
session_start();

// Si no hay sesión iniciada
if (!isset($_SESSION['id_perfil'])) {
    header("Location: ../../interfaces/login.php");
    exit;
}

// Función para verificar rol
function verificarRol($rolNecesario) {
    if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != $rolNecesario) {
        echo "<script>alert('❌ No tienes permiso para acceder a esta página'); window.location.href='../../index.php';</script>";
        exit;
    }
}
?>
