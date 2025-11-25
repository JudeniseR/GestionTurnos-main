/**
 * ========================================
 * GESTIÓN DE ÓRDENES MÉDICAS - FRONTEND
 * ========================================
 * Ruta: /interfaces/Medico/ordenes_medicas.js
 */

(function() {
  'use strict';

  // ========== CONFIGURACIÓN ==========
  const API = {
    crear: '/interfaces/Medico/api/ordenes_crear.php',
    listar: 'api/ordenes_list.php',
    verificar: 'api/orden_verificar.php',
    buscarPacientes: 'api/pacientes_buscar.php',
    estudios: '../../Logica/Paciente/Gestion-Turnos/obtenerEstudios.php'
  };

  // ========== ELEMENTOS DOM ==========
  const btnNuevaOrden = document.getElementById('btnNuevaOrden');
  const modalNuevaOrden = document.getElementById('modalNuevaOrden');
  const modalDetalleOrden = document.getElementById('modalDetalleOrden');
  const cerrarModal = document.getElementById('cerrarModal');
  const cerrarModalDetalle = document.getElementById('cerrarModalDetalle');
  const btnCancelar = document.getElementById('btnCancelar');
  const formNuevaOrden = document.getElementById('formNuevaOrden');
  const loadingEmision = document.getElementById('loadingEmision');

  // Tabs
  const tabs = document.querySelectorAll('.tab');
  const tabActivas = document.getElementById('tabActivas');
  const tabHistorial = document.getElementById('tabHistorial');

  // Listados
  const listadoActivas = document.getElementById('listadoActivas');
  const listadoHistorial = document.getElementById('listadoHistorial');

  // Formulario
  const buscarPaciente = document.getElementById('buscarPaciente');
  const resultadosPacientes = document.getElementById('resultadosPacientes');
  const pacienteSeleccionado = document.getElementById('pacienteSeleccionado');
  const idPacienteInput = document.getElementById('id_paciente');
  const selectEstudio = document.getElementById('selectEstudio');
  const estudiosSeleccionados = document.getElementById('estudiosSeleccionados');
  const estudiosIndicadosInput = document.getElementById('estudios_indicados');

  // Búsquedas
  const buscarActivas = document.getElementById('buscarActivas');
  const buscarHistorial = document.getElementById('buscarHistorial');

  // ========== ESTADO ==========
  let pacienteActual = null;
  let estudiosArray = [];
  let timeoutBusqueda = null;
  const idAfiliadoInput = document.getElementById('id_afiliado');
const idTitularInput = document.getElementById('id_titular');

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
    // Implementación simple de toast
    const toast = document.createElement('div');
    toast.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      padding: 16px 20px;
      background: ${tipo === 'success' ? '#16a34a' : tipo === 'error' ? '#dc2626' : '#1e88e5'};
      color: white;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,.2);
      z-index: 10000;
      font-weight: 600;
      max-width: 400px;
    `;
    toast.textContent = mensaje;
    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // ========== GESTIÓN DE TABS ==========
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      const tabName = tab.dataset.tab;
      if (tabName === 'activas') {
        tabActivas.style.display = 'block';
        tabHistorial.style.display = 'none';
        cargarOrdenes('activa');
      } else {
        tabActivas.style.display = 'none';
        tabHistorial.style.display = 'block';
        cargarOrdenes('historial');
      }
    });
  });

  // ========== MODAL ==========
  if (btnNuevaOrden) {
    btnNuevaOrden.addEventListener('click', abrirModalNuevaOrden);
  }

  if (cerrarModal) {
    cerrarModal.addEventListener('click', cerrarModalNuevaOrden);
  }

  if (cerrarModalDetalle) {
    cerrarModalDetalle.addEventListener('click', () => {
      modalDetalleOrden.style.display = 'none';
    });
  }

  if (btnCancelar) {
    btnCancelar.addEventListener('click', cerrarModalNuevaOrden);
  }

  // Cerrar modal al hacer clic fuera
  window.addEventListener('click', (e) => {
    if (e.target === modalNuevaOrden) cerrarModalNuevaOrden();
    if (e.target === modalDetalleOrden) modalDetalleOrden.style.display = 'none';
  });

  function abrirModalNuevaOrden() {
    modalNuevaOrden.style.display = 'flex';
    formNuevaOrden.style.display = 'block';
    loadingEmision.style.display = 'none';
    formNuevaOrden.reset();
    pacienteActual = null;
    estudiosArray = [];
    idPacienteInput.value = '';
    pacienteSeleccionado.innerHTML = '';
    estudiosSeleccionados.innerHTML = '';
    cargarEstudios();
  }

  function cerrarModalNuevaOrden() {
    modalNuevaOrden.style.display = 'none';
    formNuevaOrden.reset();
  }

  
// ========== BÚSQUEDA DE PACIENTES ==========
if (buscarPaciente) {
  buscarPaciente.addEventListener('input', (e) => {
    const query = e.target.value.trim();

    clearTimeout(timeoutBusqueda);

    if (query.length < 2) {
      resultadosPacientes.innerHTML = '';
      return;
    }

    resultadosPacientes.innerHTML = '<div style="padding:8px;color:#6b7280">Buscando...</div>';

    timeoutBusqueda = setTimeout(async () => {
      try {
        const res = await fetch(`${API.buscarPacientes}?q=${encodeURIComponent(query)}`);
        const pacientes = await res.json();

        if (!pacientes || pacientes.length === 0) {
          resultadosPacientes.innerHTML = '<div style="padding:8px;color:#6b7280">No se encontraron pacientes o beneficiarios</div>';
          return;
        }

        resultadosPacientes.innerHTML = `
          <div style="position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.1);max-height:200px;overflow-y:auto;z-index:100">
            ${pacientes.map(p => {
              const isBeneficiario = p.tipo !== 'titular';
              const tipo = isBeneficiario ? 'beneficiario' : 'paciente';
              const id = p.id;
              let nombre, apellido;
              if (isBeneficiario) {
                [apellido, nombre] = p.nombre_completo.split(', ');
              } else {
                nombre = p.nombre_completo;
                apellido = '';
              }
              const dni = p.dni;
              const idTitular = p.id_titular || '';
              return `
                <div class="paciente-resultado" data-tipo="${tipo}" data-id="${id}" data-nombre="${nombre}" data-apellido="${apellido}" data-dni="${dni}" data-id-titular="${idTitular}" style="padding:10px;cursor:pointer;border-bottom:1px solid #f1f5f9">
                  <div style="font-weight:600">${p.nombre_completo}</div>
                  <div style="font-size:12px;color:#6b7280">DNI: ${dni || 'S/D'}</div>
                </div>
              `;
            }).join('')}
          </div>
        `;

        // Event listeners para cada resultado
        document.querySelectorAll('.paciente-resultado').forEach(el => {
          el.addEventListener('click', () => {
            const tipo = el.dataset.tipo;
            const pacienteData = {
              id: el.dataset.id,
              nombre: el.dataset.nombre,
              apellido: el.dataset.apellido,
              dni: el.dataset.dni,
              id_titular: el.dataset.idTitular
            };
            if (tipo === 'paciente') {
              seleccionarPaciente({
                id: pacienteData.id,
                nombre: pacienteData.nombre,
                dni: pacienteData.dni
              });
            } else {
              seleccionarAfiliado({
                id: pacienteData.id,
                nombre: pacienteData.nombre,
                apellido: pacienteData.apellido,
                numero_documento: pacienteData.dni,
                id_titular: pacienteData.id_titular
              });
            }
          });
        });

      } catch (error) {
        console.error('Error al buscar pacientes:', error);
        resultadosPacientes.innerHTML = '<div style="padding:8px;color:#dc2626">Error al buscar</div>';
      }
    }, 300);
  });
}


  function seleccionarPaciente(paciente) {
    pacienteActual = paciente;
    idPacienteInput.value = paciente.id;
    idAfiliadoInput.value = '';  // Limpiar el campo de afiliado si se selecciona un paciente normal
    buscarPaciente.value = '';
    resultadosPacientes.innerHTML = '';

    pacienteSeleccionado.innerHTML = `
      <div style="background:#e0f2fe;border:1px solid #bae6fd;border-radius:8px;padding:10px;display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="font-weight:700;color:#075985">${paciente.nombre}</div>
          <div style="font-size:12px;color:#0369a1">DNI: ${paciente.dni || 'S/D'}</div>
        </div>
        <button type="button" style="border:none;background:none;color:#075985;cursor:pointer;font-size:18px" onclick="this.parentElement.parentElement.innerHTML='';document.getElementById('id_paciente').value=''">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    `;
}


  function seleccionarAfiliado(afiliado) {
    pacienteActual = afiliado;
    idPacienteInput.value = afiliado.id;
    idAfiliadoInput.value = afiliado.id;
    idTitularInput.value = afiliado.id_titular || '';  // 👈 AGREGAR ESTA LÍNEA
    buscarPaciente.value = '';
    resultadosPacientes.innerHTML = '';

    pacienteSeleccionado.innerHTML = `
      <div style="background:#fef3c7;border:1px solid #fde047;border-radius:8px;padding:10px;display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="font-weight:700;color:#92400e">${afiliado.nombre} ${afiliado.apellido}</div>
          <div style="font-size:12px;color:#b45309">
            DNI: ${afiliado.numero_documento || 'S/D'} | <strong>Beneficiario: ${afiliado.tipo || 'menor'}</strong>
          </div>
        </div>
        <button type="button" style="border:none;background:none;color:#92400e;cursor:pointer;font-size:18px" 
                onclick="this.parentElement.parentElement.innerHTML='';document.getElementById('id_paciente').value='';document.getElementById('id_afiliado').value='';document.getElementById('id_titular').value=''">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    `;
}

  // ========== CARGAR ESTUDIOS ==========
  async function cargarEstudios() {
    try {
      const res = await fetch(API.estudios, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
      });
      const estudios = await res.json();

      if (!estudios || estudios.length === 0) {
        selectEstudio.innerHTML = '<option value="">No hay estudios disponibles</option>';
        return;
      }

      selectEstudio.innerHTML = '<option value="">Seleccionar estudio...</option>';
      estudios.forEach(e => {
        const option = document.createElement('option');
        option.value = e.id_estudio;
        option.textContent = e.nombre;
        option.dataset.nombre = e.nombre;
        selectEstudio.appendChild(option);
      });

    } catch (error) {
      console.error('Error al cargar estudios:', error);
      selectEstudio.innerHTML = '<option value="">Error al cargar estudios</option>';
    }
  }

  // Agregar estudio seleccionado
  if (selectEstudio) {
    selectEstudio.addEventListener('change', (e) => {
      const id = e.target.value;
      const nombre = e.target.options[e.target.selectedIndex]?.dataset?.nombre;

      if (!id || !nombre) return;

      // Evitar duplicados
      if (estudiosArray.find(est => est.id === id)) {
        mostrarToast('Este estudio ya fue agregado', 'error');
        selectEstudio.value = '';
        return;
      }

      estudiosArray.push({ id, nombre });
      actualizarEstudiosSeleccionados();
      selectEstudio.value = '';
    });
  }

  function actualizarEstudiosSeleccionados() {
    if (estudiosArray.length === 0) {
      estudiosSeleccionados.innerHTML = '<div style="color:#6b7280;font-size:13px">No hay estudios seleccionados</div>';
      estudiosIndicadosInput.value = '';
      return;
    }

    estudiosSeleccionados.innerHTML = estudiosArray.map((est, idx) => `
      <div class="selected-item">
        <i class="fa-solid fa-flask"></i>
        ${est.nombre}
        <button type="button" onclick="eliminarEstudio(${idx})">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    `).join('');

    // Guardar en input hidden como JSON
    estudiosIndicadosInput.value = JSON.stringify(estudiosArray);
  }

  // Función global para eliminar estudio
  window.eliminarEstudio = function(index) {
    estudiosArray.splice(index, 1);
    actualizarEstudiosSeleccionados();
  };

// ========== ENVIAR FORMULARIO ==========
if (formNuevaOrden) {
  formNuevaOrden.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Validaciones
    if (!idPacienteInput.value && !idAfiliadoInput.value) {
        mostrarToast('Debe seleccionar un paciente o beneficiario', 'error');
        return;
    }

    if (estudiosArray.length === 0) {
        mostrarToast('Debe indicar al menos un estudio', 'error');
        return;
    }

    // Mostrar loading
    formNuevaOrden.style.display = 'none';
    loadingEmision.style.display = 'block';

    const formData = new FormData(formNuevaOrden);

    try {
        const res = await fetch(API.crear, {
            method: 'POST',
            body: formData
        });

        // Revisar si la respuesta es exitosa
        const text = await res.text();
        console.log('Respuesta cruda:', text);

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${text}`);
        }

        // Intentar parsear la respuesta a JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            throw new Error('La respuesta no es un JSON válido');
        }

        if (data.ok) {
            mostrarToast('✅ Orden médica firmada y emitida exitosamente', 'success');
            cerrarModalNuevaOrden();
            cargarOrdenes('activa');
        } else {
            throw new Error(data.msg || 'Error al emitir la orden');
        }
    } catch (error) {
        console.error('Error completo:', error);
        mostrarToast('❌ Error: ' + error.message, 'error');
        formNuevaOrden.style.display = 'block';
        loadingEmision.style.display = 'none';
    }
});
}



  // ========== CARGAR ÓRDENES ==========
  async function cargarOrdenes(tipo = 'activa', busqueda = '') {
    const container = tipo === 'activa' ? listadoActivas : listadoHistorial;
    container.innerHTML = '<div style="text-align:center;padding:20px;color:#6b7280"><i class="fa-solid fa-spinner fa-spin" style="font-size:32px"></i><p>Cargando órdenes...</p></div>';

    try {
      const params = new URLSearchParams({ estado: tipo });
      if (busqueda) params.append('q', busqueda);

      const res = await fetch(`${API.listar}?${params}`);
      const data = await res.json();

      if (!data.ok || !data.items || data.items.length === 0) {
        container.innerHTML = `
          <div class="empty-state">
            <i class="fa-solid fa-folder-open"></i>
            <h3>No hay órdenes ${tipo === 'activa' ? 'activas' : 'en el historial'}</h3>
            <p>Las órdenes que emitas aparecerán aquí</p>
          </div>
        `;
        return;
      }

      container.innerHTML = data.items.map(orden => renderOrdenItem(orden)).join('');

      // Event listeners para botones de acción
      container.querySelectorAll('.btn-ver-detalle').forEach(btn => {
        btn.addEventListener('click', () => {
          const ordenId = btn.dataset.id;
          const orden = data.items.find(o => o.id_orden == ordenId);
          if (orden) mostrarDetalleOrden(orden);
        });
      });

    } catch (error) {
      console.error('Error al cargar órdenes:', error);
      container.innerHTML = '<div style="text-align:center;padding:20px;color:#dc2626">Error al cargar las órdenes</div>';
    }
  }

  function renderOrdenItem(orden) {
    const estadoBadge = orden.estado === 'activa' 
      ? '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Activa</span>'
      : orden.estado === 'utilizada'
      ? '<span class="badge badge-warning"><i class="fa-solid fa-check"></i> Utilizada</span>'
      : '<span class="badge badge-danger"><i class="fa-solid fa-ban"></i> Cancelada</span>';

    const estudios = orden.estudios_nombres ? orden.estudios_nombres.split(',').map(e => e.trim()).join(', ') : 'Sin estudios';

    return `
      <div class="orden-item">
        <div class="orden-header">
          <div>
            <div class="orden-paciente">
              <i class="fa-solid fa-user"></i> ${orden.paciente_nombre || 'Paciente'}
            </div>
            <div class="orden-fecha">
              <i class="fa-solid fa-calendar"></i> ${formatFecha(orden.fecha_emision)}
            </div>
          </div>
          ${estadoBadge}
        </div>
        <div class="orden-content">
          <div style="margin-bottom:8px">
            <strong style="color:#0f172a">Diagnóstico:</strong> ${orden.diagnostico || '-'}
          </div>
          <div>
            <strong style="color:#0f172a">Estudios:</strong> ${estudios}
          </div>
        </div>
        <div class="orden-footer">
          <div style="font-size:12px;color:#64748b">
            <i class="fa-solid fa-fingerprint"></i> Orden #${orden.id_orden}
          </div>
          <button class="btn btn-outline btn-ver-detalle" data-id="${orden.id_orden}" style="padding:8px 12px;font-size:13px">
            <i class="fa-solid fa-eye"></i> Ver detalle
          </button>
        </div>
      </div>
    `;
  }

  // ========== DETALLE DE ORDEN ==========
  async function mostrarDetalleOrden(orden) {
    const body = document.getElementById('detalleOrdenBody');
    
    body.innerHTML = `
      <div style="display:grid;gap:16px">
        <div>
          <label style="font-size:14px;color:#6b7280;font-weight:700;display:block;margin-bottom:4px">Paciente</label>
          <div style="font-size:16px;font-weight:700">${orden.paciente_nombre || 'N/A'}</div>
          <div style="font-size:13px;color:#6b7280">DNI: ${orden.paciente_dni || 'S/D'}</div>
        </div>

        <div>
          <label style="font-size:14px;color:#6b7280;font-weight:700;display:block;margin-bottom:4px">Fecha de emisión</label>
          <div>${formatFecha(orden.fecha_emision)}</div>
        </div>

        <div>
          <label style="font-size:14px;color:#6b7280;font-weight:700;display:block;margin-bottom:4px">Diagnóstico</label>
          <div style="background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e5e7eb">
            ${orden.diagnostico || '-'}
          </div>
        </div>

        <div>
          <label style="font-size:14px;color:#6b7280;font-weight:700;display:block;margin-bottom:4px">Estudios indicados</label>
          <div style="background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e5e7eb">
            ${orden.estudios_nombres ? orden.estudios_nombres.split(',').map(e => `• ${e.trim()}`).join('<br>') : 'Sin estudios'}
          </div>
        </div>

        ${orden.observaciones ? `
          <div>
            <label style="font-size:14px;color:#6b7280;font-weight:700;display:block;margin-bottom:4px">Observaciones</label>
            <div style="background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e5e7eb">
              ${orden.observaciones}
            </div>
          </div>
        ` : ''}

        <div>
          <label style="font-size:14px;color:#6b7280;font-weight:700;display:block;margin-bottom:4px">
            <i class="fa-solid fa-shield-halved"></i> Firma Digital (primeros 100 caracteres)
          </label>
          <div class="firma-preview">
            ${orden.firma_digital ? orden.firma_digital.substring(0, 100) + '...' : 'Sin firma'}
          </div>
        </div>

        <div>
          <label style="font-size:14px;color:#6b7280;font-weight:700;display:block;margin-bottom:4px">
            <i class="fa-solid fa-hashtag"></i> Hash del contenido
          </label>
          <div class="firma-preview" style="font-size:10px">
            ${orden.contenido_hash || 'Sin hash'}
          </div>
        </div>

        <div>
          <label style="font-size:14px;color:#6b7280;font-weight:700;display:block;margin-bottom:4px">Estado</label>
          <div>${orden.estado === 'activa' ? '<span class="badge badge-success">Activa</span>' : orden.estado === 'utilizada' ? '<span class="badge badge-warning">Utilizada</span>' : '<span class="badge badge-danger">Cancelada</span>'}</div>
        </div>
      </div>
    `;

    modalDetalleOrden.style.display = 'flex';
  }

  // ========== BÚSQUEDAS EN LISTADOS ==========
  if (buscarActivas) {
    let timeout;
    buscarActivas.addEventListener('input', (e) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => {
        cargarOrdenes('activa', e.target.value.trim());
      }, 300);
    });
  }

  if (buscarHistorial) {
    let timeout;
    buscarHistorial.addEventListener('input', (e) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => {
        cargarOrdenes('historial', e.target.value.trim());
      }, 300);
    });
  }

  // ========== INICIALIZACIÓN ==========
  cargarOrdenes('activa');

})();