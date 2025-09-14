<?php
// Mostrar errores (solo para desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesion.php';

$conn = ConexionBD::conectar();

$paciente_id = $_SESSION['id_paciente_token'] ?? null;

if (!$paciente_id) {
    die("Debe iniciar sesión para ver sus turnos.");
}

$sql = "
    SELECT 
        t.id,
        t.fecha,
        t.hora,
        t.estado,
        t.estudio_id,
        e.nombre AS nombre_estudio,
        t.medico_id,
        m.nombre AS nombre_medico,
        m.apellido AS apellido_medico,
        s1.nombre AS sede_estudio,
        s2.nombre AS sede_medico
    FROM turnos t
    LEFT JOIN estudios e ON t.estudio_id = e.id
    LEFT JOIN recursos r ON t.recurso_id = r.id
    LEFT JOIN sedes s1 ON r.sede_id = s1.id
    LEFT JOIN medicos m ON t.medico_id = m.id_medico
    LEFT JOIN agenda_medica am ON t.medico_id = am.id_medico AND t.fecha = am.fecha
    LEFT JOIN sedes s2 ON am.sede_id = s2.id
    WHERE t.paciente_id = ?
    GROUP BY t.id
    ORDER BY t.fecha DESC, t.hora DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mis turnos</title>
    <!-- Íconos Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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

        table {
            width: 90%;
            margin: 30px auto;
            border-collapse: collapse;
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            border-radius: 12px;
            overflow: hidden;
        }

        table thead {
            background-color: #1e88e5;
            color: white;
            font-weight: bold;
            font-size: 1em;
        }

        table thead th {
            padding: 12px;
            text-align: center;
        }

        table tbody td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #ddd;
            color: #333;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tbody tr:hover {
            background-color: rgba(30, 136, 229, 0.1);
        }

        table button {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            background-color: #e53935;
            color: white;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s ease;
        }

        table button:hover {
            background-color: #c62828;
        }
    </style>
</head>

<body>
    <nav>
            <ul>
                <div class="nav-links">
                  <li><a href="../principalPac.php">Inicio</a></li>
                <li><a href="../principalPac.php">Principal</a></li>
                <li><a href="../verCredencial.php">Ver credencial</a></li>
                <li>
                    <input type="text" placeholder="Buscar..." />
                    <button>Buscar</button>
                </li>
                <li><a href="../../../Logica/General/cerrarSesion.php">Cerrar Sesión</a></li>
                </div>


                <div class="perfil">
                    <span><?php echo strtoupper($_SESSION['apellido']) . ", " . ucfirst(strtolower($_SESSION['nombre'])); ?></span>
                    <img src="../../assets/img/loginAdmin.jpg" alt="Foto perfil">
                </div>
            </ul>
        </nav>

    <h1 class="container">Mis Turnos</h1>

    <?php if (isset($_GET['cancelado']) && $_GET['cancelado'] == 1): ?>
        <p style="color:green;">Turno cancelado correctamente.</p>
    <?php endif; ?>

    <?php if ($result->num_rows === 0): ?>
        <p>No tenés turnos registrados.</p>
    <?php else: ?>
        <table border="1" cellpadding="5">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Estudio / Médico</th>
                    <th>Sede</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($turno = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($turno['fecha']); ?></td>
                        <td><?php echo htmlspecialchars($turno['hora']); ?></td>
                        <td>
                            <?php
                            if ($turno['estudio_id']) {
                                echo "Estudio: " . htmlspecialchars($turno['nombre_estudio'] ?? 'No especificado');
                            } elseif ($turno['medico_id']) {
                                echo "Médico: " . htmlspecialchars(trim(($turno['nombre_medico'] ?? '') . ' ' . ($turno['apellido_medico'] ?? '')));
                            } else {
                                echo "Sin asignar";
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($turno['estudio_id']) {
                                echo htmlspecialchars($turno['sede_estudio'] ?? 'Sin sede');
                            } elseif ($turno['medico_id']) {
                                echo htmlspecialchars($turno['sede_medico'] ?? 'Sin sede');
                            } else {
                                echo 'Sin sede';
                            }
                            ?>
                        </td>
                        <td><?php echo ucfirst(htmlspecialchars($turno['estado'])); ?></td>
                        <td>
                            <?php if (strtolower($turno['estado']) !== 'cancelado'): ?>
                                <form method="post" action="cancelarTurno.php" style="margin:0;">
                                    <input type="hidden" name="turno_id" value="<?php echo $turno['id']; ?>" />
                                    <button type="submit" onclick="return confirm('¿Seguro que querés cancelar este turno?');">Cancelar</button>
                                </form>
                            <?php else: ?>
                                Cancelado
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>

</html>
