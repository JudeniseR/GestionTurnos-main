/**
 * ========================================
 * GESTIÓN DE TURNOS - TÉCNICO (Frontend)
 * ========================================
 * Ruta: /interfaces/tecnico/turnosTecnico.js
 */

(function() {
  'use strict';

  // ========== CONFIGURACIÓN ==========
  const API = {
    listarTurnos: 'api/turnos_list_tecnico.php',
    ordenDetalle: 'api/turno_orden_detalle.php',
    marcarAtendido: 'api/turno_marcar_atendido.php' 
  };

  // ========== ELEMENTOS DOM ==========
  const listadoTurnos = document.getElementById('listadoTurnos');
 const buscarTurno = document.getElementById('buscarTurno');
const tabs = document.querySelectorAll('.tab[data-estado]');
let estadoActivo = 'confirmado';
  const modalOrden = document.getElementById('modalOrden');
  const cerrarModal = document.getElementById('cerrarModal');
  const modalBody = document.getElementById('modalBody');

  let todosTurnos = [];
  let timeoutBusqueda = null;

  // ========== FUNCIONES AUXILIARES ==========
  function formatFecha(fecha) {
  if (!fecha) return '-';
  // Evitar problema de zona horaria parseando manualmente
  const [year, month, day] = fecha.split('-');
  const d = new Date(year, month - 1, day);
  return d.toLocaleDateString('es-AR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
}

  function formatHora(hora) {
    if (!hora) return '-';
    return hora.substring(0, 5);
  }

  function formatFechaHora(timestamp) {
  if (!timestamp) return '-';
  const d = new Date(timestamp);
  return d.toLocaleDateString('es-AR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

  function mostrarToast(mensaje, tipo = 'info') {
    const toast = document.createElement('div');
    const colores = {
      success: '#10b981',
      error: '#ef4444',
      info: '#3b82f6'
    };

    toast.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      padding: 16px 24px;
      background: ${colores[tipo] || colores.info};
      color: white;
      border-radius: 10px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      z-index: 10000;
      font-weight: 600;
      max-width: 400px;
      animation: slideIn 0.3s;
    `;
    toast.textContent = mensaje;
    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = 'slideOut 0.3s';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }

  // ========== CARGAR TURNOS ==========
  async function cargarTurnos(busqueda = '', estado = '') {
  listadoTurnos.innerHTML = `
    <div class="loading">
      <i class="fa-solid fa-spinner"></i>
      <p>Cargando turnos...</p>
    </div>
  `;

  try {
    const params = new URLSearchParams();
    if (busqueda) params.append('q', busqueda);
    if (estado) params.append('estado', estado);

    const res = await fetch(`${API.listarTurnos}?${params}`);
    const data = await res.json();

    // AGREGAR ESTE LOG TEMPORAL
    console.log('Datos recibidos del backend:', data);

    if (!data.ok || !data.items || data.items.length === 0) {
      listadoTurnos.innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-calendar-xmark"></i>
          <h3>No hay turnos en esta categoría</h3>
          <p>Los turnos ${estado || 'asignados'} aparecerán aquí</p>
        </div>
      `;
      todosTurnos = [];
      return;
    }

    // NUEVO: Filtrar vencidos en frontend si el backend no lo hizo completamente
let items = data.items;
if (estado === 'vencido') {
  items = items.filter(t => {
    if (t.id_estado !== 2 && t.id_estado !== 5) return false;
    // Crear fecha sin conversión de zona horaria
    const [y, m, d] = t.fecha.split('-');
    const [h, min] = (t.hora || '00:00:00').split(':');
    const ts = new Date(y, m-1, d, h, min);
    const diffH = (Date.now() - ts.getTime()) / 36e5;
    return diffH >= 24;
  });
} else if (estado === 'confirmado') {
  // Confirmados: excluir vencidos
  items = items.filter(t => {
    if (t.id_estado !== 2 && t.id_estado !== 5) return true; // Otros estados pasan
    const [y, m, d] = t.fecha.split('-');
    const [h, min] = (t.hora || '00:00:00').split(':');
    const ts = new Date(y, m-1, d, h, min);
    const diffH = (Date.now() - ts.getTime()) / 36e5;
    return diffH < 24;
  });
}

    todosTurnos = items;
    renderizarTurnos(items);

  } catch (error) {
    console.error('Error al cargar turnos:', error);
    listadoTurnos.innerHTML = `
      <div class="empty-state">
        <i class="fa-solid fa-exclamation-triangle"></i>
        <h3>Error al cargar turnos</h3>
        <p>Por favor, intenta nuevamente</p>
      </div>
    `;
  }
}

  // ========== RENDERIZAR TURNOS ==========
  function renderizarTurnos(turnos) {
    if (turnos.length === 0) {
      listadoTurnos.innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-search"></i>
          <h3>No se encontraron resultados</h3>
          <p>Intenta con otros términos de búsqueda</p>
        </div>
      `;
      return;
    }

    listadoTurnos.innerHTML = `<div class="turnos-grid">${
      turnos.map(turno => {
        const tieneOrden = turno.tiene_orden_medica === 1 || turno.tiene_orden_medica === true;
        
        const badgeOrden = tieneOrden
          ? '<span class="badge badge-con-orden"><i class="fa-solid fa-file-medical"></i> Con Orden Médica</span>'
          : '<span class="badge badge-sin-orden"><i class="fa-solid fa-file-circle-xmark"></i> Sin Orden</span>';

        const btnOrden = tieneOrden
          ? `<button class="btn btn-primary btn-ver-orden" data-id="${turno.id_turno}">
               <i class="fa-solid fa-file-medical"></i> Ver Orden Médica
             </button>`
          : '';

        const btnAtendido = turno.id_estado === 3
  ? `<span class="badge badge-success">
       <i class="fa-solid fa-user-check"></i> Atendido
     </span>`
  : (tieneOrden
      ? `<button class="btn btn-success btn-marcar-atendido" data-id="${turno.id_turno}">
           <i class="fa-solid fa-user-check"></i> Marcar como Atendido
         </button>`
      : '');

        return `
          <div class="turno-card">
            <div class="turno-header">
              <div>
                <div class="turno-paciente">
                  <i class="fa-solid fa-user"></i> ${turno.paciente_nombre || 'Paciente'}
                </div>
                <div class="turno-fecha">
                  <i class="fa-solid fa-calendar"></i> ${formatFecha(turno.fecha)} a las ${formatHora(turno.hora)}
                </div>
              </div>
              <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
                ${turno.id_estado === 2 
  ? '<span class="badge badge-confirmado"><i class="fa-solid fa-check"></i> Confirmado</span>'
  : turno.id_estado === 3
    ? '<span class="badge" style="background:#dcfce7;color:#166534"><i class="fa-solid fa-user-check"></i> Atendido</span>'
    : turno.id_estado === 4
      ? '<span class="badge" style="background:#fee2e2;color:#991b1b"><i class="fa-solid fa-ban"></i> Cancelado</span>'
      : '<span class="badge badge-confirmado"><i class="fa-solid fa-clock"></i> ' + (turno.estado || 'Pendiente') + '</span>'
}
                ${badgeOrden}
              </div>
            </div>

            <div class="turno-info">
              <div class="info-item">
                <i class="fa-solid fa-flask"></i>
                <span><strong>Estudio:</strong> ${turno.estudio_nombre || 'No especificado'}</span>
              </div>
              <div class="info-item">
                <i class="fa-solid fa-id-card"></i>
                <span><strong>DNI:</strong> ${turno.paciente_dni || 'S/D'}</span>
              </div>
              ${turno.observaciones ? `
                <div class="info-item" style="grid-column: 1 / -1;">
                  <i class="fa-solid fa-comment"></i>
                  <span><strong>Observaciones:</strong> ${turno.observaciones}</span>
                </div>
              ` : ''}
            </div>

            <div class="turno-actions">
              ${btnOrden}
              ${btnAtendido}
            </div>
          </div>
        `;
      }).join('')}
    </div>`;

    // Event listeners para botones "Ver Orden"
    document.querySelectorAll('.btn-ver-orden').forEach(btn => {
      btn.addEventListener('click', () => {
        const idTurno = btn.dataset.id;
        verOrdenMedica(idTurno);
      });
    });

    // Event listeners para botones "Marcar como Atendido"
    document.querySelectorAll('.btn-marcar-atendido').forEach(btn => {
      btn.addEventListener('click', () => {
        const idTurno = btn.dataset.id;
        if (idTurno) {
          marcarAtendido(idTurno, btn);
        } else {
          mostrarToast('❌ ID de turno inválido', 'error');
        }
      });
    });
  }

  // ========== VER ORDEN MÉDICA ==========
  async function verOrdenMedica(idTurno) {
    modalBody.innerHTML = `
      <div class="loading">
        <i class="fa-solid fa-spinner"></i>
        <p>Cargando orden médica...</p>
      </div>
    `;
    modalOrden.style.display = 'block';

    try {
      const res = await fetch(`${API.ordenDetalle}?id_turno=${idTurno}`);
      const data = await res.json();

      if (!data.ok) {
        throw new Error(data.msg || 'Error al cargar la orden');
      }

      if (!data.tiene_orden) {
        modalBody.innerHTML = `
          <div class="empty-state">
            <i class="fa-solid fa-file-circle-xmark"></i>
            <h3>Sin Orden Médica</h3>
            <p>Este turno no tiene una orden médica vinculada</p>
          </div>
        `;
        return;
      }

      const estudios = data.orden.estudios_nombres 
        ? data.orden.estudios_nombres.split(',').map(e => e.trim()) 
        : [];

      modalBody.innerHTML = `
        <div class="orden-section">
          <h4><i class="fa-solid fa-user"></i> Datos del Paciente</h4>
          <p><strong>Nombre:</strong> ${data.paciente.nombre}</p>
          <p><strong>DNI:</strong> ${data.paciente.dni || 'S/D'}</p>
          <p><strong>Fecha de nacimiento:</strong> ${formatFecha(data.paciente.fecha_nacimiento)}</p>
          ${data.paciente.telefono ? `<p><strong>Teléfono:</strong> ${data.paciente.telefono}</p>` : ''}
        </div>

        <div class="orden-section">
          <h4><i class="fa-solid fa-user-doctor"></i> Médico Emisor</h4>
          <p><strong>${data.medico.nombre}</strong></p>
          <p><strong>Matrícula:</strong> ${data.medico.matricula || 'S/D'}</p>
          <p><strong>Fecha de emisión:</strong> ${formatFechaHora(data.orden.fecha_emision)}</p>
        </div>

        <div class="orden-section">
          <h4><i class="fa-solid fa-notes-medical"></i> Diagnóstico</h4>
          <p>${data.orden.diagnostico || 'No especificado'}</p>
        </div>

        <div class="orden-section">
          <h4><i class="fa-solid fa-flask"></i> Estudios</h4>
          <ul>
            ${estudios.map(e => `<li>${e}</li>`).join('')}
          </ul>
        </div>
      `;
    } catch (error) {
      console.error('Error al cargar orden médica:', error);
      modalBody.innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-exclamation-triangle"></i>
          <h3>Error al cargar orden médica</h3>
          <p>Por favor, intenta nuevamente</p>
        </div>
      `;
    }
  }

  cerrarModal.addEventListener('click', () => {
    modalOrden.style.display = 'none';
    modalBody.innerHTML = '';
  });

  // ========== MARCAR COMO ATENDIDO ==========
  async function marcarAtendido(idTurno, btn) {
    btn.disabled = true;
    btn.textContent = 'Procesando...';

    try {
      const res = await fetch(`${API.marcarAtendido}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id_turno: idTurno })
      });
      const data = await res.json();

      if (!data.ok) throw new Error(data.msg || 'No se pudo marcar como atendido');

      mostrarToast('✅ Turno marcado como atendido', 'success');
      btn.closest('.turno-actions').innerHTML = `
        <span class="badge badge-success">
          <i class="fa-solid fa-user-check"></i> Atendido
        </span>
      `;
    } catch (error) {
      console.error('Error al marcar turno:', error);
      mostrarToast('❌ ' + error.message, 'error');
      btn.disabled = false;
      btn.textContent = 'Marcar como Atendido';
    }
  }

  // Event listeners para tabs
tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    tabs.forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    estadoActivo = tab.dataset.estado;
    cargarTurnos(buscarTurno.value.trim(), estadoActivo);
  });
});

// Búsqueda
buscarTurno.addEventListener('input', e => {
  clearTimeout(timeoutBusqueda);
  const q = e.target.value.trim();
  timeoutBusqueda = setTimeout(() => {
    cargarTurnos(q, estadoActivo);
  }, 300);
});

  // ========== INICIALIZACIÓN ==========
  cargarTurnos();
})();


