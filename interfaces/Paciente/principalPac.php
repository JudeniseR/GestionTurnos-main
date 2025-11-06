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
                
                <!-- 🆕 NUEVA TARJETA: Mis Órdenes Médicas -->
                <div class="card" style="border-left: 4px solid #667eea;">
                    <i class="fa-solid fa-file-medical" style="color: #667eea;"></i>
                    <h3>Mis Órdenes Médicas</h3>
                    <a href="mis_ordenes.php" style="background: #667eea;">Ver Órdenes</a>
                </div>
                
                <div class="card">
                    <i class="fa-solid fa-calendar-check"></i>
                    <h3>Ver Mis Turnos</h3>
                    <a href="Gestion/misTurnos.php">Ir</a>
                </div>
                
                <div class="card">
                    <i class="fa-solid fa-ban"></i>
                    <h3>Cancelar Turno</h3>
                    <a href="Gestion/cancelarTurnos.php">Cancelar Turno</a>
                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER REUTILIZABLE -->
    <?php include '../footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
</body>

</html>