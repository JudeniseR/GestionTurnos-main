<?php 
$rol_requerido = 2;
require_once('../../Logica/General/verificarSesion.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$id_medico = $_SESSION['id_medico'] ?? null;
if (!$id_medico) { die('No autorizado'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seleccionar Horario</title>
<style>
:root {
  --primary: #1e88e5;
  --green: #10b981;
  --red: #ef4444;
  --gray: #d1d5db;
  --bg: #ffffff;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #fff; padding: 20px; }

.container { max-width: 420px; margin: 0 auto; }

/* Calendario */
.cal-card { background: #fff; border-radius: 16px; padding: 20px; margin-bottom: 20px; }
.cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.cal-header h3 { font-size: 17px; font-weight: 600; color: #111827; }
.nav-btn { 
  background: transparent; 
  border: none; 
  width: 32px; 
  height: 32px; 
  cursor: pointer; 
  display: flex; 
  align-items: center; 
  justify-content: center;
  color: #6b7280;
  font-size: 18px;
  border-radius: 8px;
  transition: all 0.2s;
}
.nav-btn:hover { background: #f3f4f6; color: #111827; }

.weekdays { 
  display: grid; 
  grid-template-columns: repeat(7, 1fr); 
  gap: 8px; 
  margin-bottom: 12px; 
}
.weekday { 
  text-align: center; 
  font-size: 12px; 
  color: #6b7280; 
  font-weight: 600;
  text-transform: uppercase;
}

.cal-grid { 
  display: grid; 
  grid-template-columns: repeat(7, 1fr); 
  gap: 8px; 
}
.day { 
  aspect-ratio: 1; 
  border: 1px solid transparent;
  border-radius: 12px; 
  display: flex; 
  align-items: center; 
  justify-content: center; 
  font-size: 15px; 
  font-weight: 500; 
  cursor: pointer; 
  background: #f9fafb;
  position: relative;
  transition: all 0.2s;
  color: #111827;
}
.day:hover:not(.disabled):not(.past) { 
  background: #e0f2fe; 
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.day.selected { 
  background: #0ea5e9 !important; 
  color: white !important; 
  border-color: #0ea5e9;
  box-shadow: 0 4px 12px rgba(14,165,233,0.3);
}
.day.disabled, .day.past { 
  background: transparent;
  color: #d1d5db; 
  cursor: not-allowed; 
}
.day.disabled:hover, .day.past:hover {
  transform: none;
  box-shadow: none;
}

/* Estados con puntos más grandes y centrados abajo */
.day.free { background: #d1fae5; color: #065f46; border-color: #10b981; }
.day.busy { background: #fee2e2; color: #991b1b; border-color: #ef4444; }
.day.none { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }

/* 
.day.free::after { 
  content: ''; 
  position: absolute; 
  bottom: 6px; 
  left: 50%;
  transform: translateX(-50%);
  width: 6px; 
  height: 6px; 
  background: var(--green); 
  border-radius: 50%; 
}
.day.busy::after { 
  content: ''; 
  position: absolute; 
  bottom: 6px; 
  left: 50%;
  transform: translateX(-50%);
  width: 6px; 
  height: 6px; 
  background: var(--red); 
  border-radius: 50%; 
}
.day.none::after { 
  content: ''; 
  position: absolute; 
  bottom: 6px; 
  left: 50%;
  transform: translateX(-50%);
  width: 6px; 
  height: 6px; 
  background: var(--gray); 
  border-radius: 50%; 
}
  */

.legend { 
  margin-top: 16px; 
  display: flex; 
  gap: 20px; 
  font-size: 13px; 
  color: #6b7280;
  justify-content: center;
}
.legend span { display: flex; align-items: center; gap: 6px; }
.dot { width: 10px; height: 10px; border-radius: 50%; }
.dot-green { background: var(--green); }
.dot-red { background: var(--red); }
.dot-gray { background: var(--gray); }

/* Horarios */
.slots-card { background: #fff; border-radius: 16px; padding: 20px; }
.slots-header { 
  font-size: 16px; 
  font-weight: 600; 
  margin-bottom: 16px; 
  color: #111827;
}
.slots-grid { 
  display: grid; 
  grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); 
  gap: 10px; 
  max-height: 280px; 
  overflow-y: auto; 
  padding: 4px;
}
.slot { 
  padding: 12px 8px; 
  border: 2px solid #d1fae5;
  border-radius: 12px; 
  text-align: center; 
  cursor: pointer; 
  font-size: 14px; 
  font-weight: 600; 
  background: #d1fae5;
  color: #065f46;
  transition: all 0.2s;
}
.slot:hover { 
  background: #a7f3d0; 
  border-color: #10b981;
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(16,185,129,0.2);
}
.slot.selected { 
  background: #0ea5e9; 
  color: white; 
  border-color: #0ea5e9;
  box-shadow: 0 4px 12px rgba(14,165,233,0.3);
}
.no-slots { 
  text-align: center; 
  color: #9ca3af; 
  padding: 32px 20px; 
  font-size: 14px; 
  grid-column: 1 / -1;
}
</style>
</head>
<body>

<div class="container">
  <div class="cal-card">
    <div class="cal-header">
      <button class="nav-btn" id="prevMonth">◀</button>
      <h3 id="monthYear">—</h3>
      <button class="nav-btn" id="nextMonth">▶</button>
    </div>
    
    <div class="weekdays">
      <div class="weekday">LUN</div>
      <div class="weekday">MAR</div>
      <div class="weekday">MIÉ</div>
      <div class="weekday">JUE</div>
      <div class="weekday">VIE</div>
      <div class="weekday">SÁB</div>
      <div class="weekday">DOM</div>
    </div>
    
    <div class="cal-grid" id="calendarDays"></div>
    
    <div class="legend">
      <span><span class="dot dot-green"></span> Disponible</span>
      <span><span class="dot dot-red"></span> Ocupado</span>
      <span><span class="dot dot-gray"></span> Sin agenda</span>
    </div>
  </div>

  <div class="slots-card">
    <div class="slots-header">Horarios disponibles</div>
    <div class="slots-grid" id="slotsGrid">
      <div class="no-slots">Seleccioná una fecha</div>
    </div>
  </div>
</div>

<script>
(() => {
  const API_ESTADO = 'api/agenda_estado.php';
  const API_SLOTS = 'api/agenda_slots_medico.php';
  
  let currentDate = new Date();
  const today = new Date(); today.setHours(0,0,0,0);
  let selectedDate = null;
  let selectedSlot = null;
  let estadoMesCache = {};

  const monthYearEl = document.getElementById('monthYear');
  const calendarDaysEl = document.getElementById('calendarDays');
  const slotsGridEl = document.getElementById('slotsGrid');
  const prevBtn = document.getElementById('prevMonth');
  const nextBtn = document.getElementById('nextMonth');

  const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

  function formatDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  function isToday(d) {
    return d.getDate() === today.getDate() &&
           d.getMonth() === today.getMonth() &&
           d.getFullYear() === today.getFullYear();
  }

  function isPast(d) {
    const check = new Date(d);
    check.setHours(0,0,0,0);
    return check < today;
  }

  async function fetchEstadoMes(y, m) {
    const key = `${y}-${m}`;
    if (estadoMesCache[key]) return estadoMesCache[key];
    
    try {
      const r = await fetch(`${API_ESTADO}?anio=${y}&mes=${m}`);
      if (!r.ok) return {};
      const data = await r.json();
      const map = {};
      (Array.isArray(data) ? data : []).forEach(d => { map[d.dia] = d; });
      estadoMesCache[key] = map;
      return map;
    } catch {
      return {};
    }
  }

  async function fetchSlots(fecha) {
    try {
      const r = await fetch(`${API_SLOTS}?fecha=${fecha}`);
      if (!r.ok) return null;
      return await r.json();
    } catch {
      return null;
    }
  }

  async function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    monthYearEl.textContent = `${meses[month]} ${year}`;
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const firstDayWeek = firstDay.getDay();
    const offset = firstDayWeek === 0 ? 6 : firstDayWeek - 1;
    
    calendarDaysEl.innerHTML = '';
    
    // Espacios vacíos
    for (let i = 0; i < offset; i++) {
      const empty = document.createElement('div');
      empty.className = 'day disabled';
      calendarDaysEl.appendChild(empty);
    }
    
    const estadoMap = await fetchEstadoMes(year, month + 1);
    const daysInMonth = lastDay.getDate();
    
    for (let day = 1; day <= daysInMonth; day++) {
      const d = new Date(year, month, day);
      const dateStr = formatDate(d);
      
      const dayEl = document.createElement('div');
      dayEl.className = 'day';
      dayEl.textContent = day;
      dayEl.dataset.date = dateStr;
      
      if (isPast(d) && !isToday(d)) {
        dayEl.classList.add('past');
      } else {
        const info = estadoMap[day];
        if (!info) {
          dayEl.classList.add('none');
        } else if (info.estado === 'verde') {
          dayEl.classList.add('free');
        } else if (info.estado === 'rojo') {
          dayEl.classList.add('busy');
        } else {
          dayEl.classList.add('none');
        }
      }
      
      if (selectedDate === dateStr) {
        dayEl.classList.add('selected');
      }
      
      dayEl.onclick = () => selectDate(dateStr, dayEl);
      calendarDaysEl.appendChild(dayEl);
    }
  }

  async function selectDate(dateStr, el) {
    if (el.classList.contains('past') || el.classList.contains('disabled')) return;
    
    selectedDate = dateStr;
    selectedSlot = null;
    
    document.querySelectorAll('.day').forEach(d => d.classList.remove('selected'));
    el.classList.add('selected');
    
    await renderSlots(dateStr);
    notifyParent();
  }

  async function renderSlots(fecha) {
    slotsGridEl.innerHTML = '<div class="no-slots">Cargando...</div>';
    
    const data = await fetchSlots(fecha);
    if (!data || !data.slots) {
      slotsGridEl.innerHTML = '<div class="no-slots">Sin datos</div>';
      return;
    }

    const slots = data.slots || [];
    const franjas = data.franjas || [];
    const hasFranjas = franjas.length > 0;
    const diaBloqueado = data.day?.bloqueado || false;
    const esFeriado = data.day?.feriado || false;

    // Filtrar solo disponibles que estén en franja (o todo si no hay franjas)
    let disponibles = slots.filter(s => s.estado === 'disponible');
    
    if (!diaBloqueado && !esFeriado && hasFranjas) {
      disponibles = disponibles.filter(s => s.en_franja === 1);
    }

    if (!disponibles.length) {
      slotsGridEl.innerHTML = '<div class="no-slots">Sin horarios disponibles</div>';
      return;
    }

    slotsGridEl.innerHTML = '';
    disponibles.forEach(slot => {
      const slotEl = document.createElement('div');
      slotEl.className = 'slot';
      slotEl.textContent = slot.hora;
      slotEl.dataset.hora = slot.hora;
      
      slotEl.onclick = () => {
        selectedSlot = slot.hora + ':00'; // convertir HH:MM a HH:MM:SS
        document.querySelectorAll('.slot').forEach(s => s.classList.remove('selected'));
        slotEl.classList.add('selected');
        notifyParent();
      };
      
      slotsGridEl.appendChild(slotEl);
    });
  }

  function notifyParent() {
    if (selectedDate && selectedSlot) {
      window.parent.postMessage({
        tipo: 'seleccion_agenda',
        fecha: selectedDate,
        hora: selectedSlot
      }, '*');
    }
  }

  prevBtn.onclick = () => {
    const prev = new Date(currentDate);
    prev.setMonth(prev.getMonth() - 1);
    if (prev.getFullYear() < today.getFullYear() || 
        (prev.getFullYear() === today.getFullYear() && prev.getMonth() < today.getMonth())) {
      return;
    }
    currentDate = prev;
    renderCalendar();
  };

  nextBtn.onclick = () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
  };

  renderCalendar();
})();
</script>
</body>
</html>