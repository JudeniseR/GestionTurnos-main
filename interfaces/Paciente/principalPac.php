<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../Logica/General/verificarSesion.php');

$nombre = $_SESSION['nombre'];
$apellido = $_SESSION['apellido'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paciente | Gestión de turnos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css"/>
    <link rel="stylesheet" href="../../css/principalPac.css">    
</head>

<body>

    <?php include('navPac.php'); ?>


<!--
<header>
        <nav>
            <ul>
                <div class="nav-links">
                    <li><a href="#">Inicio</a></li>
                    <li><a href="Gestion/misTurnos.php">Mis Turnos</a></li>
                    <li>
                        <a data-fancybox
                        data-caption="Sistema Gestión Turnos - Credencial virtual afiliado"
                        data-type="iframe"
                        data-src="verCredencial.php"
                        data-width="800"
                        data-height="400"
                        href="javascript:;">
                        Ver credencial
                        </a>
                        </li>
                    <li>
                        <input type="text" placeholder="Buscar..." />
                        <button>Buscar</button>
                    </li>
                    <li><a href="../../Logica/General/cerrarSesion.php">Cerrar Sesión</a></li>
                </div>

                <div class="perfil">
                    <span> <?php // echo mb_strtoupper($_SESSION['apellido'], 'UTF-8') . ", " . mb_convert_case($_SESSION['nombre'], MB_CASE_TITLE, 'UTF-8'); ?></span>
                    <img src="../../assets/img/loginAdmin.png" alt="Foto perfil">
                </div>
            </ul>
        </nav>
    </header>
-->
    <main>
        <div class="container">
            <h1>Bienvenido/a al Sistema de Turnos</h1>

            <div class="cards">
                <div class="card">
                    <i class="fa-solid fa-heart-pulse"></i>
                    <h3>Solicitar Turno Médico</h3>
                    <a href="Gestion/Turnos-Medico/turnoMedico.php">Ir</a>
                </div>
                <div class="card">
                    <i class="fa-solid fa-vials"></i>
                    <h3>Solicitar Estudio</h3>
                    <a href="Gestion/Turnos-Estudio/turnoEstudio.php">Ir</a>
                </div>
                <div class="card">
                    <i class="fa-solid fa-calendar-check"></i>
                    <h3>Ver Mis Turnos</h3>
                    <a href="Gestion/misTurnos.php">Ir</a>
                </div>
                <div class="card">
                    <i class="fa-solid fa-ban"></i>
                    <h3>Cancelar Turno</h3>
                    <!--<button>Cancelar</button>-->
                    <a href="Gestion/misTurnos.php">Cancelar Turno</a>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
</body>

</html>