<?php
// MOSTRAR ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../../../Persistencia/conexionBD.php';
require_once '../../../../Logica/General/verificarSesion.php';
require_once '../../../../interfaces/mostrarAlerta.php';

// Conexión a la base de datos
$conn = ConexionBD::conectar();

// 🆕 OBTENER DATOS DEL PACIENTE LOGUEADO
$id_usuario = $_SESSION['id_usuario'] ?? null;
$id_paciente = $_SESSION['id_paciente_token'] ?? null;

// 🆕 OBTENER DOCUMENTO DEL PACIENTE
$stmt = $conn->prepare("SELECT nro_documento FROM pacientes WHERE id_paciente = ?");
$stmt->bind_param("i", $id_paciente);
$stmt->execute();
$result = $stmt->get_result();
$paciente_actual = $result->fetch_assoc();
$nro_doc = $paciente_actual['nro_documento'] ?? null;

// 🆕 VERIFICAR SI ES TITULAR Y OBTENER AFILIADOS MENORES
$es_titular = false;
$afiliados_menores = [];

if ($nro_doc) {
    // Buscar si es titular
    $stmt = $conn->prepare("
        SELECT id 
        FROM afiliados 
        WHERE numero_documento = ? 
          AND tipo_beneficiario = 'titular'
        LIMIT 1
    ");
    $stmt->bind_param("s", $nro_doc);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $es_titular = true;
        $titular = $result->fetch_assoc();
        $id_titular = $titular['id'];

        // Obtener afiliados menores desde tabla afiliados
        $stmt = $conn->prepare("
            SELECT 
                a.id,
                a.numero_documento,
                a.numero_afiliado,
                a.nombre,
                a.apellido,
                a.fecha_nacimiento,
                a.tipo_beneficiario,
                a.estado,
                TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, CURDATE()) AS edad,
                p.id_paciente,
                u.nombre AS nombre_usuario,
                u.apellido AS apellido_usuario
            FROM afiliados a
            LEFT JOIN pacientes p ON p.nro_documento = a.numero_documento
            LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
            WHERE a.id_titular = ?
              AND a.estado = 'activo'
              AND a.tipo_beneficiario = 'hijo menor'
            ORDER BY a.nombre, a.apellido
        ");
        $stmt->bind_param("i", $id_titular);
        $stmt->execute();
        $result = $stmt->get_result();
        $afiliados_menores = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Obtener estudios
$estudios = [];
$result = $conn->query("SELECT id_estudio, nombre FROM estudios ORDER BY nombre");
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $estudios[] = $row;
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
  <title>Solicitar Estudio</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css"/>
  <link rel="stylesheet" href="../../../../css/turnoEstudio.css">
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
      background: #17a2b8;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
    }

    .calendar-nav button:hover {
      background: #138496;
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
      background: #17a2b8;
      color: white;
      border-color: #17a2b8;
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

    .estudio-info {
      background: #d1ecf1;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .estudio-info h4 {
      margin: 0 0 5px 0;
      color: #0c5460;
    }

    .estudio-info p {
      margin: 5px 0;
      color: #0c5460;
    }

    .preparacion-alert {
      background: #fff3cd;
      border: 1px solid #ffc107;
      padding: 15px;
      border-radius: 8px;
      margin-top: 10px;
    }

    .preparacion-alert h5 {
      margin: 0 0 10px 0;
      color: #856404;
    }

    .preparacion-alert p {
      margin: 0;
      color: #856404;
      font-size: 14px;
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

    #resultado-busqueda > div {
      background: #f8f9fa;
      padding: 15px;
      margin: 10px 0;
      border-radius: 8px;
      border: 1px solid #dee2e6;
    }

    #resultado-busqueda h4 {
      margin: 0 0 10px 0;
      color: #333;
    }

    #resultado-busqueda p {
      margin: 5px 0;
      color: #666;
      font-size: 14px;
    }

    #resultado-busqueda button {
      margin-top: 10px;
      padding: 8px 20px;
      background: #17a2b8;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    #resultado-busqueda button:hover {
      background: #138496;
    }

    /* 🆕 ESTILOS PARA SELECTOR DE ÓRDENES */
    .orden-medica-section {
      background: #fff3cd;
      border: 2px solid #ffc107;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
    }

    .orden-medica-section h4 {
      margin: 0 0 15px 0;
      color: #856404;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .orden-select-container {
      margin-bottom: 15px;
    }

    .orden-select-container label {
      display: block;
      font-weight: bold;
      margin-bottom: 8px;
      color: #856404;
    }

    .orden-select-container select {
      width: 100%;
      padding: 12px;
      border: 2px solid #ffc107;
      border-radius: 8px;
      font-size: 15px;
      background: white;
    }

    .orden-detalle {
      background: white;
      padding: 15px;
      border-radius: 8px;
      margin-top: 15px;
      display: none;
    }

    .orden-detalle.visible {
      display: block;
    }

    .orden-detalle p {
      margin: 8px 0;
      font-size: 14px;
      color: #333;
    }

    .orden-detalle strong {
      color: #856404;
    }

    .no-ordenes-alert {
      background: #f8d7da;
      border: 2px solid #f5c6cb;
      color: #721c24;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
      margin-bottom: 20px;
    }

    .no-ordenes-alert i {
      font-size: 48px;
      margin-bottom: 15px;
      display: block;
    }

    .no-ordenes-alert h4 {
      margin: 0 0 10px 0;
    }

    .no-ordenes-alert a {
      color: #721c24;
      text-decoration: underline;
      font-weight: bold;
    }
  </style>
</head>

<body>

  <?php include('../../navPac.php'); ?>
        
  <div class="container">
    <div class="card-form">
      <h1>Solicitar Estudio</h1>

<!-- 🆕 Selector de beneficiario -->
<?php if ($es_titular && !empty($afiliados_menores)): ?>
  <div style="background: #e7f3ff; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
    <h3 style="margin-top: 0;">¿Para quién es el estudio?</h3>
    <select id="selector-beneficiario" name="id_beneficiario"
            style="width: 100%; padding: 10px; font-size: 16px; border-radius: 5px; border: 2px solid #17a2b8;">
      
      <!-- Titular -->
      <option value="p-<?= $id_paciente ?>">
        Para mí (<?= htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) ?>)
      </option>

      <!-- Afiliados menores -->
      <?php foreach ($afiliados_menores as $afiliado): ?>
        <?php
          $nombre = $afiliado['nombre_usuario'] ?? $afiliado['nombre'];
          $apellido = $afiliado['apellido_usuario'] ?? $afiliado['apellido'];
          $edad = $afiliado['edad'] ?? null;
          $id_valor = 'a-' . $afiliado['id']; // 👈 prefijo "a-" siempre para afiliados
        ?>
        <option value="<?= htmlspecialchars($id_valor) ?>">
          <?= htmlspecialchars("$nombre $apellido") ?>
          <?= $edad ? "- {$edad} años" : "" ?>
          (<?= htmlspecialchars(ucfirst($afiliado['tipo_beneficiario'])) ?>)
        </option>
      <?php endforeach; ?>
    </select>

    <p style="margin: 10px 0 0 0; font-size: 14px; color: #666;">
      <i class="fas fa-info-circle"></i> Puede solicitar estudios para sus afiliados menores de 18 años.
    </p>
  </div>
<?php endif; ?>


      <form id="form-estudio">
        <label for="estudio">Estudio:</label>
        <select name="estudio" id="estudio">
          <option value="">-- Todos --</option>
          <?php foreach ($estudios as $est): ?>
            <option value="<?= $est['id_estudio'] ?>"><?= htmlspecialchars($est['nombre']) ?></option>
          <?php endforeach; ?>
        </select>

        <label for="sede">Centro/Sede:</label>
        <select name="sede" id="sede">
          <option value="">-- Todas --</option>
          <?php foreach ($sedes as $sede): ?>
            <option value="<?= $sede['id_sede'] ?>"><?= htmlspecialchars($sede['nombre']) ?></option>
          <?php endforeach; ?>
        </select>

        <button type="button" onclick="buscarEstudios()">Buscar</button>
        <div>
          <a class="btn-volver" href="../../principalPac.php">VOLVER</a>
        </div>
      </form>

      <div id="resultado-busqueda"></div>
    </div>
  </div>

  <!-- Calendario -->
  <div id="calendar-container">
    <!-- 🆕 SECCIÓN DE ORDEN MÉDICA REQUERIDA -->
    <div id="seccion-orden-medica" style="display:none"></div>

    <div class="estudio-info" id="estudio-info"></div>
    
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
    let estudioSeleccionado = null;
    let estudioNombre = '';
    let estudioInfo = {};
    let mesActual = new Date().getMonth();
    let anioActual = new Date().getFullYear();
    let diasDisponibles = [];
    let recursoSeleccionado = null;
    let ordenSeleccionada = null;  // 🆕 ID de la orden médica seleccionada
    let ordenesDisponibles = [];    // 🆕 Array de órdenes disponibles
    let tieneOrdenes = false;
    const MESES_ADELANTE = 1;

function buscarEstudios() {
  const datos = {
    estudio: document.getElementById("estudio").value,
    sede: document.getElementById("sede").value
  };

  fetch('../../../../Logica/Paciente/Gestion-Turnos/obtenerEstudios.php', {
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
      
      if (!Array.isArray(data)) {
        contenedor.innerHTML = '<p>Error al obtener estudios.</p>';
         mostrarAlerta('error', '❌ Error al obtener estudios.');
        return;
      }
      
      if (data.length === 0) {
        contenedor.innerHTML = '<p>No se encontraron estudios con esos criterios.</p>';
        mostrarAlerta('warning', '⚠️ No se encontraron estudios con esos criterios.');
        return;
      }
      
      data.forEach(estudio => {
        let listaTecnicos = '';
        if (estudio.tecnicos) {
          if (Array.isArray(estudio.tecnicos)) {
            listaTecnicos = estudio.tecnicos.join(', ');
          } else {
            listaTecnicos = estudio.tecnicos;
          }
        }

        let html = `
        <div>
          <h4>${estudio.nombre}</h4>
          <p><strong>Sede:</strong> ${estudio.sede}</p>
          ${listaTecnicos ? `<p><strong>Técnico(s):</strong> ${listaTecnicos}</p>` : ''}
        `;

        if (estudio.requiere_preparacion == 1 && estudio.instrucciones) {
          html += `<p><strong>⚠️ Requiere preparación</strong></p>`;
        }
        
        html += `<button onclick='verDisponibilidad(${JSON.stringify(estudio).replace(/'/g, "&apos;")})'>Ver Disponibilidad</button>
        </div>`;
        
        contenedor.innerHTML += html;
      });
    })
    .catch(error => {
      console.error('Error al obtener estudios:', error);
       mostrarAlerta('error', '❌ Error al obtener estudios. Intente nuevamente más tarde.');
    });
}

  // 🆕 CARGAR ÓRDENES MÉDICAS DISPONIBLES
  async function cargarOrdenesParaEstudio(idEstudio, idBeneficiario = null) {
  try {
    const url = new URL('../../../../Logica/Paciente/Gestion-Turnos/obtenerOrdenesDisponibles.php', window.location.origin);
    url.searchParams.append('id_estudio', idEstudio);
    
    // 🔹 SI SE PASÓ UN BENEFICIARIO, ENVIARLO
    if (idBeneficiario) {
      url.searchParams.append('id_beneficiario', idBeneficiario);
    }

    console.log('🔍 Cargando órdenes para:', { idEstudio, idBeneficiario });

    const res = await fetch(url);
    const data = await res.json();

    console.log('📦 Órdenes recibidas:', data);

    if (!data.ok || !data.ordenes || data.ordenes.length === 0) {
      ordenesDisponibles = [];
      return [];
    }

    ordenesDisponibles = data.ordenes;
    return data.ordenes;

  } catch (error) {
    console.error('❌ Error al cargar órdenes:', error);
    ordenesDisponibles = [];
    return [];
  }
}

    
    // 🆕 RENDERIZAR SELECTOR DE ÓRDENES
    function renderizarSelectorOrdenes(ordenes) {
    const seccion = document.getElementById('seccion-orden-medica');

    if (!ordenes || ordenes.length === 0) {
        seccion.innerHTML = `
            <div class="no-ordenes-alert">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <h4>⚠️ No tenés órdenes médicas válidas para este estudio</h4>
                <p>Necesitás una orden médica firmada por un médico para solicitar este estudio.</p>
                <p><a href="../../mis_ordenes.php">Ver mis órdenes médicas</a> o consultá con tu médico.</p>
            </div>
        `;
        seccion.style.display = 'block';
        document.getElementById('calendar-grid').style.opacity = '0.3';
        document.getElementById('calendar-grid').style.pointerEvents = 'none';
        return false;
    }

    let selectHTML = '<option value="">-- Seleccioná una orden médica --</option>';
    ordenes.forEach(orden => {
        const fecha = new Date(orden.fecha_emision).toLocaleDateString('es-AR');
        selectHTML += `<option value="${orden.id_orden}">
            ${orden.medico_nombre} - ${fecha} - ${orden.diagnostico.substring(0, 50)}...
        </option>`;
    });

    seccion.innerHTML = `
        <div class="orden-medica-section">
            <h4><i class="fa-solid fa-file-medical"></i> Orden Médica Requerida</h4>
            <div class="orden-select-container">
                <label for="select-orden">Seleccioná la orden médica con la que solicitás este estudio:</label>
                <select id="select-orden" onchange="mostrarDetalleOrden(this.value)">
                    ${selectHTML}
                </select>
            </div>
            <div id="orden-detalle" class="orden-detalle"></div>
        </div>
    `;
    seccion.style.display = 'block';
    document.getElementById('calendar-grid').style.opacity = '1';
    document.getElementById('calendar-grid').style.pointerEvents = 'auto';
    return true;
}

    // 🆕 MOSTRAR DETALLE DE LA ORDEN SELECCIONADA
    window.mostrarDetalleOrden = function(idOrden) {
    const detalle = document.getElementById('orden-detalle');
    if (!idOrden) {
        detalle.classList.remove('visible');
        ordenSeleccionada = null;
        return;
    }

    ordenSeleccionada = parseInt(idOrden);
    const orden = ordenesDisponibles.find(o => o.id_orden == idOrden);
    if (!orden) return;

    detalle.innerHTML = `
        <p><strong>Médico:</strong> ${orden.medico_nombre} (Mat. ${orden.medico_matricula})</p>
        <p><strong>Diagnóstico:</strong> ${orden.diagnostico}</p>
        <p><strong>Estudios:</strong> ${orden.estudios_nombres}</p>
        <p><strong>Fecha emisión:</strong> ${new Date(orden.fecha_emision).toLocaleDateString('es-AR')}</p>
        <p style="color:#28a745;font-weight:bold;"><i class="fa-solid fa-shield-check"></i> Firma digital verificada</p>
    `;
    detalle.classList.add('visible');
};

async function cargarYRenderizarOrdenes(estudioId) {
    // Si tenés un select de beneficiario, obtenemos su id
    const idBeneficiario = parseInt(document.getElementById('select-beneficiario')?.value) || null;

    // Llamada a la función que devuelve las órdenes disponibles
    ordenesDisponibles = await cargarOrdenesDisponibles(estudioId, idBeneficiario);

    // Renderizamos las órdenes en el selector
    renderizarSelectorOrdenes(ordenesDisponibles);
}

    async function verDisponibilidad(estudio) {
  estudioSeleccionado = estudio.id_estudio;
  estudioNombre = estudio.nombre;
  estudioInfo = estudio;
  
  const hoy = new Date();
  mesActual = hoy.getMonth();
  anioActual = hoy.getFullYear();
  
  // 🆕 OBTENER ID DEL BENEFICIARIO SELECCIONADO
  let idBeneficiario = obtenerIdBeneficiarioReal();

  
  // 🆕 CARGAR ÓRDENES DISPONIBLES PARA ESTE ESTUDIO Y BENEFICIARIO
  const ordenes = await cargarOrdenesParaEstudio(estudioSeleccionado, idBeneficiario);
  const hayOrdenes = renderizarSelectorOrdenes(ordenes);
  
  // Info del estudio
  let infoHtml = `<h4>${estudio.nombre}</h4>
                  <p>Sede: ${estudio.sede}</p>`;
  
  if (hayOrdenes) {
    infoHtml += `<p style="color:#28a745">✅ Seleccioná una orden médica y luego elegí un día disponible</p>`;
  }
  
  if (estudio.requiere_preparacion == 1 && estudio.instrucciones) {
    infoHtml += `
      <div class="preparacion-alert">
        <h5><i class="fas fa-exclamation-triangle"></i> Preparación requerida</h5>
        <p>${estudio.instrucciones}</p>
      </div>`;
  }
  
  document.getElementById('estudio-info').innerHTML = infoHtml;
  document.getElementById('calendar-container').style.display = 'block';
  document.getElementById('resultado-busqueda').style.display = 'none';
  
  // Solo cargar calendario si hay órdenes
  if (hayOrdenes) {
    cargarCalendario();
  }
  
  document.getElementById('calendar-container').scrollIntoView({ behavior: 'smooth' });
}

   const selectBeneficiario = document.getElementById('selector-beneficiario');
if (selectBeneficiario) {
    selectBeneficiario.addEventListener('change', async function() {
        const val = this.value;
        if (!val) return;
        
        const idBeneficiario = obtenerIdBeneficiarioReal();
        
        console.log('🔄 Beneficiario cambiado a:', idBeneficiario);
        
        // Si ya hay un estudio seleccionado, recargar órdenes
        if (estudioSeleccionado) {
            const ordenes = await cargarOrdenesParaEstudio(estudioSeleccionado, idBeneficiario);
            renderizarSelectorOrdenes(ordenes);
        }
    });
}

function obtenerIdBeneficiarioReal() {
    const selector = document.getElementById('selector-beneficiario');
    if (!selector || !selector.value) return 0; // titular por defecto

    const val = selector.value;  // ejemplo: "p-37" o "a-47"
    const [tipo, id] = val.split('-');

    if (tipo === 'a') {
        return parseInt(id); // afiliado menor → usar id_afiliado
    }

    // titular
    return 0;
}



    function cargarCalendario() {
  fetch('../../../../Logica/Paciente/Gestion-Turnos/obtenerDisponibilidad.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      tipo: 'estudio',
      id_estudio: estudioSeleccionado,
      mes: mesActual + 1,
      anio: anioActual
    })
  })
  .then(response => response.json())
  .then(data => {
    // Asumir que la respuesta tiene 'dias_disponibles' como array de fechas (ej. ['2023-10-01', ...])
    // Si no, ajusta según lo que devuelva obtenerDisponibilidad.php
    diasDisponibles = (data && Array.isArray(data.dias_disponibles)) ? data.dias_disponibles : [];
    renderizarCalendario();  // Renderizar el calendario con los días disponibles
  })
  .catch(error => {
    console.error('Error al cargar calendario:', error);
    diasDisponibles = [];  // En caso de error, vacío
    renderizarCalendario();
  });
}

  function renderizarCalendario() {
  const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  
  document.getElementById('calendar-month-year').textContent = 
    `${meses[mesActual]} ${anioActual}`;
  
  const grid = document.getElementById('calendar-grid');
  grid.innerHTML = '';
  
  const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
  diasSemana.forEach(dia => {
    const header = document.createElement('div');
    header.className = 'calendar-day-header';
    header.textContent = dia;
    grid.appendChild(header);
  });
  
  const primerDia = new Date(anioActual, mesActual, 1).getDay();
  const ultimoDia = new Date(anioActual, mesActual + 1, 0).getDate();
  
  const hoy = new Date();
  const hoyStr = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
  
  for (let i = 0; i < primerDia; i++) {
    const empty = document.createElement('div');
    empty.className = 'calendar-day empty';
    grid.appendChild(empty);
  }
  
  for (let dia = 1; dia <= ultimoDia; dia++) {
    const fechaStr = `${anioActual}-${String(mesActual + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
    const dayDiv = document.createElement('div');
    dayDiv.className = 'calendar-day';
    dayDiv.textContent = dia;
    
    if (fechaStr === hoyStr) {
      dayDiv.classList.add('today');
    }
    
    if (diasDisponibles.includes(fechaStr)) {
      dayDiv.classList.add('available');
      dayDiv.onclick = () => mostrarHorarios(fechaStr);
    } else {
      const fecha = new Date(anioActual, mesActual, dia);
      if (fecha >= hoy) {
        dayDiv.classList.add('unavailable');
      }
    }
    
    grid.appendChild(dayDiv);
  }
  
  const hoyMes = new Date();
  const mesMin = hoyMes.getMonth();
  const anioMin = hoyMes.getFullYear();
  const mesMax = (hoyMes.getMonth() + MESES_ADELANTE) % 12;
  const anioMax = hoyMes.getFullYear() + Math.floor((hoyMes.getMonth() + MESES_ADELANTE) / 12);
  
  const btnAnterior = document.querySelector('.calendar-nav');
  btnAnterior.disabled = (anioActual === anioMin && mesActual === mesMin);
  
  const btnSiguiente = document.querySelectorAll('.calendar-nav')[1];
  btnSiguiente.disabled = (anioActual === anioMax && mesActual === mesMax);
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
  // Validar que se haya seleccionado una orden
  if (!ordenSeleccionada) {
    mostrarAlerta('warning', '⚠️ Primero debes seleccionar una orden médica');
    return;
  }

  const modal = document.getElementById('modalHorarios');
  const contenedor = document.getElementById('horariosDisponibles');

  const [anio, mes, dia] = fecha.split('-');
  const fechaObj = new Date(anio, mes - 1, dia);
  const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  const fechaFormateada = fechaObj.toLocaleDateString('es-AR', opciones);

  document.getElementById('modal-fecha').textContent = fechaFormateada;
  contenedor.innerHTML = '<div class="loading">Cargando horarios...</div>';
  modal.style.display = 'block';

  fetch('../../../../Logica/Paciente/Gestion-Turnos/obtenerHorariosDia.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      tipo: 'estudio', // o 'medico' según corresponda
      id_estudio: estudioSeleccionado,
      fecha: fecha
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data && data.success && Array.isArray(data.horarios) && data.horarios.length > 0) {
      contenedor.innerHTML = '';
      data.horarios.forEach(horario => {
        const item = document.createElement('div');
        item.className = 'horario-item';
        
        // Si existe hora_fin se muestra, si no, solo hora_inicio
        item.textContent = horario.hora_fin 
            ? `${horario.hora_inicio} - ${horario.hora_fin}` 
            : `${horario.hora_inicio}`;

        item.onclick = () => confirmarTurno(fecha, horario.hora_inicio, horario.id_recurso);
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

 function confirmarTurno(fecha, horaInicio, idRecurso) {
  // 🧩 Validar orden
  if (!ordenSeleccionada) {
    mostrarAlerta('warning', '⚠️ Debes seleccionar una orden médica');
    return;
  }

  // 🧩 Obtener beneficiario seleccionado
  const selector = document.getElementById('selector-beneficiario');
  let beneficiarioId = '';
  let nombreBeneficiario = "<?= htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) ?>";

  if (selector && selector.value) {
    // 👇 CORRECCIÓN: Usar directamente el value del select
    beneficiarioId = selector.value;  // Ya viene como 'p-123' o 'a-456'
    
    // Nombre del beneficiario para mostrar
    const selectedOption = selector.options[selector.selectedIndex];
    nombreBeneficiario = selectedOption.text.split(' - ')[0];
    
    console.log('🔍 Beneficiario seleccionado:', beneficiarioId);
  } else {
    // Si no hay selector, es el paciente titular
    beneficiarioId = 'p-<?= $id_paciente ?>';
  }

  // 🧩 Confirmación
  const ordenInfo = ordenesDisponibles.find(o => o.id_orden == ordenSeleccionada);
  if (!ordenInfo) {
    mostrarAlerta('error', '⚠️ Error: No se encontró la información de la orden');
    return;
  }

  const confirmMsg = `¿Confirmar turno de ${estudioNombre}?\n\n` +
                    `👤 Para: ${nombreBeneficiario}\n` +
                    `📅 Fecha: ${fecha}\n` +
                    `🕐 Hora: ${horaInicio}\n` +
                    `📋 Orden médica: ${ordenInfo.medico_nombre}\n` +
                    `💊 Diagnóstico: ${ordenInfo.diagnostico.substring(0, 50)}...`;

  Swal.fire({
    title: '¿Confirmar turno?',
    text: confirmMsg,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#4CAF50', // Verde
    cancelButtonColor: '#F44336', // Rojo
  }).then(result => {
    if (result.isConfirmed) {
      // 🧩 Enviar solicitud
      const formData = new URLSearchParams({
        fecha: fecha,
        hora_inicio: horaInicio,
        id_estudio: estudioSeleccionado,
        id_recurso: idRecurso || '',
        id_orden_medica: ordenSeleccionada,
        beneficiario_id: beneficiarioId
      });

      console.log('📤 Datos enviados:', Object.fromEntries(formData));

      fetch('../../../../Logica/Paciente/Gestion-Turnos/confirmarTurnoEstudio.php', {
        method: 'POST',
        body: formData,
        credentials: 'include'
      })
      .then(res => res.text())
      .then(text => {
        console.log("📦 Respuesta cruda del servidor:", text);
        try {
          const data = JSON.parse(text);
          if (data.success) {
            Swal.fire({
              title: 'Turno confirmado',
              text: data.mensaje,
              icon: 'success',
              confirmButtonColor: '#4CAF50', // Verde
            }).then(() => {
              cerrarModalHorarios();
              window.location.reload(); // Recargar la página
            });
          } else {
            Swal.fire({
              title: 'Error',
              text: '❌ ' + data.error,
              icon: 'error',
              confirmButtonColor: '#F44336', // Rojo
            });
          }
        } catch (e) {
          console.error("⚠️ JSON inválido:", e);
          Swal.fire({
            title: 'Error',
            text: "❌ Respuesta inválida del servidor:\n" + text,
            icon: 'error',
            confirmButtonColor: '#F44336', // Rojo
          });
        }
      })
      .catch(err => {
        console.error("💥 Error en la solicitud fetch:", err);
        Swal.fire({
          title: 'Error',
          text: "No se pudo confirmar el turno. Error de red.",
          icon: 'error',
          confirmButtonColor: '#F44336', // Rojo
        });
      });
    }
  });
}


    function cerrarModalHorarios() {
      document.getElementById('modalHorarios').style.display = 'none';
    }

    window.onclick = function(event) {
      const modal = document.getElementById('modalHorarios');
      if (event.target === modal) {
        cerrarModalHorarios();
      }
    }
  </script>
  
  <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>

      <!-- FOOTER REUTILIZABLE -->
  <?php include '../../../footer.php'; ?>
</body>

</html>

