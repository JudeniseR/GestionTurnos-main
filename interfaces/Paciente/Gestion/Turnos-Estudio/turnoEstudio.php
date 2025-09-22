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
    <link rel="stylesheet" href="../../../../css/turnoEstudio.css">
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
        </div>
    </div>

    <script>
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
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
</body>
</html>
