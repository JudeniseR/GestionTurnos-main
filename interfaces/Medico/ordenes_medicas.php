<?php
/**
 * ========================================
 * GESTIÓN DE ÓRDENES MÉDICAS
 * ========================================
 * Ruta: /interfaces/Medico/ordenes_medicas.php
 * 
 * Permite al médico:
 * - Emitir órdenes médicas firmadas digitalmente
 * - Ver historial de órdenes emitidas
 * - Verificar firmas de órdenes
 */

// ===== Seguridad / Sesión =====
$rol_requerido = 2; // Médico
require_once('_boot_medico.php');
require_once('../../Logica/General/verificarSesion.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$id_medico = $_SESSION['id_medico'] ?? null;
$nombre    = $_SESSION['nombre']   ?? '';
$apellido  = $_SESSION['apellido'] ?? '';

$displayRight = trim(mb_strtoupper($apellido) . ', ' . mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'));

// Verificar si tiene claves generadas
require_once('../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$tiene_claves = false;
$sql = "SELECT clave_publica IS NOT NULL AS tiene_claves FROM medicos WHERE id_medico = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id_medico);
$stmt->execute();
$stmt->bind_result($tiene_claves);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Órdenes Médicas - Gestión</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --bg:#F3F6FD; --card:#fff; --ink:#0f172a; --muted:#64748b;
  --primary:#1e88e5; --primary-ink:#0d47a1; --ring:#cfe8ff;
  --ok:#16a34a; --warn:#f59e0b; --danger:#dc2626;
  --radius:16px; --shadow:0 10px 24px rgba(16,24,40,.08);
}
*{box-sizing:border-box}
html,body{margin:0}
body{font-family:Inter,system-ui,-apple-system,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}

/* Navbar */
.topbar{ position:sticky; top:0; z-index:1000; background:rgba(255,255,255,.95); backdrop-filter:blur(6px); border-bottom:1px solid #e5e7eb; }
.navbar{ max-width:1280px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; padding:16px 48px; gap:32px; }
.nav-left{display:flex; align-items:center; gap:36px;}
.brand{ color:#1e88e5; font-weight:800; text-decoration:none; display:flex; align-items:center; gap:10px; }
.nav-link{ color:#1e88e5; text-decoration:none; font-weight:700; } .nav-link:hover{text-decoration:underline}
.nav-right{display:flex; align-items:center; gap:16px;}
.logout{ color:#1e88e5; text-decoration:none; font-weight:700; } .logout:hover{text-decoration:underline}
.user{ display:flex; align-items:center; gap:10px; }
.user-name{ font-weight:800; white-space:nowrap; color:#1f2937; }
.user-avatar{ width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; background:#e8f1fb; color:#1e88e5; border:1px solid #c7ddfc; }

.wrap{max-width:1200px;margin:28px auto;padding:0 24px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;gap:10px;flex-wrap:wrap}
.page-title{display:flex;align-items:center;gap:12px;margin:0}
.page-title i{color:var(--primary);font-size:28px}
.kicker{font-size:14px;color:var(--muted);margin-top:4px}

.btn{display:inline-flex;gap:10px;align-items:center;border:none;border-radius:12px;padding:12px 18px;font-weight:800;cursor:pointer;transition:.2s;text-decoration:none}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{filter:brightness(0.96)}
.btn-outline{background:#fff;border:1px solid #d1d5db;color:#374151}
.btn-outline:hover{background:#f8fafc}
.btn-success{background:var(--ok);color:#fff}
.btn-disabled{opacity:.5;cursor:not-allowed;pointer-events:none}

.tabs{display:flex;gap:8px;margin-bottom:20px;border-bottom:2px solid #e5e7eb;flex-wrap:wrap}
.tab{padding:12px 20px;border:none;background:transparent;color:var(--muted);font-weight:700;cursor:pointer;border-bottom:3px solid transparent;transition:.2s}
.tab:hover{color:var(--ink)}
.tab.active{color:var(--primary);border-bottom-color:var(--primary)}

.card{background:var(--card);border:1px solid #e5e7eb;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px}
.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f1f5f9}
.card-title{font-weight:800;font-size:18px;display:flex;align-items:center;gap:8px}

.alert{padding:14px 16px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;gap:12px;border:1px solid}
.alert-warning{background:#fef3c7;color:#92400e;border-color:#fde047}
.alert-info{background:#dbeafe;color:#1e40af;border-color:#93c5fd}
.alert i{font-size:20px}

.form-grid{display:grid;gap:16px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group label{font-size:14px;color:#374151;font-weight:700}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px;border:1px solid #dde3ea;border-radius:12px;background:#fbfdff;font:inherit}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:2px solid var(--ring);border-color:var(--primary)}
.form-group textarea{min-height:120px;resize:vertical}
.helper{font-size:12px;color:var(--muted);margin-top:4px}

.input-with-icon{position:relative}
.input-with-icon input{padding-left:40px}
.input-with-icon i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted)}

.selected-items{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.selected-item{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;background:#e0f2fe;color:#075985;border-radius:8px;font-size:13px;font-weight:600}
.selected-item button{border:none;background:none;color:#075985;cursor:pointer;padding:0;margin-left:4px}

.orden-item{border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:12px;transition:.2s}
.orden-item:hover{border-color:#bfdbfe;background:#f8fafc}
.orden-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:12px}
.orden-paciente{font-weight:700;font-size:16px}
.orden-fecha{font-size:13px;color:var(--muted)}
.orden-content{font-size:14px;color:#475569;margin-bottom:10px}
.orden-footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid}
.badge-success{background:#dcfce7;color:#166534;border-color:#86efac}
.badge-warning{background:#fef3c7;color:#92400e;border-color:#fde047}
.badge-danger{background:#fee2e2;color:#7f1d1d;border-color:#fecaca}

.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-state i{font-size:64px;color:#cbd5e1;margin-bottom:16px}
.empty-state h3{margin:0 0 8px;color:#475569}

/* Modal */
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);justify-content:center;align-items:center;z-index:9999;padding:20px}
.modal-content{background:#fff;border-radius:16px;max-width:900px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.modal-header{display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #e5e7eb}
.modal-header h3{margin:0;font-size:20px;display:flex;align-items:center;gap:10px}
.close{cursor:pointer;font-size:28px;color:#aaa;line-height:1;transition:.2s}
.close:hover{color:#333}
.modal-body{padding:24px}
.modal-footer{padding:20px 24px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:10px}

.firma-preview{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:16px;font-family:ui-monospace,monospace;font-size:11px;color:#475569;max-height:200px;overflow-y:auto;word-break:break-all}

@media (max-width:768px){
  .navbar{padding:14px 20px}
  .header{flex-direction:column;align-items:flex-start}
  .form-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- nav -->
    <?php include('navMedico.php'); ?> 

  <main class="wrap">
    <!-- Header -->
    <div class="header">
      <div>
        <h1 class="page-title">
          <i class="fa-solid fa-file-medical"></i>
          Órdenes Médicas
        </h1>
        <div class="kicker">Emití y gestioná órdenes firmadas digitalmente</div>
      </div>
      <?php if ($tiene_claves): ?>
        <button class="btn btn-primary" id="btnNuevaOrden">
          <i class="fa-solid fa-plus"></i> Nueva Orden
        </button>
      <?php endif; ?>
    </div>

    <!-- Alerta si no tiene claves -->
    <?php if (!$tiene_claves): ?>
      <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
          <strong>Claves digitales no generadas</strong><br>
          <span style="font-size:13px">Debés generar tus claves digitales antes de poder emitir órdenes médicas.</span>
          <a href="principalMed.php" style="color:var(--primary-ink);font-weight:700;margin-left:8px">Ir a generar claves →</a>
        </div>
      </div>
    <?php else: ?>
      <!-- Tabs -->
      <div class="tabs">
        <button class="tab active" data-tab="activas">
          <i class="fa-solid fa-file-medical"></i> Órdenes Activas
        </button>
        <button class="tab" data-tab="historial">
          <i class="fa-solid fa-clock-rotate-left"></i> Historial
        </button>
      </div>

      <!-- Contenido de tabs -->
      <div id="tabActivas" class="tab-content">
        <div class="card">
          <div class="card-header">
            <div class="card-title">
              <i class="fa-solid fa-list"></i> Órdenes Activas
            </div>
            <div class="input-with-icon" style="width:300px">
              <i class="fa-solid fa-search"></i>
              <input type="text" id="buscarActivas" placeholder="Buscar por paciente o DNI...">
            </div>
          </div>
          <div id="listadoActivas">
            <div style="text-align:center;padding:20px;color:var(--muted)">
              <i class="fa-solid fa-spinner fa-spin" style="font-size:32px"></i>
              <p>Cargando órdenes...</p>
            </div>
          </div>
        </div>
      </div>

      <div id="tabHistorial" class="tab-content" style="display:none">
        <div class="card">
          <div class="card-header">
            <div class="card-title">
              <i class="fa-solid fa-archive"></i> Historial Completo
            </div>
            <div class="input-with-icon" style="width:300px">
              <i class="fa-solid fa-search"></i>
              <input type="text" id="buscarHistorial" placeholder="Buscar por paciente o DNI...">
            </div>
          </div>
          <div id="listadoHistorial">
            <div style="text-align:center;padding:20px;color:var(--muted)">
              <i class="fa-solid fa-spinner fa-spin" style="font-size:32px"></i>
              <p>Cargando historial...</p>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <!-- Modal: Nueva Orden -->
  <div class="modal" id="modalNuevaOrden">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fa-solid fa-file-medical"></i> Emitir Nueva Orden Médica</h3>
        <span class="close" id="cerrarModal">&times;</span>
      </div>
      <form id="formNuevaOrden">
        <div class="modal-body">
          <div class="form-grid" style="grid-template-columns:1fr 1fr">
            <!-- Búsqueda de paciente -->
            <div class="form-group" style="grid-column:span 2">
              <label for="buscarPaciente">
                <i class="fa-solid fa-user"></i> Paciente *
              </label>
              <div class="input-with-icon">
                <i class="fa-solid fa-search"></i>
                <input 
                  type="text" 
                  id="buscarPaciente"
                  placeholder="Buscar por nombre, apellido o DNI..."
                  autocomplete="off"
                >
              </div>
              <div id="resultadosPacientes" style="position:relative"></div>
              <input type="hidden" id="id_paciente" name="id_paciente" required>
              <input type="hidden" id="id_titular" name="id_titular">
               <input type="hidden" id="id_afiliado" name="id_afiliado">
              <div id="pacienteSeleccionado" style="margin-top:8px"></div>
            </div>

            <!-- Diagnóstico -->
            <div class="form-group" style="grid-column:span 2">
              <label for="diagnostico">
                <i class="fa-solid fa-notes-medical"></i> Diagnóstico *
              </label>
              <textarea 
                id="diagnostico" 
                name="diagnostico"
                placeholder="Ingresá el diagnóstico médico..."
                required
                maxlength="1000"
              ></textarea>
              <div class="helper">Máximo 1000 caracteres</div>
            </div>

            <!-- Estudios indicados -->
            <div class="form-group" style="grid-column:span 2">
              <label>
                <i class="fa-solid fa-flask"></i> Estudios Indicados *
              </label>
              <select id="selectEstudio">
                <option value="">Cargando estudios...</option>
              </select>
              <div class="selected-items" id="estudiosSeleccionados"></div>
              <input type="hidden" id="estudios_indicados" name="estudios_indicados" required>
            </div>

            <!-- Observaciones -->
            <div class="form-group" style="grid-column:span 2">
              <label for="observaciones">
                <i class="fa-solid fa-comment-medical"></i> Observaciones (opcional)
              </label>
              <textarea 
                id="observaciones" 
                name="observaciones"
                placeholder="Indicaciones adicionales, preparación especial, etc..."
                rows="4"
                maxlength="500"
              ></textarea>
              <div class="helper">Máximo 500 caracteres</div>
            </div>
          </div>

          <div class="alert alert-info" style="margin-top:20px">
            <i class="fa-solid fa-shield-halved"></i>
            <div style="font-size:13px">
              Esta orden será <strong>firmada digitalmente</strong> con tu clave privada RSA. 
              El paciente podrá verificar la autenticidad de la firma usando tu clave pública.
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline" id="btnCancelar">
            <i class="fa-solid fa-xmark"></i> Cancelar
          </button>
          <button type="submit" class="btn btn-primary" id="btnEmitir">
            <i class="fa-solid fa-signature"></i> Firmar y Emitir
          </button>
        </div>
      </form>

      <div id="loadingEmision" style="display:none;text-align:center;padding:40px">
        <i class="fa-solid fa-spinner fa-spin" style="font-size:48px;color:var(--primary)"></i>
        <p style="margin-top:16px;color:var(--muted);font-weight:600">Firmando orden digitalmente...</p>
      </div>
    </div>
  </div>

  <!-- Modal: Detalle de Orden -->
  <div class="modal" id="modalDetalleOrden">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fa-solid fa-file-contract"></i> Detalle de Orden Médica</h3>
        <span class="close" id="cerrarModalDetalle">&times;</span>
      </div>
      <div class="modal-body" id="detalleOrdenBody">
        <!-- Se llenará con JS -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modalDetalleOrden').style.display='none'">
          Cerrar
        </button>
      </div>
    </div>
  </div>

    <script src="ordenes_medicas.js"></script>
  </body>
</html>