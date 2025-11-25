<?php
/**
 * ========================================
 * GESTIÓN DE TURNOS - TÉCNICO
 * ========================================
 * Con visualización y verificación de órdenes médicas
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$rol_requerido = 4; // Técnico
require_once('../../Logica/General/verificarSesion.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$nombre = $_SESSION['nombre'] ?? '';
$apellido = $_SESSION['apellido'] ?? '';
$id_tecnico = $_SESSION['id_tecnico'] ?? null;

$displayName = trim(mb_strtoupper($apellido) . ', ' . mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Turnos - Técnico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }


        .subnav {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.tab {
    background: #f8fafc;
    border: none;
    border-radius: 8px;
    padding: 10px 16px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #555;
    transition: 0.25s;
}

.tab:hover {
    background: #e3f2fd;
    color: #667eea;
}

.tab.active {
    background: #667eea;
    color: #fff;
}


        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            font-weight: 600;
            color: #333;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .filtros {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filtros input, .filtros select {
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            flex: 1;
            min-width: 200px;
        }

        .filtros input:focus, .filtros select:focus {
            outline: none;
            border-color: #667eea;
        }

        .turnos-grid {
            display: grid;
            gap: 20px;
        }

        .turno-card {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            position: relative;
        }

        .turno-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-color: #667eea;
        }

        .turno-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .turno-paciente {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        .turno-fecha {
            font-size: 14px;
            color: #64748b;
            margin-top: 5px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-confirmado {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-con-orden {
            background: #dcfce7;
            color: #166534;
        }

        .badge-sin-orden {
            background: #fef3c7;
            color: #92400e;
        }

        .turno-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            font-size: 14px;
        }

        .info-item i {
            color: #667eea;
            width: 20px;
        }

        .turno-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 80px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .loading {
            text-align: center;
            padding: 60px 20px;
        }

        .loading i {
            font-size: 48px;
            color: #667eea;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background: white;
            max-width: 900px;
            margin: 40px auto;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            animation: slideDown 0.3s;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 22px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close {
            font-size: 28px;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover {
            color: #1e293b;
        }

        .modal-body {
            padding: 30px;
        }

        .orden-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .orden-section h4 {
            margin: 0 0 10px 0;
            color: #1e293b;
            font-size: 16px;
        }

        .orden-section p {
            margin: 5px 0;
            color: #475569;
            line-height: 1.6;
        }

        .verificacion-result {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: start;
            gap: 15px;
        }

        .verificacion-result.valida {
            background: #dcfce7;
            border: 2px solid #86efac;
            color: #166534;
        }

        .verificacion-result.invalida {
            background: #fee2e2;
            border: 2px solid #fecaca;
            color: #991b1b;
        }

        .verificacion-result i {
            font-size: 32px;
        }

        .estudios-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .estudio-tag {
            background: #e0e7ff;
            color: #3730a3;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .turno-header {
                flex-direction: column;
                gap: 10px;
            }

            .turno-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    
<!-- nav -->
    <?php include('navTecnico.php'); ?> 

        <!-- Sub-navbar con pestañas (igual que médico) -->
<div class="subnav" style="background:#fff;display:flex;gap:10px;padding:15px 0;border-bottom:2px solid #e2e8f0;margin-bottom:25px;">
    <button class="tab active" data-estado="confirmado">
        <i class="fa-solid fa-calendar-check"></i> Confirmados
    </button>
    <button class="tab" data-estado="cancelado">
        <i class="fa-solid fa-ban"></i> Cancelados
    </button>
    <button class="tab" data-estado="vencido">
        <i class="fa-solid fa-triangle-exclamation"></i> Vencidos
    </button>
    <button class="tab" data-estado="atendido">
        <i class="fa-solid fa-user-check"></i> Atendidos
    </button>
</div>

<div class="filtros">
    <input 
        type="text" 
        id="buscarTurno" 
        placeholder="🔍 Buscar por paciente, DNI o estudio..."
    >
</div>

            <div id="listadoTurnos">
                <div class="loading">
                    <i class="fa-solid fa-spinner"></i>
                    <p>Cargando turnos...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Orden Médica -->
    <div class="modal" id="modalOrden">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-file-medical"></i> Orden Médica del Paciente</h3>
                <span class="close" id="cerrarModal">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Se llenará con JS -->
            </div>
        </div>
    </div>

    <script src="turnosTecnico.js"></script>
</body>
</html>