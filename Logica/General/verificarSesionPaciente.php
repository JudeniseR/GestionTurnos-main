<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['paciente_id'])) {
    header('Location: /index.php'); 
    exit;
}
?>