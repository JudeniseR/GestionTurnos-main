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
  <link rel="stylesheet" href="../../../../css/principalPac.css">
  <style>
    /* Estilos del calendario */
    #calendar-container {
      display: none;
      max-width: 900px;
      margin: 30px auto;
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .calendar-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
    }

    .calendar-header h3 {
      margin: 0;
      color: #333;
    }

    .calendar-nav {
      display: flex;
      gap: 10px;
    }

    .calendar-nav button {
      padding: 8px 15px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
    }

    .calendar-nav button:hover {
      background: #0056b3;
    }

    .calendar-nav button:disabled {
      background: #ccc;
      cursor: not-allowed;
    }

    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 5px;
    }

    .calendar-day-header {
      text-align: center;
      padding: 10px;
      font-weight: bold;
      background: #e9ecef;
      border-radius: 5px;
      font-size: 14px;
    }

    .calendar-day {
      aspect-ratio: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid #dee2e6;
      border-radius: 8px;
      font-size: 16px;
      cursor: default;
      transition: all 0.3s;
      background: #f8f9fa;
      color: #999;
    }

    .calendar-day.empty {
      border: none;
      background: transparent;
    }

    .calendar-day.available {
      background: #28a745;
      color: white;
      border-color: #28a745;
      cursor: pointer;
      font-weight: bold;
    }

    .calendar-day.available:hover {
      background: #218838;
      transform: scale(1.05);
    }

    .calendar-day.unavailable {
      background: #dc3545;
      color: white;
      border-color: #dc3545;
      cursor: not-allowed;
    }

    .calendar-day.today {
      border: 3px solid #ffc107;
    }

    /* Modal de horarios */
    #modalHorarios {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.6);
      z-index: 1000;
      animation: fadeIn 0.3s;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-content {
      background: white;
      width: 90%;
      max-width: 400px;
      margin: 80px auto;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 5px 30px rgba(0,0,0,0.3);
      animation: slideDown 0.3s;
    }

    @keyframes slideDown {
      from {
        transform: translateY(-50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .modal-content h3 {
      margin-top: 0;
      color: #333;
      text-align: center;
      font-size: 22px;
    }

    .modal-fecha {
      text-align: center;
      color: #666;
      margin-bottom: 20px;
      font-size: 16px;
    }

    #horariosDisponibles {
      max-height: 400px;
      overflow-y: auto;
      margin-bottom: 20px;
    }

    .horario-item {
      padding: 12px;
      margin: 8px 0;
      background: #f8f9fa;
      border: 2px solid #dee2e6;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s;
      text-align: center;
      font-size: 16px;
      font-weight: 500;
    }

    .horario-item:hover {
      background: #007bff;
      color: white;
      border-color: #007bff;
      transform: translateX(5px);
    }

    .modal-buttons {
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    .modal-buttons button {
      padding: 10px 30px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 16px;
      transition: all 0.3s;
    }

    .btn-cancelar {
      background: #6c757d;
      color: white;
    }

    .btn-cancelar:hover {
      background: #545b62;
    }

    .loading {
      text-align: center;
      padding: 20px;
      color: #666;
    }

    .no-horarios {
      text-align: center;
      padding: 20px;
      color: #dc3545;
      font-size: 16px;
    }

    .medico-info {
      background: #e7f3ff;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
    }

    .medico-info h4 {
      margin: 0 0 5px 0;
      color: #0056b3;
    }

    .calendar-legend {
      display: flex;
      justify-content: center;
      gap: 30px;
      margin-top: 20px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .legend-color {
      width: 20px;
      height: 20px;
      border-radius: 5px;
    }

    .legend-color.available {
      background: #28a745;
    }

    .legend-color.unavailable {
      background: #dc3545;
    }
  </style>
</head>

<body>

  <?php include('../../navPac.php'); ?>
        
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

  <!-- Calendario -->
  <div id="calendar-container">
    <div class="medico-info" id="medico-info"></div>
    
    <div class="calendar-header">
      <button class="calendar-nav" onclick="cambiarMes(-1)">
        <i class="fas fa-chevron-left"></i> Anterior
      </button>
      <h3 id="calendar-month-year"></h3>
      <button class="calendar-nav" onclick="cambiarMes(1)">
        Siguiente <i class="fas fa-chevron-right"></i>
      </button>
    </div>
    
    <div class="calendar-grid" id="calendar-grid"></div>
    
    <div class="calendar-legend">
      <div class="legend-item">
        <div class="legend-color available"></div>
        <span>Días disponibles</span>
      </div>
      <div class="legend-item">
        <div class="legend-color unavailable"></div>
        <span>Días no disponibles</span>
      </div>
    </div>
  </div>

  <!-- Modal para selección de horarios -->
  <div id="modalHorarios">
    <div class="modal-content">
      <h3>Seleccione un horario</h3>
      <div class="modal-fecha" id="modal-fecha"></div>
      <div id="horariosDisponibles"></div>
      <div class="modal-buttons">
        <button class="btn-cancelar" onclick="cerrarModalHorarios()">Cancelar</button>
      </div>
    </div>
  </div>

  <script>
    let medicoSeleccionado = null;
    let medicoNombre = '';
    let mesActual = new Date().getMonth(); // 0-11
    let anioActual = new Date().getFullYear();
    let diasDisponibles = [];
    const MESES_ADELANTE = 3; // Mostrar hasta 3 meses adelante

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
              <button onclick="verDisponibilidad(${medico.id_medico}, '${medico.nombre} ${medico.apellido}')">Ver Disponibilidad</button>
            </div>`;
          });
        })
        .catch(error => {
          console.error('Error al obtener médicos:', error);
          alert('Error al obtener médicos.');
        });
    }

    function verDisponibilidad(idMedico, nombreMedico) {
      medicoSeleccionado = idMedico;
      medicoNombre = nombreMedico;
      
      // Resetear al mes actual
      const hoy = new Date();
      mesActual = hoy.getMonth();
      anioActual = hoy.getFullYear();
      
      // Mostrar info del médico
      document.getElementById('medico-info').innerHTML = `
        <h4>Dr/a. ${nombreMedico}</h4>
        <p>Seleccione un día disponible para ver los horarios</p>
      `;
      
      // Mostrar calendario y ocultar resultados
      document.getElementById('calendar-container').style.display = 'block';
      document.getElementById('resultado-busqueda').style.display = 'none';
      
      // Cargar calendario
      cargarCalendario();
      
      // Scroll al calendario
      document.getElementById('calendar-container').scrollIntoView({ behavior: 'smooth' });
    }

    function cargarCalendario() {
      // Obtener disponibilidad del mes
      fetch('../../../../Logica/Paciente/Gestion-Turnos/obtenerDisponibilidad.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          tipo: 'medico',
          id_medico: medicoSeleccionado,
          mes: mesActual + 1,
          anio: anioActual
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          diasDisponibles = data.dias_disponibles;
          renderizarCalendario();
        } else {
          alert('Error al cargar disponibilidad');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error al cargar el calendario');
      });
    }

    function renderizarCalendario() {
      const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                     'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
      
      // Actualizar título
      document.getElementById('calendar-month-year').textContent = 
        `${meses[mesActual]} ${anioActual}`;
      
      // Crear grid del calendario
      const grid = document.getElementById('calendar-grid');
      grid.innerHTML = '';
      
      // Headers de días de la semana
      const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
      diasSemana.forEach(dia => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header';
        header.textContent = dia;
        grid.appendChild(header);
      });
      
      // Primer día del mes y total de días
      const primerDia = new Date(anioActual, mesActual, 1).getDay();
      const ultimoDia = new Date(anioActual, mesActual + 1, 0).getDate();
      
      // Fecha de hoy
      const hoy = new Date();
      const hoyStr = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
      
      // Espacios vacíos antes del primer día
      for (let i = 0; i < primerDia; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        grid.appendChild(empty);
      }
      
      // Días del mes
      for (let dia = 1; dia <= ultimoDia; dia++) {
        const fechaStr = `${anioActual}-${String(mesActual + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const dayDiv = document.createElement('div');
        dayDiv.className = 'calendar-day';
        dayDiv.textContent = dia;
        
        // Marcar día de hoy
        if (fechaStr === hoyStr) {
          dayDiv.classList.add('today');
        }
        
        // Verificar si está disponible
        if (diasDisponibles.includes(fechaStr)) {
          dayDiv.classList.add('available');
          dayDiv.onclick = () => mostrarHorarios(fechaStr);
        } else {
          // Solo marcar como no disponible si es una fecha futura
          const fecha = new Date(anioActual, mesActual, dia);
          if (fecha >= hoy) {
            dayDiv.classList.add('unavailable');
          }
        }
        
        grid.appendChild(dayDiv);
      }
      
      // Controlar botones de navegación
      const hoyMes = new Date();
      const mesMin = hoyMes.getMonth();
      const anioMin = hoyMes.getFullYear();
      const mesMax = (hoyMes.getMonth() + MESES_ADELANTE) % 12;
      const anioMax = hoyMes.getFullYear() + Math.floor((hoyMes.getMonth() + MESES_ADELANTE) / 12);
      
      // Deshabilitar "Anterior" si estamos en el mes actual
      const btnAnterior = document.querySelector('.calendar-nav');
      if (anioActual === anioMin && mesActual === mesMin) {
        btnAnterior.disabled = true;
      } else {
        btnAnterior.disabled = false;
      }
      
      // Deshabilitar "Siguiente" si llegamos al límite
      const btnSiguiente = document.querySelectorAll('.calendar-nav')[1];
      if (anioActual === anioMax && mesActual === mesMax) {
        btnSiguiente.disabled = true;
      } else {
        btnSiguiente.disabled = false;
      }
    }

    function cambiarMes(direccion) {
      mesActual += direccion;
      
      if (mesActual > 11) {
        mesActual = 0;
        anioActual++;
      } else if (mesActual < 0) {
        mesActual = 11;
        anioActual--;
      }
      
      cargarCalendario();
    }

    function mostrarHorarios(fecha) {
      const modal = document.getElementById('modalHorarios');
      const contenedor = document.getElementById('horariosDisponibles');
      
      // Formatear fecha para mostrar
      const [anio, mes, dia] = fecha.split('-');
      const fechaObj = new Date(anio, mes - 1, dia);
      const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
      const fechaFormateada = fechaObj.toLocaleDateString('es-AR', opciones);
      
      document.getElementById('modal-fecha').textContent = fechaFormateada;
      contenedor.innerHTML = '<div class="loading">Cargando horarios...</div>';
      modal.style.display = 'block';
      
      // Obtener horarios del día
      fetch('../../../../Logica/Paciente/Gestion-Turnos/obtenerHorariosDia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          tipo: 'medico',
          id_medico: medicoSeleccionado,
          fecha: fecha
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success && data.horarios.length > 0) {
          contenedor.innerHTML = '';
          data.horarios.forEach(horario => {
  const item = document.createElement('div');
  item.className = 'horario-item';
  item.textContent = horario.hora_inicio; // ✅ Solo muestra hora de inicio
  item.onclick = () => confirmarTurno(fecha, horario.hora_inicio, medicoSeleccionado);
  contenedor.appendChild(item);
});
        } else {
          contenedor.innerHTML = '<div class="no-horarios">No hay horarios disponibles para este día</div>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        contenedor.innerHTML = '<div class="no-horarios">Error al cargar horarios</div>';
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
        .then(response => response.text())
        .then(text => {
          console.log("📦 Respuesta cruda del servidor:", text);
          try {
            const data = JSON.parse(text);
            if (data.success) {
              alert(data.mensaje);
              cerrarModalHorarios();
              cargarCalendario(); // Recargar calendario
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

    // Cerrar modal al hacer click fuera
    window.onclick = function(event) {
      const modal = document.getElementById('modalHorarios');
      if (event.target === modal) {
        cerrarModalHorarios();
      }
    }
  </script>

    <!-- FOOTER REUTILIZABLE -->
  <?php include '../../../footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>

</body>

</html>