/**
 * ========================================
 * MIS ÓRDENES MÉDICAS - FRONTEND
 * ========================================
 * Ruta: /interfaces/Paciente/mis_ordenes.js
 */
(function() {
  'use strict';

  // ========== CONFIGURACIÓN ==========
  const API = {
    listar: 'api/paciente_ordenes_list.php',
    descargarPDF: 'api/orden_descargar_pdf.php'
  };

  // ========== ELEMENTOS DOM ==========
  const listadoOrdenes = document.getElementById('listadoOrdenes');
  const buscarOrden = document.getElementById('buscarOrden');
  const modalDetalle = document.getElementById('modalDetalle');
  const cerrarModal = document.getElementById('cerrarModal');
  const modalBody = document.getElementById('modalBody');

  let todasLasOrdenes = [];
  let timeoutBusqueda = null;

  // ========== FUNCIONES AUXILIARES ==========
  function formatFecha(fecha) {
    if (!fecha) return '-';
    const d = new Date(fecha);
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
    const colores = { success: '#10b981', error: '#ef4444', info: '#3b82f6' };
    toast.style.cssText = `
      position: fixed; bottom: 20px; right: 20px;
      padding: 16px 24px; background: ${colores[tipo] || colores.info};
      color: white; border-radius: 10px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 10000;
      font-weight: 600; max-width: 400px; animation: slideIn 0.3s;
    `;
    toast.textContent = mensaje;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.animation = 'slideOut 0.3s';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }

  // ========== CARGAR ÓRDENES ==========
  async function cargarOrdenes(busqueda = '') {
    listadoOrdenes.innerHTML = `
      <div class="loading">
        <i class="fa-solid fa-spinner"></i>
        <p>Cargando tus órdenes médicas...</p>
      </div>
    `;

    try {
      const params = new URLSearchParams();
      if (busqueda) params.append('q', busqueda);

      const res = await fetch(`${API.listar}?${params}`);
      const data = await res.json();

      if (!data.ok || !data.items || data.items.length === 0) {
        listadoOrdenes.innerHTML = `
          <div class="empty-state">
            <i class="fa-solid fa-folder-open"></i>
            <h3>No tenés órdenes médicas</h3>
            <p>Cuando tu médico emita una orden, aparecerá aquí</p>
          </div>
        `;
        todasLasOrdenes = [];
        return;
      }

      todasLasOrdenes = data.items;
      renderizarOrdenes(data.items);

    } catch (error) {
      console.error('Error al cargar órdenes:', error);
      listadoOrdenes.innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-exclamation-triangle"></i>
          <h3>Error al cargar las órdenes</h3>
          <p>Por favor, intenta nuevamente</p>
        </div>
      `;
    }
  }

  // ========== RENDERIZAR ÓRDENES ==========
  function renderizarOrdenes(ordenes) {
    if (ordenes.length === 0) {
      listadoOrdenes.innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-search"></i>
          <h3>No se encontraron resultados</h3>
          <p>Intenta con otros términos de búsqueda</p>
        </div>
      `;
      return;
    }

    listadoOrdenes.innerHTML = ordenes.map(orden => {
      const estudios = orden.estudios_nombres 
        ? orden.estudios_nombres.split(',').map(e => e.trim()) 
        : [];

      // Badge estado
      const badgeEstado = orden.estado === 'activa'
        ? '<span class="badge badge-activa"><i class="fa-solid fa-circle-check"></i> Activa</span>'
        : orden.estado === 'utilizada'
        ? '<span class="badge badge-utilizada"><i class="fa-solid fa-check"></i> Utilizada</span>'
        : '<span class="badge badge-utilizada"><i class="fa-solid fa-ban"></i> Cancelada</span>';

      // Badge tipo paciente
      const badgeTipo = orden.paciente_tipo === 'titular'
        ? '<span class="badge badge-titular"><i class="fa-solid fa-user"></i> Titular</span>'
        : '<span class="badge badge-hijo"><i class="fa-solid fa-child"></i> Hijo menor</span>';

      return `
        <div class="orden-card">
          <div class="orden-header">
            <div class="orden-info">
              <h3><i class="fa-solid fa-user-doctor"></i> ${orden.medico_nombre || 'Médico'}</h3>
              <div class="orden-meta">
                <span><i class="fa-solid fa-calendar"></i> ${formatFecha(orden.fecha_emision)}</span>
                <span><i class="fa-solid fa-id-card"></i> Matrícula: ${orden.medico_matricula || 'S/D'}</span>
              </div>
            </div>
            ${badgeEstado} ${badgeTipo}
          </div>

          <div class="orden-content">
            <div class="orden-section">
              <label><i class="fa-solid fa-notes-medical"></i> Diagnóstico</label>
              <div class="content">${orden.diagnostico || '-'}</div>
            </div>

            <div class="orden-section">
              <label><i class="fa-solid fa-flask"></i> Estudios Indicados</label>
              <div class="estudios-list">
                ${estudios.map(e => `<span class="estudio-tag">${e}</span>`).join('')}
              </div>
            </div>

            ${orden.observaciones ? `
              <div class="orden-section">
                <label><i class="fa-solid fa-comment-medical"></i> Observaciones</label>
                <div class="content">${orden.observaciones}</div>
              </div>
            ` : ''}
          </div>

          <div class="orden-actions">
            <button class="btn btn-primary" onclick="verDetalle(${orden.id_orden})">
              <i class="fa-solid fa-eye"></i> Ver Detalle Completo
            </button>

            <a href="${API.descargarPDF}?id_orden=${orden.id_orden}" 
               class="btn btn-outline" 
               target="_blank">
              <i class="fa-solid fa-download"></i> Descargar PDF
            </a>
          </div>
        </div>
      `;
    }).join('');
  }

  // ========== VER DETALLE ==========
  window.verDetalle = function(idOrden) {
    const orden = todasLasOrdenes.find(o => o.id_orden == idOrden);
    if (!orden) {
      mostrarToast('Orden no encontrada', 'error');
      return;
    }

    const estudios = orden.estudios_nombres 
      ? orden.estudios_nombres.split(',').map(e => e.trim()) 
      : [];

    const badgeTipoDetalle = orden.paciente_tipo === 'titular'
      ? '<span class="badge badge-titular"><i class="fa-solid fa-user"></i> Titular</span>'
      : '<span class="badge badge-hijo"><i class="fa-solid fa-child"></i> Hijo menor</span>';

    modalBody.innerHTML = `
      <div style="display:grid;gap:20px">
        <div class="orden-section">
          <label><i class="fa-solid fa-user-doctor"></i> Médico</label>
          <div class="content">
            <strong>${orden.medico_nombre || 'N/A'}</strong><br>
            Matrícula: ${orden.medico_matricula || 'S/D'}
          </div>
        </div>

        <div class="orden-section">
          <label><i class="fa-solid fa-calendar"></i> Fecha de Emisión</label>
          <div class="content">${formatFecha(orden.fecha_emision)}</div>
        </div>

        <div class="orden-section">
          <label><i class="fa-solid fa-id-card"></i> Paciente</label>
          <div class="content">
            ${orden.paciente_nombre || 'N/A'} - ${orden.paciente_dni || 'S/D'} ${badgeTipoDetalle}
          </div>
        </div>

        <div class="orden-section">
          <label><i class="fa-solid fa-notes-medical"></i> Diagnóstico</label>
          <div class="content">${orden.diagnostico || '-'}</div>
        </div>

        <div class="orden-section">
          <label><i class="fa-solid fa-flask"></i> Estudios Indicados</label>
          <div class="estudios-list">
            ${estudios.map(e => `<span class="estudio-tag">${e}</span>`).join('')}
          </div>
        </div>

        ${orden.observaciones ? `
          <div class="orden-section">
            <label><i class="fa-solid fa-comment-medical"></i> Observaciones</label>
            <div class="content">${orden.observaciones}</div>
          </div>
        ` : ''}

        <div class="orden-section">
          <label><i class="fa-solid fa-shield-halved"></i> Firma Digital</label>
          <div class="sello-firma">
            <div class="sello-top">${orden.medico_nombre || 'Dr./Dra. N/A'}</div>
            ${orden.medico_especialidad ? `<div class="sello-mid">${orden.medico_especialidad}</div>` : ''}
            <div class="sello-bottom">Matrícula: ${orden.medico_matricula || 'S/D'}</div>
            <div class="sello-fecha">Firmado digitalmente el ${formatFecha(orden.fecha_emision)}</div>
          </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:center;padding-top:10px"> 
          <a href="${API.descargarPDF}?id_orden=${orden.id_orden}" 
             class="btn btn-outline" 
             target="_blank">
            <i class="fa-solid fa-download"></i> Descargar PDF
          </a>
        </div>
      </div>
    `;

    modalDetalle.style.display = 'block';
  };

  // ========== BÚSQUEDA ==========
  if (buscarOrden) {
    buscarOrden.addEventListener('input', (e) => {
      clearTimeout(timeoutBusqueda);
      timeoutBusqueda = setTimeout(() => {
        cargarOrdenes(e.target.value.trim());
      }, 300);
    });
  }

  // ========== MODAL ==========
  if (cerrarModal) {
    cerrarModal.addEventListener('click', () => {
      modalDetalle.style.display = 'none';
    });
  }

  window.addEventListener('click', (e) => {
    if (e.target === modalDetalle) {
      modalDetalle.style.display = 'none';
    }
  });

  // ========== INICIALIZACIÓN ==========
  cargarOrdenes();

})();
