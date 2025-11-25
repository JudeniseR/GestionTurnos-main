<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

$nombre = $_SESSION['nombre'];
$apellido = $_SESSION['apellido'];

$id_usuario = $_SESSION['id_usuario'] ?? null;
$id_paciente = $_SESSION['id_paciente_token'] ?? null;

// Verificar si tiene afiliados menores
$tiene_afiliados = false;
$conn = ConexionBD::conectar();

$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM pacientes p1
    JOIN afiliados a1 ON a1.numero_documento = p1.nro_documento
    JOIN afiliados a2 ON a2.id = a1.id_titular
    JOIN pacientes p2 ON p2.nro_documento = a2.numero_documento
    WHERE p2.id_paciente = ?
      AND TIMESTAMPDIFF(YEAR, p1.fecha_nacimiento, CURDATE()) < 18
      AND a1.estado = 'activo'
");
$stmt->bind_param("i", $id_paciente);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$tiene_afiliados = ($row['total'] > 0);
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
                <div class="card">
                    <i class="fa-solid fa-file-medical"></i>
                    <h3>Mis Órdenes Médicas</h3>
                    <a href="mis_ordenes.php">Ir</a>
                </div>
                
                <div class="card">
                    <i class="fa-solid fa-calendar-check"></i>
                    <h3>Ver Mis Turnos</h3>
                    <a href="Gestion/misTurnos.php">Ir</a>
                </div>
                
                <div class="card">
                    <i class="fa-solid fa-ban"></i>
                    <h3>Cancelar Turno</h3>
                    <a href="Gestion/cancelarTurnos.php">Ir</a>
                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER REUTILIZABLE -->
    <?php include '../footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
</body>

</html>