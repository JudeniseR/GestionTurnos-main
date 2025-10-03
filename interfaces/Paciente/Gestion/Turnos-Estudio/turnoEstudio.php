<?php
// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../../../Persistencia/conexionBD.php';
require_once '../../../../Logica/General/verificarSesion.php';

$conn = ConexionBD::conectar();

// Obtener estudios (tipos de estudio)
$tipos = $conn->query("SELECT id_estudio, nombre FROM estudios ORDER BY nombre");

// Obtener sedes
$sedes = $conn->query("SELECT id_sede, nombre FROM sedes ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Estudio</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
    <link rel="stylesheet" href="../../../../css/turnoEstudio.css">
    <style>
        #calendar {
            max-width: 900px;
            margin: 30px auto;
        }
    </style>
</head>
<body>
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

    <!-- CONTENEDOR TARJETA -->
    <div class="container">
        <div class="card-form">
            <h1>Solicitar Estudio</h1>
            <form id="form-estudio">
                <label for="tipoEstudio">Tipo de estudio:</label>
                <select name="tipoEstudio" id="tipoEstudio" required>
                    <option value="">Seleccione...</option>
                    <?php while($row = $tipos->fetch_assoc()): ?>
                        <option value="<?= $row['id_estudio'] ?>"><?= $row['nombre'] ?></option>
                    <?php endwhile; ?>
                </select>

                <label for="sede">Sede:</label>
                <select name="sede" id="sede" required>
                    <option value="">Seleccione...</option>
                    <?php while($row = $sedes->fetch_assoc()): ?>
                        <option value="<?= $row['id_sede'] ?>"><?= $row['nombre'] ?></option>
                    <?php endwhile; ?>
                </select>

                <button type="button" onclick="buscarEstudios()">Buscar Estudios</button>
                <div>
                    <a class="btn-volver" href="../../principalPac.php">VOLVER</a>
                </div>
            </form>

            <div id="resultado-busqueda"></div>
            <div id="calendar" style="display:none;"></div>
        </div>
    </div>

    <!-- Modal para selección de horario -->
    <div id="modalHorarios" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background:white; width:300px; margin:100px auto; padding:20px; border-radius:10px; text-align:center;">
            <h3>Seleccioná un horario</h3>
            <div id="horariosDisponibles"></div>
            <br>
            <button onclick="cerrarModalHorarios()">Cancelar</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

    <script>
        let estudioSeleccionado = null;
        let calendar;

        function buscarEstudios() {
            const tipo = document.getElementById('tipoEstudio').value;
            const sede = document.getElementById('sede').value;

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
                document.getElementById('calendar').style.display = 'none';

                if (!Array.isArray(data) || data.length === 0) {
                    contenedor.innerHTML = '<p>No se encontraron estudios.</p>';
                    return;
                }

                data.forEach(estudio => {
                    contenedor.innerHTML += `
                        <div>
                            <h4>${estudio.nombre_estudio}</h4>
                            <p>${estudio.descripcion || ''}</p>
                            <button onclick="verDisponibilidadCalendario(${estudio.id_estudio})">Ver Disponibilidad</button>
                        </div>
                    `;
                });
            })
            .catch(err => {
                console.error('Error en fetch:', err);
                alert("Hubo un error al buscar estudios.");
            });
        }

        function verDisponibilidadCalendario(idEstudio) {
            estudioSeleccionado = idEstudio;
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
                    url: '../../../../Logica/Paciente/Gestion-Turnos/obtenerDisponibilidadEstudiosCalendario.php',
                    method: 'POST',
                    extraParams: {
                        id_estudio: idEstudio
                    },
        failure: () => {
            alert('Error al cargar disponibilidad');
        }
    },
    eventDidMount: function(info) {
        console.log('Evento montado:', info.event);
    },
    
    eventClick: function(info) {
    const evento = info.event;
    const fecha = evento.startStr;
    const horarios = evento.extendedProps.horarios;

    if (!horarios || horarios.length === 0) {
        alert("No hay horarios disponibles para este día.");
        return;
    }

    const contenedor = document.getElementById('horariosDisponibles');
    contenedor.innerHTML = ''; // Limpiar horarios anteriores

    horarios.forEach(hora => {
        const boton = document.createElement('button');
        boton.textContent = hora;
        boton.style.margin = '5px';
        boton.onclick = () => {
            cerrarModalHorarios();
            confirmarTurno(fecha, hora);
        };
        contenedor.appendChild(boton);
    });

    // Mostrar el modal
    document.getElementById('modalHorarios').style.display = 'block';
}


});


            calendar.render();
        }

        function confirmarTurno(fecha, horaInicio) {
            if (!estudioSeleccionado) {
                alert("No se ha seleccionado un estudio.");
                return;
            }

            fetch('../../../../Logica/Paciente/Gestion-Turnos/confirmarTurnoEstudio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    fecha: fecha,
                    hora_inicio: horaInicio,
                    id_estudio: estudioSeleccionado
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.mensaje);
                    calendar.refetchEvents();
                } else {
                    alert("Error: " + data.error);
                }
            })
            .catch(err => {
                console.error('Error al confirmar turno:', err);
                alert("No se pudo confirmar el turno.");
            });
        }

        function cerrarModalHorarios() {
            document.getElementById('modalHorarios').style.display = 'none';
        }

    </script>

</body>
</html>