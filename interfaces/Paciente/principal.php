<?php
require_once '../../Logica/General/verificarSesionPaciente.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Turnos</title>
    <!-- Íconos Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Reset y estilo base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: url("https://i.pinimg.com/1200x/9b/e2/12/9be212df4fc8537ddc31c3f7fa147b42.jpg") no-repeat center center fixed;
            background-size: cover;
            color: #333;
        }

        /* Barra de navegación */
        nav {
            background-color: white; /* fondo blanco */
            padding: 15px 40px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        nav ul {
            list-style: none;
            display: flex;
            justify-content: center; /* centra el contenido */
            align-items: center;
            font-size: 1.1em; /* más grande */
        }

        nav ul li {
            margin: 0 25px;
        }

        nav ul li a {
            color: #1e88e5; /* azul que combina */
            text-decoration: none;
            font-weight: bold;
        }

        nav ul li a:hover {
            text-decoration: underline;
        }

        nav ul li input[type="text"] {
            padding: 7px;
            border-radius: 6px;
            border: 1px solid #ccc;
            margin-right: 8px;
        }

        nav ul li button {
            padding: 7px 15px;
            border: none;
            border-radius: 6px;
            background-color: #1e88e5;
            color: white;
            cursor: pointer;
            font-weight: bold;
        }

        nav ul li button:hover {
            background-color: #1565c0;
        }

        /* Contenido principal */
        .container {
            padding: 60px 20px;
            text-align: center;
        }

        .container h1 {
            margin-bottom: 40px;
            color: #f5f8fa; /* color claro */
            font-size: 2.5em;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }

        .cards {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .card {
            background-color: rgba(255,255,255,0.9);
            border-radius: 15px;
            padding: 40px 20px;
            width: 250px;
            height: 230px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            text-align: center;
            transition: transform 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card:hover {
            transform: translateY(-8px);
        }

        .card i {
            font-size: 50px;
            color: #1e88e5;
            margin-bottom: 10px;
        }

        .card h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .card a, .card button {
            padding: 10px;
            text-decoration: none;
            color: white;
            background-color: #1e88e5;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            width: 80%;
            margin: 0 auto; /* centra el botón */
        }

        .card a:hover, .card button:hover {
            background-color: #1565c0;
        }
    </style>
</head>
<body>

<!-- Menú de navegación -->
<nav>
    <ul>
        <li><a href="../../index.php">Inicio</a></li>
        <li><a href="../interfaces/Paciente/Gestion/misTurnos.php">Mis Turnos</a></li>
        <li><a href="verCredencial.php">Ver credencial</a></li>
        <li>
            <input type="text" placeholder="Buscar..."/>
            <button>Buscar</button>
        </li>
        <li><a href="../../Logica/General/cerrarSesion.php">Cerrar Sesión</a></li>
    </ul>
</nav>

<!-- Contenido principal -->
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
            <button>Cancelar</button>
        </div>
    </div>
</div>

</body>
</html>
