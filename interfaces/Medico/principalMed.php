<?php
$rol_requerido = 2; // Médico
require_once('../../Logica/General/verificarSesion.php');

$nombre = $_SESSION['nombre'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Medico</title>
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
            background-color: white;
            padding: 15px 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        nav ul {
            list-style: none;
            display: flex;
            justify-content: space-between;
            /* espacio entre opciones y perfil */
            align-items: center;
            font-size: 1.1em;
        }

        nav ul .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        nav ul li a {
            color: #1e88e5;
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

        /* Perfil en el nav */
        .perfil {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .perfil span {
            font-weight: bold;
            color: #333;
        }

        .perfil img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            /* círculo */
            object-fit: cover;
            border: 2px solid #1e88e5;
        }

        /* Contenido principal */
        .container {
            padding: 60px 20px;
            text-align: center;
        }

        .container h1 {
            margin-bottom: 40px;
            color: #f5f8fa;
            font-size: 2.5em;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }

        .cards {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .card {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 40px 20px;
            width: 250px;
            height: 230px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
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

        .card a,
        .card button {
            padding: 10px;
            text-decoration: none;
            color: white;
            background-color: #1e88e5;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            width: 80%;
            margin: 0 auto;
        }

        .card a:hover,
        .card button:hover {
            background-color: #1565c0;
        }
    </style>
</head>

<body>
    <header>
        <nav>
            <ul>
                <div class="nav-links">
                    <li><a href="#">Inicio</a></li>
                    <li>
                        <input type="text" placeholder="Buscar..." />
                        <button>Buscar</button>
                    </li>
                    <li><a href="../../Logica/General/cerrarSesion.php">Cerrar sesión</a></li>
                </div>

                <!--
                <div class="perfil">
                    <span><?php // echo $nombre . " " . $apellido; ?></span>
                    <img src="../../assets/img/loginAdmin.jpg" alt="Foto perfil">
                </div>
                -->
            </ul>
        </nav>
    </header>
    <main>
        <div class="container">
            
            
            <div class="container">
            <h1>Bienvenido, <?= htmlspecialchars($nombre) ?> (Administrador)</h1>
            
            <div class="cards">
                <div class="card">
                    <i class="fa-solid fa-calendar-days"></i>
                    <h3></h3>
                    <a href="#">Gestionar Agenda</a>
                </div>
                <div class="card">
                    <i class="fa-solid fa-user-doctor"></i>
                    <h3></h3>
                    <a href="#">Gestionar Turnos</a>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
