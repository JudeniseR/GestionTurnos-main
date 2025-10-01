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

  /* BUTTON SYSTEM */
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
  .day.disabled{opacity:.35; cursor:not-allowed}
  .day.free{background:#ecfdf5;border-color:#a7f3d0;color:#166534;font-weight:700}
  .day.busy{background:#fef2f2;border-color:#fecaca;color:#7f1d1d;font-weight:700}
  .day.selected{outline:2px solid var(--primary)}
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

  /* MODAL */
  .modal-backdrop{position:fixed; inset:0; background:rgba(0,0,0,.35); display:none; align-items:center; justify-content:center; z-index:50;}
  .modal{background:#fff; width:min(460px, 92vw); border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.2); padding:18px;}
  .modal h3{ margin:0 0 10px; }
  .modal .field{ display:flex; flex-direction:column; gap:6px; margin:10px 0 14px; }
  .modal input[type="text"], .modal input[type="time"], .modal textarea{border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; outline:none;}
  .modal textarea{ min-height:90px; resize:vertical; }
  .modal .actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:10px; }
  .btn-secondary{ background:#e5e7eb; color:#111827; border:none; border-radius:10px; padding:9px 14px; cursor:pointer; }
  .btn-secondary:hover{ background:#d1d5db; }
</style>
</head>
<body>
<header class="topbar">
  <nav class="navbar">
    <div class="nav-left">
      <a class="brand" href="principalMed.php"><i class="fa-solid fa-stethoscope"></i> Inicio</a>
      <a class="nav-link" href="agenda.php">Gestionar agenda</a>
      <a class="nav-link" href="turnos.php">Gestionar turnos</a>
    </div>
    <div class="nav-right">
      <a class="logout" href="../../Logica/General/cerrarSesion.php">Cerrar sesión</a>
      <div class="user">
        <span class="user-name"><?= htmlspecialchars($displayRight ?: 'MÉDICO') ?></span>
        <span class="user-avatar"><i class="fa-solid fa-user-doctor"></i></span>
      </div>
    </div>
  </nav>
</header>

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
      <h4 style="margin:10px 0 8px;">Horarios (30’)</h4>
      <div class="slots-list" id="slotsList">
        <div class="empty">Elegí un día en el calendario para ver horarios.</div>
      </div>
    </section>
  </div>

  <!-- MODALES -->
  <!-- Bloquear día -->
  <div id="modalDia" class="modal-backdrop" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true">
      <h3>Bloquear día completo</h3>
      <div class="field"><label>Fecha</label><input type="text" id="md-fecha" readonly></div>
      <div class="field"><label>Motivo (opcional)</label><textarea id="md-motivo" placeholder="Ej.: Congreso, licencia, reunión..."></textarea></div>
      <div class="actions">
        <button class="btn-secondary" data-close-dia>Cancelar</button>
        <button class="btn btn-primary" id="md-confirm"><i class="fa-solid fa-lock"></i> Bloquear día</button>
      </div>
    </div>
  </div>

  <!-- Bloquear slot -->
  <div id="modalSlot" class="modal-backdrop" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true">
      <h3>Bloquear horario</h3>
      <div class="field"><label>Fecha</label><input type="text" id="ms-fecha" readonly></div>
      <div class="field"><label>Hora</label><input type="text" id="ms-hora" readonly></div>
      <div class="field"><label>Motivo (opcional)</label><textarea id="ms-motivo" placeholder="Ej.: Práctica, interconsulta..."></textarea></div>
      <div class="actions">
        <button class="btn-secondary" data-close-slot>Cancelar</button>
        <button class="btn btn-primary" id="ms-confirm"><i class="fa-solid fa-lock"></i> Bloquear horario</button>
      </div>
    </div>
  </div>

  <!-- Agregar franja -->
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
  const API_BASE = 'api';

  // DOM base
  const monthLabel = document.getElementById('monthLabel');
  const daysGrid   = document.getElementById('days');
  const dayTitle   = document.getElementById('dayTitle');

  const prevBtn    = document.getElementById('prevBtn');
  const nextBtn    = document.getElementById('nextBtn');

  const btnBloqDia     = document.getElementById('btnBloqDia');
  const btnDesbloqDia  = document.getElementById('btnDesbloqDia');
  const btnAddFranja   = document.getElementById('btnAddFranja');

  const franjasList = document.getElementById('franjasList');
  const slotsList   = document.getElementById('slotsList');

  // Modales + accesibilidad
  let __lastFocus = null;
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
  const modalDia   = document.getElementById('modalDia');
  const mdFecha    = document.getElementById('md-fecha');
  const mdMotivo   = document.getElementById('md-motivo');
  const mdConfirm  = document.getElementById('md-confirm');

  const modalSlot  = document.getElementById('modalSlot');
  const msFecha    = document.getElementById('ms-fecha');
  const msHora     = document.getElementById('ms-hora');
  const msMotivo   = document.getElementById('ms-motivo');
  const msConfirm  = document.getElementById('ms-confirm');

  const modalFranja = document.getElementById('modalFranja');
  const mfFecha     = document.getElementById('mf-fecha');
  const mfHoraIni   = document.getElementById('mf-hora-inicio');
  const mfHoraFin   = document.getElementById('mf-hora-fin');
  const mfConfirm   = document.getElementById('mf-confirm');

  document.querySelector('[data-close-dia]').onclick    = ()=> hideModal(modalDia);
  document.querySelector('[data-close-slot]').onclick   = ()=> hideModal(modalSlot);
  document.querySelector('[data-close-franja]').onclick = ()=> hideModal(modalFranja);

  // Helpers
  function asJson(resp){
    const ct = resp.headers.get('content-type') || '';
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    if (!ct.includes('application/json')) {
      return resp.text().then(t => { throw new Error('No JSON: ' + t.slice(0,200)); });
    }
    return resp.json();
  }
  function fromISODateLocal(iso){ const [Y,M,D]=iso.split('-').map(Number); return new Date(Y,M-1,D); }

  // Estado
  const today = new Date(); today.setHours(0,0,0,0);
  let current = new Date(today.getFullYear(), today.getMonth(), 1);
  let selectedDate = null;

  prevBtn.onclick = () => { shiftMonth(-1); };
  nextBtn.onclick = () => { shiftMonth( 1); };

  btnBloqDia.onclick     = openDia;
  btnDesbloqDia.onclick  = desbloquearDia;
  btnAddFranja.onclick   = openFranja;

  renderMonth();

  function canGoPrevMonth(){
    return (current.getFullYear() > today.getFullYear()) ||
           (current.getFullYear() === today.getFullYear() && current.getMonth() > today.getMonth());
  }
  function shiftMonth(delta){
    const nd = new Date(current); nd.setMonth(current.getMonth()+delta);
    if(delta<0){
      const before = (nd.getFullYear() <  today.getFullYear()) ||
                     (nd.getFullYear() === today.getFullYear() && nd.getMonth() < today.getMonth());
      if(before) return;
    }
    current = nd;
    renderMonth(); clearRight();
  }
  function renderMonth(){
    const y = current.getFullYear();
    const m = current.getMonth()+1;
    monthLabel.textContent = current.toLocaleDateString('es-AR',{month:'long', year:'numeric'});
    daysGrid.innerHTML = '';
    prevBtn.disabled = !canGoPrevMonth();

    const pad = (new Date(y, m-1, 1).getDay()+6)%7;
    for(let i=0;i<pad;i++){ const p=document.createElement('div'); p.className='day pad'; daysGrid.appendChild(p); }

    const lastDate = new Date(y, m, 0).getDate();
    const cells=[];
    for(let d=1; d<=lastDate; d++){
      const box=document.createElement('div');
      box.className='day';
      box.textContent=d;
      const iso = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      box.dataset.date = iso;
      const dateObj = fromISODateLocal(iso);
      if(dateObj < today) box.classList.add('disabled');
      daysGrid.appendChild(box); cells.push(box);
    }

    fetch(`${API_BASE}/agenda_estado.php?anio=${y}&mes=${m}`)
      .then(asJson)
      .then(data=>{
        const map={}; (data||[]).forEach(d=>map[d.dia]=d);
        cells.forEach((box, idx)=>{
          const info=map[idx+1];
          if(!info){ box.classList.add('free'); box.title='Disponible'; }
          else{
            if(info.estado==='verde'){ box.classList.add('free'); box.title='Disponible'; }
            else if(info.estado==='rojo'){ box.classList.add('busy'); box.title='Ocupado/Bloqueado'; }
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
      })
      .catch(err=>{ console.error('[agenda_estado]', err); });
  }

  function clearRight(){
    selectedDate=null;
    btnBloqDia.disabled = btnDesbloqDia.disabled = btnAddFranja.disabled = true;
    franjasList.innerHTML = '<div class="empty">Seleccioná un día para ver sus franjas.</div>';
    slotsList.innerHTML   = '<div class="empty">Elegí un día en el calendario para ver horarios.</div>';
    dayTitle.textContent  = 'Seleccioná una fecha';
  }

  // ===== Franjas (rangos) =====
  function loadFranjas(fecha){
    franjasList.innerHTML = '<div class="empty">Cargando franjas...</div>';
    fetch(`${API_BASE}/franjas_dia.php?fecha=${encodeURIComponent(fecha)}`)
      .then(asJson)
      .then(items=>{
        if(!items || !items.length){
          franjasList.innerHTML = '<div class="empty">No hay franjas cargadas para este día.</div>';
          return;
        }
        franjasList.innerHTML='';
        items.forEach(f=>{
          const row = document.createElement('div');
          row.className='franja';
          row.innerHTML = `
            <div>
              <div class="range">${f.hora_inicio} – ${f.hora_fin}</div>
              <small>ID #${f.id_agenda}</small>
            </div>
            <div>
              <button class="btn btn-danger-outline btn-sm" data-del="${f.id_agenda}">
                <i class="fa-solid fa-trash-can"></i><span>Eliminar</span>
              </button>
            </div>`;
          row.querySelector('[data-del]').onclick = ()=> eliminarFranja(f.id_agenda, fecha);
          franjasList.appendChild(row);
        });
      })
      .catch(err=>{
        console.error('[franjas_dia]', err);
        franjasList.innerHTML = '<div class="empty">Error al cargar franjas.</div>';
      });
  }

  async function eliminarFranja(id_agenda, fecha){
    if(!confirm('¿Eliminar esta franja?')) return;
    try{
      const body = new URLSearchParams({ id_agenda });
      const r = await fetch(`${API_BASE}/eliminar_franja.php`, {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body
      });
      if(!r.ok) throw new Error('HTTP '+r.status);
      await loadFranjas(fecha);
      await loadSlots(fecha);
      renderMonth();
    }catch(e){
      alert('No se pudo eliminar la franja.');
      console.error(e);
    }
  }

  // ===== Slots =====
  function loadSlots(fecha){
    slotsList.innerHTML = '<div class="empty">Cargando horarios...</div>';
    fetch(`${API_BASE}/agenda_slots.php?fecha=${encodeURIComponent(fecha)}`)
      .then(asJson)
      .then(items=>{
        if(!items || !items.length){
          slotsList.innerHTML = '<div class="empty">No hay horarios configurados para este día.</div>';
          return;
        }
        slotsList.innerHTML = '';
        items.forEach(s=>{
          const row=document.createElement('div');
          const isBloqueo = (s.estado==='ocupado' && (s.motivo || '').toLowerCase() !== 'turno asignado');
          row.className = 'slot ' + (s.estado==='disponible' ? 'free' : 'busy');
          row.innerHTML = `
            <span>${s.hora}</span>
            ${
              s.estado==='disponible'
                ? '<button class="btn btn-outline btn-sm" data-act="bloquear" data-h="'+s.hora+'"><i class="fa-solid fa-lock"></i> Bloquear</button>'
                : (isBloqueo
                    ? '<button class="btn btn-danger-outline btn-sm" data-act="desbloquear" data-h="'+s.hora+'"><i class="fa-solid fa-unlock"></i> Desbloquear</button>'
                    : '<span class="muted">'+ (s.motivo || 'Ocupado') +'</span>')
            }`;
          if(s.estado==='disponible'){
            row.querySelector('[data-act="bloquear"]').onclick = ()=> openSlot(fecha, s.hora);
          } else if(isBloqueo){
            row.querySelector('[data-act="desbloquear"]').onclick = ()=> desbloquearSlot(fecha, s.hora);
          }
          slotsList.appendChild(row);
        });
      })
      .catch(err=>{
        console.error('[agenda_slots]', err);
        slotsList.innerHTML = '<div class="empty">Error al cargar horarios.</div>';
      });
  }

  // ===== Bloqueo de día =====
  function openDia(){
    if(!selectedDate) return;
    mdFecha.value = selectedDate;
    mdMotivo.value = '';
    showModal(modalDia, 'textarea, input, [autofocus]');
  }
  mdConfirm.onclick = async ()=>{
    mdConfirm.disabled=true;
    try{
      const body = new URLSearchParams({ fecha: mdFecha.value, motivo: mdMotivo.value });
      const r = await fetch(`${API_BASE}/bloquear_dia.php`, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      if(!r.ok) throw new Error('HTTP '+r.status);
      hideModal(modalDia); renderMonth(); loadFranjas(selectedDate); loadSlots(selectedDate);
    }catch(e){ alert('No se pudo bloquear el día.'); console.error(e); }
    finally{ mdConfirm.disabled=false; }
  };

  async function desbloquearDia(){
    if(!selectedDate) return;
    if(!confirm('¿Quitar bloqueo del día '+selectedDate+'?')) return;
    try{
      const body = new URLSearchParams({ fecha: selectedDate });
      const r = await fetch(`${API_BASE}/desbloquear_dia.php`, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      if(!r.ok) throw new Error('HTTP '+r.status);
      renderMonth(); loadFranjas(selectedDate); loadSlots(selectedDate);
    }catch(e){ alert('No se pudo desbloquear el día.'); console.error(e); }
  }

  // ===== Bloqueo / Desbloqueo de slot =====
  function openSlot(fecha, hora){
    msFecha.value=fecha; msHora.value=hora; msMotivo.value='';
    showModal(modalSlot, 'textarea, input, [autofocus]');
  }
  msConfirm.onclick = async ()=>{
    msConfirm.disabled=true;
    try{
      const body = new URLSearchParams({ fecha: msFecha.value, hora: msHora.value, motivo: msMotivo.value });
      const r = await fetch(`${API_BASE}/bloquear_slot.php`, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      if(!r.ok) throw new Error('HTTP '+r.status);
      hideModal(modalSlot); renderMonth(); loadSlots(msFecha.value);
    }catch(e){ alert('No se pudo bloquear el horario.'); console.error(e); }
    finally{ msConfirm.disabled=false; }
  };
  async function desbloquearSlot(fecha,hora){
    if(!confirm(`¿Quitar bloqueo de ${hora} del ${fecha}?`)) return;
    try{
      const body = new URLSearchParams({ fecha, hora });
      const r = await fetch(`${API_BASE}/desbloquear_slot.php`, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
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
      const r = await fetch(`${API_BASE}/crear_franja.php`, {
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
