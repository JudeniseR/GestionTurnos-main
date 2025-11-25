<?php
$rol_requerido = 2; require_once('../../Logica/General/verificarSesion.php');
if (session_status()===PHP_SESSION_NONE) { session_start(); }
$nom = $_SESSION['nombre'] ?? ''; $ape = $_SESSION['apellido'] ?? '';
$displayRight = trim(mb_strtoupper($ape).', '.mb_convert_case($nom, MB_CASE_TITLE, 'UTF-8'));
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Turnos · Por confirmar</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{--brand:#1e88e5;--brand-600:#1565c0;--bg:#f5f7fb;--card:#fff;--muted:#6b7280;--border:#e5e7eb;--shadow:0 10px 25px rgba(0,0,0,.08)}
*{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,Helvetica,sans-serif;background:var(--bg)}
.topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid var(--border)}
.navbar{max-width:1200px;margin:0 auto;padding:12px 16px;display:flex;justify-content:space-between;gap:10px}
.btn-link{background:#fff;border:1px solid var(--border);border-radius:10px;padding:8px 10px;display:inline-flex;gap:8px;align-items:center;text-decoration:none;color:#111;font-weight:700}
.wrap{max-width:1200px;margin:18px auto;padding:0 12px 34px}
.subnav{display:flex;gap:8px;flex-wrap:wrap}
.subnav a{border:1px solid var(--border);border-radius:999px;padding:8px 12px;text-decoration:none;color:#111;background:#fff;font-weight:700}
.subnav a.active{background:var(--brand);color:#fff;border-color:var(--brand)}
.card{background:var(--card);border-radius:14px;box-shadow:var(--shadow);padding:14px;border:1px solid rgba(0,0,0,.03)}
.list-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border)}
.thead,.row{display:grid;grid-template-columns:120px 90px 1.1fr .8fr 1fr 200px;align-items:center;gap:10px}
.thead{font-size:12px;color:var(--muted);padding:0 6px}
.row{background:#fff;border:1px solid var(--border);border-radius:12px;padding:10px;margin-bottom:10px}
.badge{padding:4px 8px;border-radius:999px;font-size:12px;display:inline-block}
.b-pend{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}
.btn{border:none;border-radius:10px;padding:8px 12px;display:inline-flex;gap:8px;align-items:center;font-weight:700;cursor:pointer}
.btn-outline{background:#fff;border:1px solid var(--border)}
.btn-primary{background:var(--brand);color:#fff;border:1px solid var(--brand-600)}
.btn-sm{padding:7px 10px}.btn-xs{height:36px;min-width:110px;font-size:14px}
.empty{padding:16px;text-align:center;color:var(--muted)}
.toast{position:fixed;right:16px;bottom:16px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;display:none}
</style>
</head><body>
<header class="topbar">
  <nav class="navbar">
    <div>
      <a class="btn-link" href="principalMed.php"><i class="fa-solid fa-house-medical"></i> Inicio</a>
      <a class="btn-link" href="agenda.php"><i class="fa-solid fa-calendar-days"></i> Agenda</a>
      <a class="btn-link" href="turnos_pendientes.php"><i class="fa-solid fa-list-check"></i> Turnos</a>
    </div>
    <div>
      <a class="btn-link" href="../../Logica/General/cerrarSesion.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
      <span class="btn-link" style="pointer-events:none"><i class="fa-solid fa-user-doctor"></i> <?= htmlspecialchars($displayRight) ?></span>
    </div>
  </nav>
</header>

<main class="wrap">
  <div class="card" style="margin-bottom:12px">
    <div class="subnav">
      <a class="active" href="turnos_pendientes.php"><i class="fa-solid fa-bell"></i> Por confirmar</a>
      <a href="turnos_confirmados.php"><i class="fa-solid fa-calendar-day"></i> Confirmados (hoy)</a>
      <a href="turnos_atendidos.php"><i class="fa-solid fa-user-check"></i> Atendidos</a>
      <a href="turnos_cancelados.php"><i class="fa-solid fa-ban"></i> Cancelados</a>
    </div>
  </div>

  <section class="card list-card">
    <div class="list-head">
      <div style="font-weight:800"><i class="fa-solid fa-table-list"></i> Turnos pendientes</div>
      <div id="pagerTop" class="muted"></div>
    </div>
    <div class="list-body">
      <div class="thead"><div>Fecha</div><div>Hora</div><div>Paciente</div><div>Estado</div><div>Obs.</div><div>Acciones</div></div>
      <div id="grid"></div>
      <div id="pagerBottom" class="muted" style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px;"></div>
    </div>
  </section>
</main>

<div id="toast" class="toast"></div>

<script>
(() => {
  const API='api';
  const grid=document.getElementById('grid'), toast=document.getElementById('toast');
  const pagerTop=document.getElementById('pagerTop'), pagerBottom=document.getElementById('pagerBottom');

  function tip(m){toast.textContent=m;toast.style.display='block';setTimeout(()=>toast.style.display='none',2200);}
  function el(t,c,x){const e=document.createElement(t); if(c)e.className=c; if(x!==undefined)e.appendChild(document.createTextNode(x)); return e;}
  function badgePend(){const b=el('span','badge b-pend','pendiente'); return b;}
  async function asJson(r){const t=await r.text(); if(!r.ok) throw new Error('HTTP '+r.status+' '+t); return JSON.parse(t);}

  function renderPager(where,total,per,page,onPage){
    where.innerHTML=''; const pages=Math.max(1,Math.ceil(total/per));
    const prev=el('button','btn btn-outline btn-sm','Anterior'), next=el('button','btn btn-outline btn-sm','Siguiente');
    prev.onclick=()=>{if(page>1)onPage(page-1)}; next.onclick=()=>{if(page<pages)onPage(page+1)}
    const lbl=el('span',''); lbl.style.margin='0 8px'; lbl.textContent='Página '+page+' de '+pages;
    where.append(prev,lbl,next);
  }

  function fila(t){
    const row=el('div','row'); row.dataset.id=t.id_turno;
    const cF=el('div','',t.fecha_fmt||t.fecha||''); 
    const cH=el('div','',(t.hora||'').slice(0,5));
    const cP=el('div'); cP.append(el('div','',t.paciente||('Paciente #'+t.id_paciente)), el('div','muted',t.dni?('DNI '+t.dni):''));
    const cE=el('div'); cE.appendChild(badgePend());
    const cO=el('div'); cO.append(el('div','',t.observaciones? (t.observaciones.length>80?t.observaciones.slice(0,80)+'…':t.observaciones) : '-'));
    const cA=el('div');
    const btnOk=document.createElement('button'); btnOk.className='btn btn-primary btn-xs';
    btnOk.innerHTML="<i class='fa-solid fa-check'></i> Aceptar";
    btnOk.onclick=()=>aceptar(t.id_turno);
    const ver=document.createElement('a'); ver.className='btn btn-outline btn-xs'; ver.href='turno_detalle.php?id='+t.id_turno; ver.target='_blank';
    ver.innerHTML="<i class='fa-solid fa-eye'></i> Ver";
    cA.append(btnOk, ver);
    row.append(cF,cH,cP,cE,cO,cA); 
    return row;
  }

  async function aceptar(id_turno){
    if(!confirm('¿Confirmar este turno?')) return;
    try{
      // 👉 Llama al MISMO archivo "turnos_confirmados.php" con __action=confirmar
      const res=await fetch('turnos_confirmados.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({__action:'confirmar', id_turno})
      });
      const data=await res.json();
      tip(data.msg|| (data.ok?'Confirmado':'No se pudo confirmar'));
      if(data.ok) cargar(currPage);
    }catch(e){ console.error(e); tip('Error al confirmar'); }
  }

  let currPage=1;
  async function cargar(page=1){
    currPage=page; grid.innerHTML='';
    const qs=new URLSearchParams({estado:'pendiente',page,per_page:10});
    try{
      const data=await fetch(API+'/turnos_list.php?'+qs.toString()).then(asJson);
      const items=data.items||[]; const total=data.total||items.length; const per=data.per_page||10; const cur=data.page||page;
      if(!items.length) grid.appendChild(el('div','empty','Sin turnos pendientes'));
      else items.forEach(i=>grid.appendChild(fila(i)));
      renderPager(pagerTop,total,per,cur,cargar); renderPager(pagerBottom,total,per,cur,cargar);
    }catch(e){ console.error(e); grid.appendChild(el('div','empty','Error cargando')); }
  }

  cargar(1);
})();
</script>
</body></html>
