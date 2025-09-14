<?php
// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../../../Persistencia/conexionBD.php';
require_once '../../../../Logica/General/verificarSesion.php';

$conn = ConexionBD::conectar();

// Obtener tipos de estudio
$tipos = $conn->query("SELECT id, nombre FROM tipos_estudio");

// Obtener sedes
$sedes = $conn->query("SELECT id, nombre FROM sedes");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Estudio</title>
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
        .card-form {
      background: rgba(0, 22, 41, 0.9);
      /* card oscura */
      padding: 30px;
      border-radius: 12px;
      width: 400px;
      color: #fff;
      box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.3);
      margin: auto;
    }

    .card-form h1 {
      text-align: center;
      margin-bottom: 25px;
      color: #fff;
    }

    .card-form label {
      display: block;
      margin: 10px 0 5px;
      font-weight: bold;
      font-size: 0.9em;
    }

    .card-form select,
    .card-form input {
      width: 100%;
      padding: 10px;
      border-radius: 6px;
      border: none;
      margin-bottom: 15px;
      font-size: 0.95em;
    }

    .card-form button {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 6px;
      background: #00b4ff;
      color: #fff;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.3s;
    }

    .card-form button:hover {
      background: #008ccc;
    }
        #resultado-busqueda {
      margin-top: 20px;
      background: rgba(255, 255, 255, 0.1);
      padding: 15px;
      border-radius: 8px;
      max-height: 300px;
      overflow-y: auto;
    }

    #resultado-busqueda div {
      background: rgba(255, 255, 255, 0.15);
      padding: 10px;
      margin: 10px 0;
      border-radius: 6px;
    }

    #resultado-busqueda h4 {
      margin: 0;
      color: #00ff99;
    }

    #resultado-busqueda button {
      background: #1e88e5;
      margin-top: 5px;
      padding: 6px 12px;
      border-radius: 5px;
    }

    #resultado-busqueda button:hover {
      background: #1565c0;
    }
    
    .btn-volver {
  display: block;            
  width: 100%;              
  padding: 12px;
  border-radius: 6px;
  background: #00b4ff;
  color: #fff;
  font-weight: bold;
  cursor: pointer;
  text-decoration: none;
  text-align: center;
  transition: background 0.3s;
  margin-top: 10px;          
}

.btn-volver:hover {
  background: #008ccc;
}
    </style>
</head>
<body>
    <nav>
            <ul>
                <div class="nav-links">
                  <li><a href="../../principalPac.php">Inicio</a></li>
                  <li><a href="../misTurnos.php">Mis Turnos</a></li>
                  <li><a href="../../verCredencial.php">Ver credencial</a></li>
                  <li><input type="text" placeholder="Buscar..." />
                      <button>Buscar</button>
                  </li>
                  <li><a href="../../../../Logica/General/cerrarSesion.php">Cerrar Sesión</a></li>
                </div>


                <div class="perfil">
                    <span><?php echo strtoupper($_SESSION['apellido']) . ", " . ucfirst(strtolower($_SESSION['nombre'])); ?></span>
                    <img src="../../assets/img/loginAdmin.jpg" alt="Foto perfil">
                </div>
            </ul>
        </nav>

    <!-- CONTENEDOR TARJETA -->
    <div class="container">
        <div class="card-form">
        <h1>Solicitar Estudio</h1>
        <form id="form-estudio">
            <label for="tipoEstudio">Tipo de estudio:</label>
            <select name="tipoEstudio" id="tipoEstudio" required>
                <option value="">Seleccione...</option>
                <?php while($row = $tipos->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= $row['nombre'] ?></option>
                <?php endwhile; ?>
            </select>

            <label for="sede">Sede:</label>
            <select name="sede" id="sede" required>
                <option value="">Seleccione...</option>
                <?php while($row = $sedes->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= $row['nombre'] ?></option>
                <?php endwhile; ?>
            </select>

            <button type="button" onclick="buscarEstudios()">Buscar Estudios</button>
            <div>
                <a class="btn-volver" href="../../principalPac.php">VOLVER</a>
            </div>
        </form>

        <div id="resultado-busqueda"></div>
     </div>
    </div>
    

    <script>
        function buscarEstudios() {
    const tipo = document.getElementById('tipoEstudio').value;
    const sede = document.getElementById('sede').value;

    // Solo verificamos que al menos uno esté seleccionado
    if (!tipo && !sede) {
        alert("Debe seleccionar al menos tipo de estudio o sede.");
        return;
    }

    fetch('../../../../Logica/Paciente/Gestion-Turnos/obtenerEstudios.php', {
        method: 'POST',
        body: new URLSearchParams({ tipoEstudio: tipo, sede: sede }),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
    .then(res => res.json())
    .then(data => {
        const contenedor = document.getElementById('resultado-busqueda');
        contenedor.innerHTML = '';
        if (data.length === 0) {
            contenedor.innerHTML = '<p>No se encontraron estudios.</p>';
            return;
        }

        data.forEach(estudio => {
            contenedor.innerHTML += `
                <div>
                    <h4>${estudio.nombre_estudio}</h4>
                    <p>${estudio.descripcion || ''}</p>
                    <button onclick="verDisponibilidad(${estudio.id_estudio})">Ver Disponibilidad</button>
                </div>
            `;
        });
    })
    .catch(err => {
        console.error('Error:', err);
        alert("Hubo un error al buscar estudios.");
    });
}


        function verDisponibilidad(idEstudio) {
            fetch('../../../../Logica/Paciente/Gestion-Turnos/verDisponibilidadEstudio.php', {
                method: 'POST',
                body: `id_estudio=${idEstudio}`,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            })
            .then(res => res.json())
            .then(data => {
                const contenedor = document.getElementById('resultado-busqueda');
                contenedor.innerHTML = `<h3>Disponibilidad del Estudio</h3>`;

                if (Object.keys(data).length === 0) {
                    contenedor.innerHTML += "<p>No hay turnos disponibles.</p>";
                    return;
                }

                for (const [fecha, horarios] of Object.entries(data)) {
                    contenedor.innerHTML += `<h4 style="color:lightgreen;">${fecha}</h4><ul>`;
                    horarios.forEach(horario => {
                        contenedor.innerHTML += `
                            <li>
                                ${horario.inicio} - ${horario.fin}
                                <button onclick="confirmarTurno('${fecha}', '${horario.inicio}', ${idEstudio})">Seleccionar</button>
                            </li>
                        `;
                    });
                    contenedor.innerHTML += '</ul>';
                }
            })
            .catch(err => {
                console.error('Error al obtener disponibilidad:', err);
                alert("Error al consultar la disponibilidad.");
            });
        }

        function confirmarTurno(fecha, horaInicio, idEstudio) {
            if (!confirm(`¿Confirmar turno el ${fecha} a las ${horaInicio}?`)) return;

            fetch('../../../../Logica/Paciente/Gestion-Turnos/confirmarTurnoEstudio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    fecha: fecha,
                    hora_inicio: horaInicio,
                    id_estudio: idEstudio
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.mensaje);
                    verDisponibilidad(idEstudio);
                } else {
                    alert("Error: " + data.error);
                }
            })
            .catch(err => {
                console.error('Error al confirmar turno:', err);
                alert("No se pudo confirmar el turno.");
            });
        }
    </script>
</body>
</html>
