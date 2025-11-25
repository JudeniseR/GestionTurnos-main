<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 2; // Médico
require_once('../../Logica/General/verificarSesion.php');
if (session_status()===PHP_SESSION_NONE) { session_start(); }

$nombre    = $_SESSION['nombre']   ?? '';
$apellido  = $_SESSION['apellido'] ?? '';
$displayRight = trim(mb_strtoupper($apellido) . ', ' . mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'));
$id_turno = (int)($_GET['id'] ?? 0);

if(!$id_turno){ header('Location: turnos_pendientes.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Detalle del turno #<?= $id_turno ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
  :root{--brand:#1e88e5;--brand-600:#1565c0;--ok:#16a34a;--bad:#ef4444;--border:#e5e7eb;--bg:#f5f7fb;--card:#fff;--muted:#6b7280;--shadow:0 10px 25px rgba(0,0,0,.08)}
  *{box-sizing:border-box} body{margin:0;font-family:Inter,Arial,Helvetica,sans-serif;background:var(--bg)}
  .wrap{max-width:1100px;margin:18px auto;padding:0 16px}
  .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
  .chip{border:1px dashed #d1d5db;padding:8px 10px;border-radius:10px;background:#f9fafb}
  .btn{border:none;border-radius:10px;padding:9px 12px;display:inline-flex;gap:8px;align-items:center;cursor:pointer;font-weight:700}
  .btn-outline{background:#fff;border:1px solid var(--border)}
  .btn-primary{background:var(--brand);color:#fff}
  .btn-ok{background:var(--ok);color:#fff}
  .btn-danger{background:#bad;color:#fff}
  .btn-danger{background:var(--bad);color:#fff}
  .btn[disabled]{opacity:.5;pointer-events:none}
  .grid{display:grid;grid-template-columns:1.05fr .95fr;gap:12px}
  .card{background:var(--card);border-radius:14px;border:1px solid rgba(0,0,0,.05);box-shadow:var(--shadow);padding:14px}
  .h{font-weight:800;margin-bottom:8px}
  textarea,input,select{width:100%;border:1px solid var(--border);border-radius:10px;padding:10px}
  .row-btns{display:flex;gap:8px;flex-wrap:wrap}
  .badge{padding:4px 8px;border-radius:999px;font-size:12px;display:inline-block}
  .b-conf{background:#e0f2fe;color:#075985;border:1px solid #bae6fd}
  .b-aten{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
  .b-canc{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
  .toast{position:fixed;right:16px;bottom:16px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;display:none;z-index:100}
  /* Modales */
  .modal-back{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:90}
  .modal{background:#fff;border-radius:14px;box-shadow:var(--shadow);width:100%;max-width:520px;padding:16px;border:1px solid var(--border)}
  .modal h3{margin:0 0 10px;font-size:18px}
  .modal .fld{display:flex;flex-direction:column;gap:6px;margin-bottom:10px}
  /* Historial */
  .timeline{display:flex;flex-direction:column;gap:10px}
  .tl-item{display:grid;grid-template-columns:140px 1fr;gap:10px;align-items:start}
  .tl-date{font-size:12px;color:var(--muted)}
  .tl-badge{font-size:12px;margin-left:6px}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <a class="btn btn-outline" href="javascript:history.back()"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    <span class="chip"><i class="fa-solid fa-user-doctor"></i> <?= htmlspecialchars($displayRight ?: 'MÉDICO') ?></span>
  </div>

  <div class="card" id="cardHeader">
    <div class="h"><i class="fa-solid fa-calendar-check"></i> Turno #<?= $id_turno ?></div>
    <div id="infoTurno" class="muted">Cargando…</div>
  </div>

  <div class="grid">
    <div class="card">
      <div class="h"><i class="fa-solid fa-file-medical"></i> Ficha médica</div>
      <textarea id="ficha" rows="12" placeholder="Evolución, diagnósticos, indicaciones…"></textarea>
      <div class="row-btns" style="margin-top:10px">
        <button id="btnGuardar" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar ficha</button>
      </div>
    </div>
    <div class="card">
      <div class="h"><i class="fa-solid fa-screwdriver-wrench"></i> Acciones</div>
      <div class="row-btns" style="margin-bottom:8px">
        <button id="bAtendido" class="btn btn-ok"><i class="fa-solid fa-user-check"></i> Marcar como atendido</button>
      </div>
      <div class="row-btns">
        <button id="bReprog" class="btn btn-outline"><i class="fa-solid fa-rotate-right"></i> Reprogramar</button>
        <button id="bCancel" class="btn btn-danger"><i class="fa-solid fa-ban"></i> Cancelar</button>
        <button id="bDerivar" class="btn btn-outline"><i class="fa-solid fa-user-doctor"></i> Derivar</button>
      </div>
      <hr style="margin:14px 0;border:none;border-top:1px solid var(--border)">
      <div class="h" style="margin-bottom:6px"><i class="fa-solid fa-clock-rotate-left"></i> Historial</div>
      <div id="timeline" class="timeline"><div class="muted">Cargando…</div></div>
    </div>
  </div>
</div>

<!-- Modales -->
<div id="mReprog" class="modal-back" role="dialog" aria-modal="true">
  <div class="modal">
    <h3><i class="fa-solid fa-rotate-right"></i> Reprogramar turno</h3>
    <div class="fld"><label>Nueva fecha</label><input id="rp-fecha" type="date"></div>
    <div class="fld"><label>Nueva hora</label><input id="rp-hora" type="time"></div>
    <div class="fld"><label>Motivo</label><textarea id="rp-motivo" rows="3"></textarea></div>
    <div class="row-btns">
      <button class="btn btn-outline" onclick="Modal.close('mReprog')">Cancelar</button>
      <button id="rp-enviar" class="btn btn-primary"><i class="fa-solid fa-rotate-right"></i> Reprogramar</button>
    </div>
  </div>
</div>

<div id="mCancel" class="modal-back" role="dialog" aria-modal="true">
  <div class="modal">
    <h3><i class="fa-solid fa-ban"></i> Cancelar turno</h3>
    <div class="fld"><label>Motivo</label><textarea id="cn-motivo" rows="3" placeholder="Motivo (obligatorio)"></textarea></div>
    <div class="row-btns">
      <button class="btn btn-outline" onclick="Modal.close('mCancel')">Volver</button>
      <button id="cn-enviar" class="btn btn-danger"><i class="fa-solid fa-ban"></i> Cancelar</button>
    </div>
  </div>
</div>

<div id="mDerivar" class="modal-back" role="dialog" aria-modal="true">
  <div class="modal">
    <h3><i class="fa-solid fa-user-doctor"></i> Derivar paciente</h3>
    <div class="fld"><label>Especialidad</label><select id="dv-esp"></select></div>
    <div class="fld"><label>Médico (opcional)</label><select id="dv-med"></select></div>
    <div class="fld"><label>Motivo / Indicación</label><textarea id="dv-motivo" rows="3"></textarea></div>
    <div class="row-btns">
      <button class="btn btn-outline" onclick="Modal.close('mDerivar')">Volver</button>
      <button id="dv-enviar" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Derivar</button>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script>
const API='api', ID=<?= $id_turno ?>;
const toast=document.getElementById('toast');
const ficha = document.getElementById('ficha');
const infoTurno = document.getElementById('infoTurno');
const timeline = document.getElementById('timeline');

const btnGuardar = document.getElementById('btnGuardar');
const bAtendido = document.getElementById('bAtendido');
const bReprog = document.getElementById('bReprog');
const bCancel = document.getElementById('bCancel');
const bDerivar = document.getElementById('bDerivar');

const Modal = { open:(id)=>document.getElementById(id).style.display='flex', close:(id)=>document.getElementById(id).style.display='none' };
function tip(m){toast.textContent=m;toast.style.display='block';setTimeout(()=>toast.style.display='none',2200);}
function asJson(r){return r.text().then(t=>{if(!r.ok) throw new Error('HTTP '+r.status+' '+t); try{return JSON.parse(t)}catch(_){throw new Error('JSON inválido')}})}
function badge(estado){const s=document.createElement('span');s.className='badge '+(estado==='confirmado'?'b-conf':estado==='atendido'?'b-aten':'b-canc');s.textContent=estado;return s;}

// Estado actual (para habilitar/ocultar acciones)
let ESTADO='pendiente';

async function loadHeader(){
  const r = await fetch(API+'/turnos_list.php?id_turno='+ID);
  const j = await asJson(r);
  const t = (j.items && j.items[0]) ? j.items[0] : null;
  if(!t){ infoTurno.textContent='No encontrado'; return; }

  ESTADO = (t.estado||'').toLowerCase();

  infoTurno.innerHTML = `
    <div><b>Paciente:</b> ${t.paciente || ('Paciente #'+t.id_paciente)} ${t.dni?('· DNI '+t.dni):''}</div>
    <div><b>Fecha y hora:</b> ${t.fecha_fmt || t.fecha} ${String(t.hora||'').slice(0,5)}</div>
    <div><b>Estado:</b> ${badge(ESTADO).outerHTML}</div>
    <div style="margin-top:6px"><b>Obs. turno:</b> ${t.observaciones? t.observaciones : '-'}</div>
  `;

  // Modo lectura si cancelado
  const readonly = ESTADO === 'cancelado';
  ficha.disabled = readonly;
  btnGuardar.disabled = readonly;
  bAtendido.disabled = (ESTADO!=='confirmado'); // solo desde confirmado
  bReprog.disabled  = readonly;
  bCancel.disabled  = readonly;
  bDerivar.disabled = readonly;
}

async function loadFicha(){
  try{
    const r=await fetch(API+'/ficha_get.php?id_turno='+ID);
    if(!r.ok){ ficha.value=''; return; }
    const j=await r.json(); ficha.value = j.nota || '';
  }catch{ ficha.value=''; }
}

async function loadHistorial(){
  timeline.innerHTML = '<div class="muted">Cargando…</div>';
  try{
    const r=await fetch(API+'/observaciones_list.php?id_turno='+ID);
    const j=await asJson(r);
    const items = j.items || [];
    if(!items.length){ timeline.innerHTML = '<div class="muted">Sin eventos</div>'; return; }
    timeline.innerHTML='';
    for(const it of items){
      const row=document.createElement('div'); row.className='tl-item';
      const d=document.createElement('div'); d.className='tl-date'; d.textContent=it.fecha||'';
      const c=document.createElement('div');
      const h=document.createElement('div'); h.style.fontWeight='700'; h.textContent=it.titulo||'-';
      const s=document.createElement('div'); s.textContent=it.detalle||'';
      if(it.badge){ const b=document.createElement('span'); b.className='tl-badge badge '+it.badge.cls; b.textContent=it.badge.txt; h.appendChild(b); }
      c.appendChild(h); c.appendChild(s);
      row.appendChild(d); row.appendChild(c);
      timeline.appendChild(row);
    }
  }catch(_){ timeline.innerHTML='<div class="muted">No se pudo obtener el historial</div>'; }
}

// Guardar ficha
btnGuardar.onclick = async ()=>{
  try{
    const body = new URLSearchParams({ id_turno:ID, nota:ficha.value });
    const r=await fetch(API+'/ficha_save.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
    if(!r.ok) throw 0; tip('Ficha guardada');
  }catch(_){ tip('No se pudo guardar'); }
};

// Acciones
bAtendido.onclick = async ()=>{
  try{
    const body=new URLSearchParams({id_turno:ID, estado:'atendido'});
    const r=await fetch(API+'/turnos_estado.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
    if(!r.ok) throw 0; tip('Marcado como atendido'); await loadHeader(); await loadHistorial();
  }catch(_){ tip('No se pudo actualizar'); }
};

bReprog.onclick = ()=> Modal.open('mReprog');
bCancel.onclick = ()=> Modal.open('mCancel');
bDerivar.onclick = async ()=>{
  await cargarEspecialidadesYMedicos();
  Modal.open('mDerivar');
};

// Reprogramar
document.getElementById('rp-enviar').onclick = async ()=>{
  const fecha = document.getElementById('rp-fecha').value;
  const hora  = document.getElementById('rp-hora').value;
  const motivo= document.getElementById('rp-motivo').value.trim();
  if(!fecha||!hora||!motivo) return tip('Completá fecha, hora y motivo');
  try{
    const body=new URLSearchParams({id_turno:ID, fecha, hora, motivo});
    const r=await fetch(API+'/turnos_reprogramar.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
    if(!r.ok) throw 0; tip('Turno reprogramado'); Modal.close('mReprog'); await loadHeader(); await loadHistorial();
  }catch(_){ tip('No se pudo reprogramar'); }
};

// Cancelar
document.getElementById('cn-enviar').onclick = async ()=>{
  const motivo=document.getElementById('cn-motivo').value.trim();
  if(!motivo) return tip('Escribí el motivo');
  try{
    const body=new URLSearchParams({id_turno:ID, estado:'cancelado', motivo});
    const r=await fetch(API+'/turnos_estado.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
    if(!r.ok) throw 0; tip('Turno cancelado'); Modal.close('mCancel'); await loadHeader(); await loadHistorial();
  }catch(_){ tip('No se pudo cancelar'); }
};

// Derivar
async function cargarEspecialidadesYMedicos(){
  const dvEsp=document.getElementById('dv-esp'), dvMed=document.getElementById('dv-med');
  dvEsp.innerHTML='<option value="">Seleccione…</option>'; dvMed.innerHTML='<option value="">(opcional)</option>';
  try{
    const esp=await (await fetch(API+'/especialidades_list.php')).json();
    (esp||[]).forEach(e=>{ const o=document.createElement('option'); o.value=e.id_especialidad; o.textContent=e.nombre_especialidad; dvEsp.appendChild(o); });
    dvEsp.onchange=async ()=>{
      dvMed.innerHTML='<option value="">(opcional)</option>';
      if(!dvEsp.value) return;
      const meds=await (await fetch(API+'/medicos_por_especialidad.php?id_especialidad='+dvEsp.value)).json();
      (meds||[]).forEach(m=>{ const o=document.createElement('option'); o.value=m.id_medico; o.textContent=m.nombre_completo; dvMed.appendChild(o); });
    };
  }catch{}
}
document.getElementById('dv-enviar').onclick = async ()=>{
  const esp=document.getElementById('dv-esp').value||'';
  const med=document.getElementById('dv-med').value||'';
  const mot=document.getElementById('dv-motivo').value.trim();
  if(!esp||!mot) return tip('Especialidad y motivo son obligatorios');
  try{
    const body=new URLSearchParams({id_turno:ID, id_especialidad:esp, id_medico_destino:med, motivo:mot});
    const r=await fetch(API+'/turno_derivar.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
    const j=await r.json().catch(()=>({ok:false}));
    if(!r.ok || j.ok===false) throw 0;
    tip('Derivación registrada'); Modal.close('mDerivar'); await loadHistorial();
  }catch(_){ tip('No se pudo derivar'); }
};

(async function init(){
  await loadHeader();
  await loadFicha();
  await loadHistorial();
})();
</script>
</body>
</html>
