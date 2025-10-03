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

// Obtener sedes (corregido: usar id_sede en lugar de id)
$sedes = [];
$result = $conn->query("SELECT id_sede, nombre FROM sedes ORDER BY nombre");
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
  <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
  <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
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
                  <li>
                      <input type="text" placeholder="Buscar..." />
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
          <option value="<?= $sede['id_sede'] ?>"><?= htmlspecialchars($sede['nombre']) ?></option>
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
    
    let calendar;
    let medicoSeleccionado = null;

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
    medicoSeleccionado = idMedico;
    const calendarEl = document.getElementById('calendar');
    calendarEl.style.display = 'block';

    if (calendar) {
        calendar.destroy();
    }

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        events: {
            url: '../../../../Logica/Paciente/Gestion-Turnos/obtenerDisponibilidadMedicoCalendario.php',
            method: 'POST',
            extraParams: {
                id_medico: idMedico
            },
            failure: () => {
                alert('Error al cargar disponibilidad');
            }
        },
        eventClick: function(info) {
            const fecha = info.event.startStr;
            const horarios = info.event.extendedProps.horarios;

            if (!horarios || horarios.length === 0) {
                alert("No hay horarios disponibles para este día.");
                return;
            }

            const contenedor = document.getElementById('horariosDisponibles');
            contenedor.innerHTML = '';

            horarios.forEach(hora => {
                const boton = document.createElement('button');
                boton.textContent = hora;
                boton.style.margin = '5px';
                boton.onclick = () => {
                    cerrarModalHorarios();
                    confirmarTurno(fecha, hora, idMedico);
                };
                contenedor.appendChild(boton);
            });

            document.getElementById('modalHorarios').style.display = 'block';
        }
    });

    calendar.render();
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
        .then(response => response.text())
        .then(text => {
          console.log("📦 Respuesta cruda del servidor:", text);
          try {
            const data = JSON.parse(text);
            if (data.success) {
              alert(data.mensaje);
              verDisponibilidad(idMedico);
            } else {
              alert('❌ Error: ' + data.error);
            }
          } catch (e) {
            console.error("⚠️ JSON inválido:", e, text);
            alert("❌ Respuesta inválida del servidor. Revisa la consola.");
          }
        })
        .catch(err => {
          console.error("💥 Error en la solicitud fetch:", err);
          alert("No se pudo confirmar el turno. Error de red.");
        });
    }

    function cerrarModalHorarios() {
      document.getElementById('modalHorarios').style.display = 'none';
    }

  </script>
  <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>

  <div id="calendar" style="display:none; max-width: 900px; margin: 30px auto;"></div>

<!-- Modal para selección de horarios -->
<div id="modalHorarios" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background:white; width:300px; margin:100px auto; padding:20px; border-radius:10px; text-align:center;">
        <h3>Seleccioná un horario</h3>
        <div id="horariosDisponibles"></div>
        <br>
        <button onclick="cerrarModalHorarios()">Cancelar</button>
    </div>
</div>

</body>

</html>
