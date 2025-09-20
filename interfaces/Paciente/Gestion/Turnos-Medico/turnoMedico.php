<?php
// MOSTRAR ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../../../Persistencia/conexionBD.php';
require_once '../../../../Logica/General/verificarSesion.php';

// Conexión a la base de datos
$conn = ConexionBD::conectar();

// Obtener especialidades
$especialidades = [];
$result = $conn->query("SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad");
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $especialidades[] = $row;
  }
}

// Obtener sedes
$sedes = [];
$result = $conn->query("SELECT id, nombre FROM sedes ORDER BY nombre");
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $sedes[] = $row;
  }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Solicitar turno médico</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css"/>
  <link rel="stylesheet" href="../../../../css/turnoMedico.css">
  <!-- <style>
    
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
            color: #1e88e5;
        }

        .perfil img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            /* círculo */
            object-fit: cover;
            border: 2px solid #1e88e5;
        }

        .fancybox__caption {
            font-size: 14px;
            font-weight: 500;
            font-family: 'Arial', sans-serif;
            color: #555;
            text-align: center;
            margin-top: 10px;
            letter-spacing: 0.5px;
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

  </style> -->
</head>

<body>
    <header>
        <nav>
            <ul>
                <div class="nav-links">
                  <li><a href="../../principalPac.php">Inicio</a></li>
                  <li><a href="../misTurnos.php">Mis Turnos</a></li>
                  <li>
                        <a data-fancybox
                        data-caption="Sistema Gestión Turnos - Credencial virtual afiliado"
                        data-type="iframe"
                        data-src="../../verCredencial.php"
                        data-width="800"
                        data-height="400"
                        href="javascript:;">
                        Ver credencial
                        </a>
                        </li>
                  <li><input type="text" placeholder="Buscar..." />
                      <button>Buscar</button>
                  </li>
                  <li><a href="../../../../Logica/General/cerrarSesion.php">Cerrar Sesión</a></li>
                </div>
                <div class="perfil">
                    <span><?php echo mb_strtoupper($_SESSION['apellido'], 'UTF-8') . ", " . mb_convert_case($_SESSION['nombre'], MB_CASE_TITLE, 'UTF-8'); ?></span>
                    <img src="../../../../assets/img/loginAdmin.png" alt="Foto perfil">
                </div>
            </ul>
        </nav>
    </header>
        
  <div class="container">
    <div class="card-form">
    <h1>Solicitar Turno Médico</h1>
    <form id="form-busqueda">
      <label for="especialidad">Especialidad:</label>
      <select name="especialidad" id="especialidad">
        <option value="">-- Todas --</option>
        <?php foreach ($especialidades as $esp): ?>
          <option value="<?= $esp['id_especialidad'] ?>"><?= htmlspecialchars($esp['nombre_especialidad']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="sede">Centro/Sede:</label>
      <select name="sede" id="sede">
        <option value="">-- Todas --</option>
        <?php foreach ($sedes as $sede): ?>
          <option value="<?= $sede['id'] ?>"><?= htmlspecialchars($sede['nombre']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="nombre_medico">Nombre del médico:</label>
      <input type="text" name="nombre_medico" id="nombre_medico" placeholder="Ej. Juan Pérez" />

      <button type="button" onclick="buscarMedicos()">Buscar</button>
      <div>
        <a class="btn-volver" href="../../principalPac.php">VOLVER</a>
      </div>
    </form>

    <div id="resultado-busqueda"></div>
  </div>
  </div>

  

  <script>
    function buscarMedicos() {
      const datos = {
        especialidad: document.getElementById("especialidad").value,
        sede: document.getElementById("sede").value,
        nombre_medico: document.getElementById("nombre_medico").value,
      };

      fetch('../../../../Logica/Paciente/Gestion-Turnos/obtenerMedicos.php', {
          method: 'POST',
          body: new URLSearchParams(datos),
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          }
        })
        .then(response => response.json())
        .then(data => {
          const contenedor = document.getElementById("resultado-busqueda");
          contenedor.innerHTML = '';
          if (data.length === 0) {
            contenedor.innerHTML = '<p>No se encontraron médicos con esos criterios.</p>';
            return;
          }
          data.forEach(medico => {
            contenedor.innerHTML += `
            <div>
              <h4>${medico.nombre} ${medico.apellido}</h4>
              <p>Especialidades: ${medico.especialidades.join(', ')}</p>
              <button onclick="verDisponibilidad(${medico.id_medico})">Ver Disponibilidad</button>
            </div>`;
          });
        })
        .catch(error => {
          console.error('Error al obtener médicos:', error);
          alert('Error al obtener médicos.');
        });
    }

    function verDisponibilidad(idMedico) {
      fetch('../../../../Logica/Paciente/Gestion-Turnos/verDisponibilidadMedico.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `id_medico=${encodeURIComponent(idMedico)}`
        })
        .then(response => response.json())
        .then(data => {
          const contenedor = document.getElementById("resultado-busqueda");
          contenedor.innerHTML = "<h3>Disponibilidad del médico</h3>";

          if (Object.keys(data).length === 0) {
            contenedor.innerHTML += "<p>No hay turnos disponibles.</p>";
            return;
          }

          for (const [fecha, horarios] of Object.entries(data)) {
            contenedor.innerHTML += `<h4 style="color:lightgreen;">${fecha}</h4><ul>`;
            horarios.forEach(horario => {
              contenedor.innerHTML += `
            <li>
              ${horario.inicio} a ${horario.fin}
              <button onclick="confirmarTurno('${fecha}', '${horario.inicio}', ${idMedico})">Seleccionar</button>
            </li>`;
            });
            contenedor.innerHTML += `</ul>`;
          }
        })
        .catch(err => {
          console.error("Error al obtener disponibilidad:", err);
          alert("No se pudo obtener la disponibilidad.");
        });
    }

    function confirmarTurno(fecha, horaInicio, idMedico) {
  if (!confirm(`¿Confirmar turno el ${fecha} a las ${horaInicio}?`)) return;

  fetch('../../../../Logica/Paciente/Gestion-Turnos/confirmarTurnoMedico.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({
        fecha: fecha,
        hora_inicio: horaInicio,
        id_medico: idMedico
      })
    })
    .then(response => response.text()) // Primero leemos como texto
    .then(text => {
      console.log("📦 Respuesta cruda del servidor:", text); // 🐞 Log importante

      try {
        const data = JSON.parse(text); // Intentamos convertir a JSON

        if (data.success) {
          alert(data.mensaje);
          verDisponibilidad(idMedico); // Volver a mostrar turnos disponibles
        } else {
          alert('❌ Error: ' + data.error);
        }
      } catch (e) {
        console.error("⚠️ JSON inválido:", e, text); // ⚠️ Mostrar el error
        alert("❌ Respuesta inválida del servidor. Revisa la consola.");
      }
    })
    .catch(err => {
      console.error("💥 Error en la solicitud fetch:", err);
      alert("No se pudo confirmar el turno. Error de red.");
    });
}

  </script>
  <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
</body>

</html>