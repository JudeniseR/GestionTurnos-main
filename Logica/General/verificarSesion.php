<?php
// echo '<pre>';
// print_r($_SESSION);
// echo '</pre>';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lista de roles válidos para la página
// Esta variable la debe definir el archivo que incluye este script antes de incluirlo
if (!isset($rol_requerido)) {
    // Si no se especifica rol requerido, asumimos que cualquier rol está permitido
    $roles_validos = [1, 2, 3, 4, 5]; // Paciente, Médico, Administrador, Técnico y Administrativo.
} else {
    // Puede ser un solo rol o un array de roles permitidos
    if (is_array($rol_requerido)) {
        $roles_validos = $rol_requerido;
    } else {
        $roles_validos = [$rol_requerido];
    }
}

// Verificamos que el usuario esté autenticado
// if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol_id'])) {
//     header("Location: /index.php");
//     exit;                                                //ACA QUEDAMOS JAVEEEE
// }

// Validar que el rol del usuario esté dentro de los permitidos para esta página
if (!in_array($_SESSION['rol_id'], $roles_validos)) {
    // Rol no autorizado: destruimos sesión y enviamos a login
    session_destroy();
    header("Location: /index.php");
    exit;
}
// ?>