<?php 
// ===== Seguridad / Sesión =====
$rol_requerido = 2; // Médico
require_once('../../Logica/General/verificarSesion.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$id_medico = $_SESSION['id_medico'] ?? null;
$nombre    = $_SESSION['nombre']   ?? '';
$apellido  = $_SESSION['apellido'] ?? '';
$displayRight = trim(mb_strtoupper($apellido) . ', ' . mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestionar Agenda</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<style>
  :root{
    --primary:#1e88e5; --primary-600:#1565c0; --text:#0f172a; --muted:#6b7280;
    --green:#16a34a; --red:#dc2626; --gray:#9ca3af; --card:#fff; --shadow:0 10px 25px rgba(0,0,0,.08);
    --bg:#f5f7fb; --danger:#ef4444; --danger-600:#dc2626;
    --radius:14px;
  }
  *{box-sizing:border-box}
  body{margin:0; font-family:Arial, Helvetica, sans-serif; background:var(--bg); color:var(--text)}

  /* NAV */
  .topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #e5e7eb}
  .navbar{max-width:1280px;margin:0 auto;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;gap:12px}
  .nav-left{display:flex;align-items:center;gap:24px}
  .brand{color:var(--primary);font-weight:800;text-decoration:none;display:flex;gap:8px;align-items:center}
  .nav-link{color:var(--primary);text-decoration:none;font-weight:700}
  .nav-link:hover{text-decoration:underline}
  .nav-right{display:flex;align-items:center;gap:12px}
  .logout{color:var(--primary);text-decoration:none;font-weight:700}
  .logout:hover{text-decoration:underline}
  .user{display:flex;align-items:center;gap:10px}
  .user-name{font-weight:800;white-space:nowrap}
  .user-avatar{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#e8f1fb;color:#1e88e5;border:1px solid #c7ddfc}

  /* LAYOUT */
  .wrap{max-width:1200px;margin:22px auto;padding:0 16px 40px}
  .page-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 12px}
  .page-title{font-size:26px;font-weight:800;margin:0 auto;text-align:center;flex:1;color:#0f172a}

  .grid{display:grid;grid-template-columns:1.15fr 1fr;gap:18px}
  @media(max-width:980px){ .grid{grid-template-columns:1fr} }
  .card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:14px 14px 18px}

  /* BUTTONS */
  .btn{
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    height:38px; padding:0 14px; border-radius:10px; font-weight:700; line-height:1;
    border:1px solid transparent; cursor:pointer; user-select:none;
    transition:background .15s ease, border-color .15s ease, color .15s ease, transform .05s ease;
  }
  .btn:active{ transform:translateY(1px); }
  .btn i{font-size:14px}
  .btn-sm{height:34px; padding:0 12px; font-size:14px}
  .btn-icon{width:36px;height:36px;padding:0;border-radius:50%}
  .btn-primary{background:var(--primary); color:#fff; border-color:var(--primary)}
  .btn-primary:hover{background:var(--primary-600); border-color:var(--primary-600)}
  .btn-outline{background:#fff; color:var(--primary); border-color:rgba(30,136,229,.35)}
  .btn-outline:hover{background:#f0f7ff; border-color:var(--primary)}
  .btn-ghost{background:#fff; color:var(--text); border-color:#e5e7eb}
  .btn-ghost:hover{background:#f8fafc}
  .btn-danger{background:var(--danger); color:#fff; border-color:var(--danger)}
  .btn-danger:hover{background:var(--danger-600); border-color:var(--danger-600)}
  .btn-danger-outline{background:#fff; color:var(--danger); border-color:rgba(239,68,68,.45)}
  .btn-danger-outline:hover{background:#fff5f5; border-color:var(--danger)}

  /* CALENDARIO */
  .cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
  .month{font-weight:800}
  .cal-nav{display:flex;gap:8px}
  .dow{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin:6px 0 6px}
  .dow span{font-size:12px;color:var(--muted);text-align:center}
  .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;min-height:238px}
  .day{
    background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;height:44px;
    display:flex;align-items:center;justify-content:center;cursor:pointer;user-select:none;transition:.15s;
  }
  .day:hover{transform:translateY(-1px); border-color:#d1d5db}
  .day.pad{background:transparent;border:none;cursor:default}
  /* Días pasados - estilo tenue */
  .day.disabled {
    background: #e4e4e4ff !important;  
    border-color: #f3f4f6 !important;
    color: #000000ff !important;
    opacity: 0.5;
    cursor: not-allowed !important;
  }
  .day.free{background:#4CAF50;border-color:#45a049;color:#fff;font-weight:700}        /* VERDE - con franjas */
  .day.busy{background:#F44336;border-color:#e53935;color:#fff;font-weight:700}        /* ROJO - bloqueado */
  .day.none{background:#9E9E9E;border-color:#757575;color:#fff;font-weight:700;opacity:0.7}  /* GRIS - sin agenda */
  .day.holiday{background:#4A90E2;border-color:#1976D2;color:#fff;font-weight:700}     /* AZUL - feriado (NUEVA) */
  .day.selected{outline:2px solid --primary}
  .legend{margin-top:10px;display:flex;gap:16px;color:var(--muted);font-size:13px;align-items:center;flex-wrap:wrap}
  .dot{width:12px;height:12px;border-radius:3px;display:inline-block}
  .dot-green{background:var(--green)} .dot-red{background:var(--red)} .dot-gray{background:var(--gray)}

  /* PANEL DERECHO */
  .panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
  .panel-actions{display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:wrap}

  .franjas{display:flex;flex-direction:column;gap:8px;margin-bottom:8px}
  .franja{display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb;border-radius:10px;background:#fff;padding:10px 12px}
  .franja small{color:var(--muted)}
  .franja .range{font-weight:700}

  .slots-list{display:flex;flex-direction:column;gap:8px;max-height:420px;overflow:auto;padding-right:6px}
  .slot{border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;background:#fff;font-weight:700;}
  .slot.free{background:#ecfdf5;border-color:#a7f3d0;color:#166534}
  .slot.busy{background:#fef2f2;border-color:#fecaca;color:#7f1d1d}
  .slot .muted{font-weight:600;color:#7f1d1d}
  .empty{color:var(--muted); text-align:center; padding:20px}

  /* Badges / Turnos */
  .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid #e5e7eb; }
  .badge-red { background:#fee2e2; border-color:#fecaca; color:#991b1b; }
  .badge-blue { background:#e0f2fe; border-color:#bae6fd; color:#1e3a8a; }
  .badge-gray { background:#f3f4f6; border-color:#e5e7eb; color:#374151; }
  .turnos-box{ margin-top:12px; display:flex; flex-direction:column; gap:8px; }
  .turno-item{ border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; background:#fff; display:flex; gap:8px; align-items:center; }

  /* MODAL FRANJA */
  .modal-backdrop{position:fixed; inset:0; background:rgba(0,0,0,.35); display:none; align-items:center; justify-content:center; z-index:50;}
  .modal{background:#fff; width:min(460px, 92vw); border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.2); padding:18px;}
  .modal h3{ margin:0 0 10px; }
  .modal .field{ display:flex; flex-direction:column; gap:6px; margin:10px 0 14px; }
  .modal input[type="text"], .modal input[type="time"], .modal textarea{border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; outline:none;}
  .modal .actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:10px; }
  .btn-secondary{ background:#e5e7eb; color:#111827; border:none; border-radius:10px; padding:9px 14px; cursor:pointer; }
  .btn-secondary:hover{ background:#d1d5db; }
</style>
</head>
<body>

<!-- nav -->
    <?php include('navMedico.php'); ?> 

<main class="wrap">
  <div class="page-head">
    <button class="btn btn-outline btn-sm" onclick="history.back()"><i class="fa-solid fa-arrow-left"></i><span>Volver</span></button>
    <h1 class="page-title">Gestionar Agenda</h1>
    <button class="btn btn-outline btn-sm" onclick="location.href='principalMed.php'"><i class="fa-solid fa-house"></i><span>Inicio</span></button>
  </div>

  <div class="grid">
    <!-- Calendario -->
    <section class="card">
      <div class="cal-header">
        <div class="month" id="monthLabel">—</div>
        <div class="cal-nav">
          <button class="btn btn-ghost btn-icon" id="prevBtn" title="Mes anterior"><i class="fa-solid fa-chevron-left"></i></button>
          <button class="btn btn-ghost btn-icon" id="nextBtn" title="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
      </div>
      <div class="dow"><span>LUN</span><span>MAR</span><span>MIÉ</span><span>JUE</span><span>VIE</span><span>SÁB</span><span>DOM</span></div>
      <div class="cal-grid" id="days"></div>
      <div class="legend">
        <span><span class="dot dot-green"></span> Disponible</span>
        <span><span class="dot dot-red"></span> Ocupado/Bloqueado</span>
        <span><span class="dot dot-gray"></span> Sin agenda</span>
        <span><span class="dot" style="background:#4A90E2"></span> Feriado</span>
      </div>
    </section>

    <!-- Panel derecho -->
    <section class="card">
      <div class="panel-head">
        <h3 id="dayTitle">Seleccioná una fecha</h3>
        <div class="panel-actions">
          <button id="btnBloqDia"     class="btn btn-outline btn-sm" disabled title="Bloquear día">
            <i class="fa-solid fa-lock"></i><span>Bloquear día</span>
          </button>
          <button id="btnDesbloqDia"  class="btn btn-outline btn-sm" disabled title="Desbloquear día">
            <i class="fa-solid fa-unlock"></i><span>Desbloquear día</span>
          </button>
          <button id="btnAddFranja"   class="btn btn-primary btn-sm" disabled title="Agregar franja">
            <i class="fa-solid fa-plus"></i><span>Franja</span>
          </button>
        </div>
      </div>

      <!-- Franjas del día -->
      <div id="franjasBox">
        <h4 style="margin:4px 0 8px;">Franjas del día</h4>
        <div class="franjas" id="franjasList">
          <div class="empty">No hay franjas cargadas para este día.</div>
        </div>
      </div>

      <!-- Slots -->
      <div id="dayBadges"></div>
      <h4 style="margin:10px 0 8px;">Horarios (30’)</h4>
      <div class="slots-list" id="slotsList">
        <div class="empty">Elegí un día en el calendario para ver horarios.</div>
      </div>

      <!-- Turnos -->
      <h4 style="margin:10px 0 8px;">Turnos del día</h4>
      <div id="turnosBox" class="turnos-box">
        <div class="empty">No hay turnos para este día.</div>
      </div>
    </section>
  </div>

  <!-- MODAL: Agregar franja -->
  <div id="modalFranja" class="modal-backdrop" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true">
      <h3>Agregar franja</h3>
      <div class="field"><label>Fecha</label><input type="text" id="mf-fecha" readonly></div>
      <div class="field"><label>Hora inicio</label><input type="time" id="mf-hora-inicio" step="1800" required></div>
      <div class="field"><label>Hora fin</label><input type="time" id="mf-hora-fin" step="1800" required></div>
      <div class="actions">
        <button class="btn-secondary" data-close-franja>Cancelar</button>
        <button class="btn btn-primary" id="mf-confirm"><i class="fa-solid fa-plus"></i> Crear franja</button>
      </div>
    </div>
  </div>
</main>

<script>
(() => {
  // ===== ENDPOINTS =====
  const API_BASE           = 'api';
  const API_ESTADO_MES     = `${API_BASE}/agenda_estado.php`;
  const API_FRANJAS_DIA    = `${API_BASE}/franjas_dia.php`;
  const API_SLOTS_PRIMARY  = `${API_BASE}/agenda_slots_medico.php`; // preferido (devuelve day/franjas/slots/turnos opcional)
  const API_SLOTS_FALLBACK = `${API_BASE}/agenda_slots.php`;
  const API_BLOQ_DIA       = `${API_BASE}/bloquear_dia.php`;
  const API_DESBLOQ_DIA    = `${API_BASE}/desbloquear_dia.php`;
  const API_BLOQ_SLOT      = `${API_BASE}/bloquear_slot.php`;
  const API_DESBLOQ_SLOT   = `${API_BASE}/desbloquear_slot.php`;
  const API_CREAR_FRANJA   = `${API_BASE}/crear_franja.php`;
  const API_ELIM_FRANJA    = `${API_BASE}/eliminar_franja.php`;
  const API_TURNOS_DIA     = `${API_BASE}/turnos_dia.php`;
  // Configuración: cantidad máxima de meses a mostrar (incluyendo el actual)
  const MAX_MONTHS = 2;  

  // DOM
  const monthLabel = document.getElementById('monthLabel');
  const daysGrid   = document.getElementById('days');
  const dayTitle   = document.getElementById('dayTitle');
  const prevBtn    = document.getElementById('prevBtn');
  const nextBtn    = document.getElementById('nextBtn');
  const btnBloqDia    = document.getElementById('btnBloqDia');
  const btnDesbloqDia = document.getElementById('btnDesbloqDia');
  const btnAddFranja  = document.getElementById('btnAddFranja');
  const franjasList = document.getElementById('franjasList');
  const slotsList   = document.getElementById('slotsList');
  const dayBadges   = document.getElementById('dayBadges');
  const turnosBox   = document.getElementById('turnosBox');

  // Modal franja
  let __lastFocus = null;
  const modalFranja = document.getElementById('modalFranja');
  const mfFecha     = document.getElementById('mf-fecha');
  const mfHoraIni   = document.getElementById('mf-hora-inicio');
  const mfHoraFin   = document.getElementById('mf-hora-fin');
  const mfConfirm   = document.getElementById('mf-confirm');

  document.querySelector('[data-close-franja]').onclick = ()=> hideModal(modalFranja);

  function showModal(el, focusSelector) {
    __lastFocus = document.activeElement;
    el.removeAttribute('aria-hidden');
    el.style.display = 'flex';
    requestAnimationFrame(() => {
      const toFocus = el.querySelector(focusSelector || '[autofocus], input, textarea, button');
      if (toFocus) toFocus.focus();
    });
  }
  function hideModal(el) {
    if (el.contains(document.activeElement)) document.activeElement.blur();
    el.setAttribute('aria-hidden','true');
    el.style.display = 'none';
    if (__lastFocus && typeof __lastFocus.focus === 'function') __lastFocus.focus();
  }

  // Helpers
  function asJson(resp){
    const ct = resp.headers.get('content-type') || '';
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    if (!ct.includes('application/json')) {
      return resp.text().then(t => { throw new Error('No JSON: ' + t.slice(0,200)); });
    }
    return resp.json();
  }
  const fromISODateLocal = (iso)=>{ const [Y,M,D]=iso.split('-').map(Number); return new Date(Y,M-1,D); };
  const pad = (n)=> String(n).padStart(2,'0');

  // Estado calendario
  const today = new Date(); today.setHours(0,0,0,0);
  let current = new Date(today.getFullYear(), today.getMonth(), 1);
  let selectedDate = null;

  prevBtn.onclick = () => { shiftMonth(-1); };
  nextBtn.onclick = () => { shiftMonth( 1); };

  btnBloqDia.onclick     = bloquearDiaConfirm;
  btnDesbloqDia.onclick  = desbloquearDiaConfirm;
  btnAddFranja.onclick   = openFranja;

  renderMonth();

  function canGoPrevMonth(){
    return (current.getFullYear() > today.getFullYear()) ||
           (current.getFullYear() === today.getFullYear() && current.getMonth() > today.getMonth());
  }

  function shiftMonth(delta){
  const nd = new Date(current); 
  nd.setMonth(current.getMonth() + delta);
  
  // Verificar si el nuevo mes está dentro del rango permitido
  const monthsDiff = (nd.getFullYear() - today.getFullYear()) * 12 + (nd.getMonth() - today.getMonth());
  if (monthsDiff < 0 || monthsDiff >= MAX_MONTHS) {
    return;  // No permitir navegación fuera del rango
  }
  
  current = nd;
  renderMonth(); 
  clearRight();
}

  // ===== Calendario con estados + tooltip feriado =====
  async function renderMonth(){
    const y = current.getFullYear();
    const m = current.getMonth()+1;
    monthLabel.textContent = current.toLocaleDateString('es-AR',{month:'long', year:'numeric'});
    daysGrid.innerHTML = '';
    prevBtn.disabled = !canGoPrevMonth();
    // Verificar si podemos ir al siguiente mes
const nextMonth = new Date(current); 
nextMonth.setMonth(current.getMonth() + 1);
const nextMonthsDiff = (nextMonth.getFullYear() - today.getFullYear()) * 12 + (nextMonth.getMonth() - today.getMonth());
nextBtn.disabled = (nextMonthsDiff >= MAX_MONTHS);

    const lead = (new Date(y, m-1, 1).getDay()+6)%7;
    for(let i=0;i<lead;i++){ const p=document.createElement('div'); p.className='day pad'; daysGrid.appendChild(p); }

    const lastDate = new Date(y, m, 0).getDate();
    const cells=[];
    for(let d=1; d<=lastDate; d++){
      const el=document.createElement('div'); el.className='day'; el.textContent=d;
      const iso = `${y}-${pad(m)}-${pad(d)}`; el.dataset.date=iso;
      if(fromISODateLocal(iso) < today) el.classList.add('disabled');
      daysGrid.appendChild(el); cells.push(el);
    }

    let map = {};
    try{
      const data = await fetch(`${API_ESTADO_MES}?anio=${y}&mes=${m}`).then(asJson);
      (Array.isArray(data)?data:[]).forEach(d => { map[d.dia]=d; });
    }catch(e){ /* sin estado mes */ }

  cells.forEach((box, idx)=>{
  const info=map[idx+1];
  const iso = box.dataset.date;
  const isPast = fromISODateLocal(iso) < today;
  
  // Si es día pasado, solo aplicar estilo disabled y salir
  if(isPast){
    box.classList.add('disabled');
    box.title = 'Fecha pasada';
    return; // No aplicar otros estilos
  }
  
  // Días futuros o actual
  if(!info){ 
    box.classList.add('none'); 
    box.title='Sin agenda'; 
  }
  else{
    if(info.estado==='azul'){ 
      box.classList.add('holiday'); 
      const dsc = (info.feriado_desc || '').trim();
      box.title = dsc ? `Feriado nacional: ${dsc}` : 'Feriado nacional';
    }
    else if(info.estado==='verde'){ 
      box.classList.add('free'); 
      box.title = 'Disponible';
    }
    else if(info.estado==='rojo'){ 
      box.classList.add('busy'); 
      box.title = 'Ocupado/Bloqueado';
    }
    else { 
      box.classList.add('none'); 
      box.title = 'Sin agenda';
    }
  }



      if (!box.classList.contains('disabled')){
        box.onclick=()=>{
          document.querySelectorAll('.day.selected').forEach(n=>n.classList.remove('selected'));
          box.classList.add('selected');
          selectedDate = box.dataset.date;
          btnBloqDia.disabled    = false;
          btnDesbloqDia.disabled = false;
          btnAddFranja.disabled  = false;
          loadFranjas(selectedDate);
          loadSlots(selectedDate);
          dayTitle.textContent = fromISODateLocal(selectedDate)
            .toLocaleDateString('es-AR',{weekday:'long', day:'2-digit', month:'long', year:'numeric'});
        };
      }else{
        box.onclick=null;
      }
    });
  }

  function clearRight(){
    selectedDate=null;
    btnBloqDia.disabled = btnDesbloqDia.disabled = btnAddFranja.disabled = true;
    franjasList.innerHTML = '<div class="empty">Seleccioná un día para ver sus franjas.</div>';
    slotsList.innerHTML   = '<div class="empty">Elegí un día en el calendario para ver horarios.</div>';
    dayBadges.innerHTML   = '';
    turnosBox.innerHTML   = '<div class="empty">No hay turnos para este día.</div>';
    dayTitle.textContent  = 'Seleccioná una fecha';
  }

  // ===== Franjas =====
  async function loadFranjas(fecha){
  franjasList.innerHTML = '<div class="empty">Cargando franjas...</div>';
  try{
    const raw = await fetch(`${API_FRANJAS_DIA}?fecha=${encodeURIComponent(fecha)}`).then(asJson);
    const arr = Array.isArray(raw) ? raw
              : Array.isArray(raw?.franjas) ? raw.franjas
              : Array.isArray(raw?.data) ? raw.data
              : [];
    if(!arr.length){
      franjasList.innerHTML = '<div class="empty">No hay franjas cargadas para este día.</div>';
      return;
    }
    franjasList.innerHTML='';
    arr.forEach(f=>{
      const id = f.id_agenda ?? f.franja_id ?? f.id ?? '';
      const hi = (f.hora_inicio||'').slice(0,5);
      // Solo mostrar hora de inicio, no el rango
      const row = document.createElement('div');
      row.className='franja';
      row.innerHTML = `
        <div>
          <div class="range">${hi}</div>
          <small>Slot de 30' · ID #${id}</small>
        </div>
        <div>
          ${id ? `<button class="btn btn-danger-outline btn-sm" data-del="${id}">
                    <i class="fa-solid fa-trash-can"></i><span>Eliminar</span>
                  </button>`:''}
        </div>`;
      if(id){
        row.querySelector('[data-del]').onclick = ()=> eliminarFranja(id, fecha);
      }
      franjasList.appendChild(row);
    });
  }catch(err){
    console.error('[franjas_dia]', err);
    franjasList.innerHTML = '<div class="empty">Error al cargar franjas.</div>';
  }
}

async function eliminarFranja(id_agenda, fecha) {
  // Confirmar si realmente se desea eliminar la franja
  if (!confirm('¿Eliminar esta franja?')) return;
  
  try {
    // Preparar el cuerpo de la solicitud
    const body = new URLSearchParams({ id_agenda });
    
    // Realizar la solicitud de eliminación
    const r = await fetch(API_ELIM_FRANJA, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    });

    // Verificar si la respuesta es exitosa
    if (!r.ok) throw new Error('HTTP ' + r.status);

    // Actualizar las franjas, slots y el mes
    await loadFranjas(fecha);
    await loadSlots(fecha);
    renderMonth();
    
    // Mostrar mensaje de éxito
    mostrarAlerta('success', 'Franja eliminada exitosamente.');

  } catch (e) {
    // Mostrar mensaje de error en caso de fallo
    mostrarAlerta('error', 'No se pudo eliminar la franja.');
    console.error(e);
  }
}


  // ===== Normalizador de slots (por si el backend viejo devuelve arrays simples) =====
  function normalizeSlots(raw){
  const base = Array.isArray(raw) ? raw
             : Array.isArray(raw?.slots) ? raw.slots
             : Array.isArray(raw?.data) ? raw.data
             : [];
  return base.map(s=>{
    // Extraer solo hora_inicio, ignorar hora_fin
    let h = s.hora_inicio || s.hora || '';
    h = String(h).slice(0,5);  // Solo HH:MM
    
    if ('disponible' in s) {
      return {hora:h, estado: s.disponible ? 'disponible':'ocupado', motivo: s.disponible ? '' : (s.motivo || 'Bloqueado/Ocupado'), en_franja: 1};
    }
    if (s.estado) {
      return {hora:h, estado: String(s.estado).toLowerCase(), motivo: s.motivo || '', en_franja: (s.en_franja?1:0)};
    }
    return {hora:h || '--:--', estado:'disponible', motivo:'', en_franja:1};
  }).sort((a,b)=> a.hora.localeCompare(b.hora));
}

  // ===== Construir 24h en base a franjas + ocupados =====
  function buildDaySlots(fecha, franjas, dayInfo, turnosSet = new Set(), bloqueosSet = new Set()) {
    const hm2s = h => { const [H,M]=h.split(':').map(Number); return H*3600+M*60; };
    const inFranja = (hh) => franjas.some(f => {
      const a = hm2s((f.hora_inicio||f.hi).slice(0,5));
      const b = hm2s((f.hora_fin||f.hf).slice(0,5));
      const t = hm2s(hh);
      return t >= a && t <= b - 30*60; // slot [inicio, fin)
    });

    const slots = [];
    const diaBloq   = !!dayInfo?.bloqueado;
    const esFeriado = !!dayInfo?.feriado;

    // Si NO hay franjas y no está bloqueado/feriado -> TODO habilitado (en_franja=1)
    const allEnabled = !franjas.length && !diaBloq && !esFeriado;

    for (let min=0; min<24*60; min+=30) {
      const hh = String(Math.floor(min/60)).padStart(2,'0')+':'+String(min%60).padStart(2,'0');

      const enf = allEnabled ? true : inFranja(hh);

      let estado = 'disponible';
      let motivo = '';

      if (diaBloq)                  { estado = 'ocupado';      motivo = dayInfo?.motivo_bloqueo || 'Día bloqueado'; }
      else if (esFeriado)           { estado = 'ocupado';      motivo = dayInfo?.feriado_desc ? `Feriado nacional: ${dayInfo.feriado_desc}` : 'Feriado nacional'; }
      else if (!enf)                { estado = 'fuera_franja'; motivo = 'Fuera de franja'; }
      else if (turnosSet.has(hh))   { estado = 'ocupado';      motivo = 'Turno asignado'; }
      else if (bloqueosSet.has(hh)) { estado = 'ocupado';      motivo = 'Bloqueado'; }

      slots.push({hora: hh, en_franja: enf ? 1 : 0, estado, motivo});
    }
    return slots;
  }

  // ===== fetchSlotsDay: intenta nuevo -> fallback -> sintetiza =====
  async function fetchSlotsDay(fecha){
    try{
      const data = await fetch(`${API_SLOTS_PRIMARY}?fecha=${encodeURIComponent(fecha)}`).then(asJson);
      if (data && (data.slots || Array.isArray(data))) {
        if (Array.isArray(data)) {
          return { day:{}, franjas:[], slots: normalizeSlots(data), turnos:[] };
        }
        // asegurar normalización de slots
        const out = {...data};
        out.slots = normalizeSlots(data.slots || []);
        return out;
      }
    }catch(e){ /* cae al fallback */ }

    try{
      const data = await fetch(`${API_SLOTS_FALLBACK}?fecha=${encodeURIComponent(fecha)}`).then(asJson);
      if (Array.isArray(data)) {
        return { day:{}, franjas:[], slots: normalizeSlots(data), turnos:[] };
      }
    }catch(_){}

    // última defensa: sin datos -> 24h disponibles
    return { day:{}, franjas:[], slots: buildDaySlots(fecha, [], {}), turnos:[] };
  }

  // ===== Render de slots + turnos =====
  async function loadSlots(fecha){
    dayBadges.innerHTML = '';
    slotsList.innerHTML = '<div class="empty">Cargando horarios...</div>';

    const res = await fetchSlotsDay(fecha);

    // Badges feriado / bloqueo
    const badges=[];
    if (res?.day?.feriado) {
      const motivoF = (res.day.feriado_desc||'').trim();
      badges.push(`<span class="badge badge-red">${motivoF ? 'Feriado nacional: '+motivoF : 'Feriado nacional'}</span>`);
    }
    if (res?.day?.bloqueado) {
      const motB = (res.day.motivo_bloqueo||'').trim();
      badges.push(`<span class="badge badge-blue">${motB ? 'Día bloqueado: '+motB : 'Día bloqueado'}</span>`);
    }
    if (badges.length){
      dayBadges.innerHTML = `<div style="margin:6px 0 10px;">${badges.join(' ')}</div>`;
    }

    // Si el back NO trae day/franjas, los reconstruimos con la info de franjas endpoint para tener en_franja correcto:
    // (Opcional — tu back ya lo trae en agenda_slots_medico.php)

    // Lista visible:
    const isFeriado  = !!res?.day?.feriado;
    const isBloqDia  = !!res?.day?.bloqueado;
    const hasFranjas = Array.isArray(res.franjas) && res.franjas.length > 0;

    let items = Array.isArray(res.slots) ? res.slots : [];

    // Si no vino en_franja porque vino del viejo endpoint, lo asumimos 1 (ya lo normalizamos).
    // Regla de visibilidad:
    // - Feriado o bloqueo de día: mostrar TODAS (todas ocupadas ya vienen así)
    // - Si hay franjas: mostrar sólo en_franja=1
    // - Si no hay franjas: mostrar todas (24h)
    if (!isFeriado && !isBloqDia && hasFranjas) {
      items = items.filter(s => s.en_franja === 1);
    }

    if (!items.length){
      slotsList.innerHTML = '<div class="empty">No hay horarios en la franja seleccionada.</div>';
    } else {
      slotsList.innerHTML = '';
      items.forEach(s=>{
        const row=document.createElement('div');
        let cls = 'slot ';
        cls += (s.estado==='disponible' ? 'free' : 'busy');
        row.className = cls;

        let rightHTML = '';
        if (s.estado==='disponible') {
          rightHTML = `<button class="btn btn-outline btn-sm" data-act="bloquear" data-h="${s.hora}">
                         <i class="fa-solid fa-lock"></i> Bloquear
                       </button>`;
        } else {
          if (isFeriado) {
            const t = (res?.day?.feriado_desc||'').trim();
            rightHTML = `<span class="muted">${ t ? 'Feriado nacional: '+t : 'Feriado nacional' }</span>`;
          } else if (isBloqDia) {
            const t = (res?.day?.motivo_bloqueo||'').trim();
            rightHTML = `<span class="muted">${ t ? 'Día bloqueado: '+t : 'Día bloqueado' }</span>`;
          } else if (s.motivo && s.motivo.toLowerCase()!=='turno asignado') {
            rightHTML = `<button class="btn btn-danger-outline btn-sm" data-act="desbloquear" data-h="${s.hora}">
                           <i class="fa-solid fa-unlock"></i> Desbloquear
                         </button>`;
          } else {
            rightHTML = `<span class="muted">${s.motivo || 'Ocupado'}</span>`;
          }
        }

        row.innerHTML = `<span>${s.hora}</span>${rightHTML}`;

        if (s.estado==='disponible'){
          row.querySelector('[data-act="bloquear"]').onclick = ()=> bloquearSlotAlert(fecha, s.hora);
        } else if (!isFeriado && !isBloqDia && s.motivo && s.motivo.toLowerCase()!=='turno asignado'){
          const b = row.querySelector('[data-act="desbloquear"]');
          if (b) b.onclick = ()=> desbloquearSlotAlert(fecha, s.hora);
        }
        slotsList.appendChild(row);
      });
    }

    // Turnos del día (si no vinieron)
    const turnos = (Array.isArray(res.turnos) && res.turnos.length)
      ? res.turnos
      : await fetch(`${API_TURNOS_DIA}?fecha=${encodeURIComponent(fecha)}`).then(r=>r.ok?r.json():[]);
    if (!turnos.length){
      turnosBox.innerHTML = '<div class="empty">No hay turnos para este día.</div>';
    } else {
      turnosBox.innerHTML = '';
      turnos.forEach(t=>{
        const i=document.createElement('div');
        i.className='turno-item';
        const hora = (t.hora||'').slice(0,5);
        const pac  = t.paciente ? t.paciente : `Paciente ${t.id_paciente||''}`;
        i.innerHTML = `<strong>${hora}</strong> <span>#${t.id_turno}</span> <span class="badge badge-gray">${pac}</span>`;
        turnosBox.appendChild(i);
      });
    }
  }

  // ===== Acciones Día (confirm / prompt) =====
  async function bloquearDiaConfirm(){
    if(!selectedDate) return;
    const motivo = prompt(`Bloquear el día completo ${selectedDate}\nMotivo (opcional):`,'');
    if (motivo===null) return;
    try{
      const body = new URLSearchParams({ fecha: selectedDate, motivo: motivo||'' });
      const r = await fetch(API_BLOQ_DIA, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      if(!r.ok) throw new Error('HTTP '+r.status);
      renderMonth(); loadFranjas(selectedDate); loadSlots(selectedDate);
    }catch(e){ alert('No se pudo bloquear el día.'); console.error(e); }
  }
  async function desbloquearDiaConfirm(){
    if(!selectedDate) return;
    if(!confirm('¿Quitar bloqueo del día '+selectedDate+'?')) return;
    try{
      const body = new URLSearchParams({ fecha: selectedDate });
      const r = await fetch(API_DESBLOQ_DIA, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      if(!r.ok) throw new Error('HTTP '+r.status);
      renderMonth(); loadFranjas(selectedDate); loadSlots(selectedDate);
    }catch(e){ alert('No se pudo desbloquear el día.'); console.error(e); }
  }

  // ===== Bloqueo / Desbloqueo de slot (prompt) =====
  async function bloquearSlotAlert(fecha,hora){
    const motivo = prompt(`Bloquear ${hora} del ${fecha}\nMotivo (opcional):`,'');
    if (motivo===null) return;
    try{
      const body = new URLSearchParams({ fecha, hora, motivo: motivo||'' });
      const r = await fetch(API_BLOQ_SLOT, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      if(!r.ok) throw new Error('HTTP '+r.status);
      renderMonth(); loadSlots(fecha);
    }catch(e){ alert('No se pudo bloquear el horario.'); console.error(e); }
  }
  async function desbloquearSlotAlert(fecha,hora){
    if(!confirm(`¿Quitar bloqueo de ${hora} del ${fecha}?`)) return;
    try{
      const body = new URLSearchParams({ fecha, hora });
      const r = await fetch(API_DESBLOQ_SLOT, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      if(!r.ok) throw new Error('HTTP '+r.status);
      renderMonth(); loadSlots(fecha);
    }catch(e){ alert('No se pudo desbloquear el horario.'); console.error(e); }
  }

  // ===== Crear franja =====
  function openFranja(){
    if(!selectedDate) return;
    mfFecha.value = selectedDate;
    mfHoraIni.value = '';
    mfHoraFin.value = '';
    showModal(modalFranja, '#mf-hora-inicio');
  }
  mfConfirm.onclick = async ()=>{
    mfConfirm.disabled = true;
    try{
      const body = new URLSearchParams({
        fecha: mfFecha.value,
        hora_inicio: mfHoraIni.value,
        hora_fin: mfHoraFin.value
      });
      const r = await fetch(API_CREAR_FRANJA, {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body
      });
      if(!r.ok) {
        const t = await r.text().catch(()=> '');
        throw new Error('HTTP '+r.status+' '+t);
      }
      hideModal(modalFranja);
      renderMonth();
      loadFranjas(mfFecha.value);
      loadSlots(mfFecha.value);
    }catch(e){
      alert('No se pudo crear la franja.\n' + (e?.message || ''));
      console.error(e);
    }finally{
      mfConfirm.disabled=false;
    }
  };
})();
</script>
</body>
</html>
