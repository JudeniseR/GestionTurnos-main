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
    // verificar: 'api/paciente_orden_verificar.php',
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

      const badgeEstado = orden.estado === 'activa'
        ? '<span class="badge badge-activa"><i class="fa-solid fa-circle-check"></i> Activa</span>'
        : orden.estado === 'utilizada'
        ? '<span class="badge badge-utilizada"><i class="fa-solid fa-check"></i> Utilizada</span>'
        : '<span class="badge badge-utilizada"><i class="fa-solid fa-ban"></i> Cancelada</span>';

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
            ${badgeEstado}
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
          <label><i class="fa-solid fa-shield-halved"></i> Firma Digital (preview)</label>
          <div class="firma-preview">
            ${orden.firma_digital ? orden.firma_digital.substring(0, 150) + '...' : 'Sin firma'}
          </div>
        </div>

        <div class="orden-section">
          <label><i class="fa-solid fa-hashtag"></i> Hash del Contenido</label>
          <div class="firma-preview" style="font-size:10px">
            ${orden.contenido_hash || 'Sin hash'}
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

  /*
  // ========== VERIFICAR FIRMA ==========
  window.verificarFirma = async function(idOrden) {
    try {
      mostrarToast('Verificando firma digital...', 'info');

      const res = await fetch(`${API.verificar}?id_orden=${idOrden}`);
      const data = await res.json();

      if (!data.ok) {
        throw new Error(data.msg || 'Error al verificar la firma');
      }

      // Mostrar resultado en modal
      modalBody.innerHTML = `
        <div class="verificacion-result ${data.valida ? 'valida' : 'invalida'}">
          <i class="fa-solid ${data.valida ? 'fa-circle-check' : 'fa-circle-xmark'}"></i>
          <div>
            <h4 style="margin:0 0 8px 0">${data.msg}</h4>
            ${data.verificacion ? `
              <div style="font-size:14px;margin-top:10px">
                <strong>Verificación:</strong><br>
                • Integridad: ${data.verificacion.integridad ? '✅ Válida' : '❌ Comprometida'}<br>
                • Autenticidad: ${data.verificacion.autenticidad ? '✅ Verificada' : '❌ Inválida'}
              </div>
            ` : ''}
            ${data.explicacion ? `
              <div style="margin-top:15px;font-size:13px;padding:10px;background:rgba(255,255,255,0.3);border-radius:6px">
                <strong>Detalles:</strong><br>
                ${data.explicacion.integridad ? `• ${data.explicacion.integridad}<br>` : ''}
                ${data.explicacion.autenticidad ? `• ${data.explicacion.autenticidad}<br>` : ''}
                ${data.explicacion.no_repudio ? `• ${data.explicacion.no_repudio}` : ''}
              </div>
            ` : ''}
          </div>
        </div>

        ${data.detalles ? `
          <div class="orden-section">
            <label>Información del Médico</label>
            <div class="content">
              <strong>${data.detalles.medico || 'N/A'}</strong><br>
              ${data.detalles.matricula ? `Matrícula: ${data.detalles.matricula}<br>` : ''}
              Fecha de emisión: ${formatFecha(data.detalles.fecha_emision)}
            </div>
          </div>
        ` : ''}

        <div style="text-align:center;margin-top:20px">
          <button class="btn btn-outline" onclick="document.getElementById('modalDetalle').style.display='none'">
            Cerrar
          </button>
        </div>
      `;

      modalDetalle.style.display = 'block';

      if (data.valida) {
        mostrarToast('✅ Firma digital verificada correctamente', 'success');
      } else {
        mostrarToast('⚠️ La firma no pudo ser verificada', 'error');
      }

    } catch (error) {
      console.error('Error:', error);
      mostrarToast('❌ ' + error.message, 'error');
    }
  };
  */

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