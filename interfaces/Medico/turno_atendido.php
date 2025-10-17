<?php
$rol_requerido = 2; require_once('../../Logica/General/verificarSesion.php');
if (session_status()===PHP_SESSION_NONE) { session_start(); }
$id_turno = (int)($_GET['id'] ?? 0);
$displayRight = trim(($_SESSION['apellido'] ?? '').', '.($_SESSION['nombre'] ?? ''));
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ficha médica</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{--primary:#1e88e5;--primary-600:#1565c0;--danger:#ef4444;--danger-600:#dc2626;--success:#16a34a;--bg:#f5f7fb;--card:#fff;--muted:#6b7280}
*{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,Helvetica,sans-serif;background:var(--bg)}
.wrap{max-width:1100px;margin:18px auto;padding:0 16px}
.badge{border:1px dashed #d1d5db;padding:6px 10px;border-radius:10px;background:#f9fafb}
.card{background:var(--card);border-radius:14px;box-shadow:0 10px 25px rgba(0,0,0,.08);padding:18px}
h1{margin:0 0 14px;font-size:28px}
.btn{border:none;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer;display:inline-flex;gap:8px;align-items:center}
.btn-primary{background:var(--success);color:#fff}.btn-danger{background:var(--danger);color:#fff}
.btn-outline{background:#fff;border:1px solid #bcd7ff;color:#0b5ac7}
.toast{position:fixed;right:16px;bottom:16px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;display:none}
</style></head><body>
<div class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <a class="btn btn-outline" href="turnos.php"><i class="fa-solid fa-arrow-left"></i> Volver a turnos</a>
    <span class="badge"><i class="fa-solid fa-user-doctor"></i> <?= htmlspecialchars($displayRight) ?></span>
  </div>

  <div class="card">
    <h1><i class="fa-solid fa-notes-medical"></i> Ficha médica · Turno #<?= $id_turno ?></h1>
    <label style="color:var(--muted);font-size:13px">Notas de atención</label>
    <textarea id="txt" style="width:100%;min-height:320px;border:1px solid #e5e7eb;border-radius:12px;padding:12px"></textarea>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
      <button id="btnSave" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
      <button id="btnDel"  class="btn btn-danger"><i class="fa-solid fa-trash"></i> Eliminar ficha</button>
      <a class="btn btn-outline" href="turno_derivar.php?id=<?= $id_turno ?>"><i class="fa-solid fa-user-md"></i> Derivar</a>
    </div>
    <div class="badge" style="margin-top:8px;color:#6b7280">Los cambios se guardan sin salir de esta pantalla.</div>
  </div>
</div>
<div id="toast" class="toast"></div>
<script>
const idTurno = <?= $id_turno ?>;
const toast=document.getElementById('toast');
function showToast(m){ toast.textContent=m; toast.style.display='block'; setTimeout(()=>toast.style.display='none',2200); }

async function getFicha(){
  try{
    const r=await fetch('api/ficha_get.php?id_turno='+idTurno);
    const j=await r.json(); if(!j.ok) throw 0;
    document.getElementById('txt').value=j.notas||'';
  }catch(_){ showToast('No se pudo cargar la ficha'); }
}
document.getElementById('btnSave').onclick=async ()=>{
  try{
    const body=new URLSearchParams({id_turno:idTurno,notas:document.getElementById('txt').value});
    const r=await fetch('api/ficha_save.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
    const j=await r.json(); if(!j.ok) throw 0; showToast('Ficha guardada');
  }catch(_){ showToast('No se pudo guardar'); }
};
document.getElementById('btnDel').onclick=async ()=>{
  if(!confirm('¿Eliminar el contenido de la ficha?')) return;
  try{
    const body=new URLSearchParams({id_turno:idTurno});
    const r=await fetch('api/ficha_delete.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
    const j=await r.json(); if(!j.ok) throw 0; document.getElementById('txt').value=''; showToast('Ficha eliminada');
  }catch(_){ showToast('No se pudo eliminar'); }
};
getFicha();
</script>
</body></html>
