<?php
/**
 * ========================================
 * MIS ÓRDENES MÉDICAS - PACIENTE
 * ========================================
 * Ruta: /interfaces/Paciente/mis_ordenes.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$rol_requerido = 1; // Paciente
require_once('../../Logica/General/verificarSesion.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$nombre = $_SESSION['nombre'] ?? '';
$apellido = $_SESSION['apellido'] ?? '';
$id_paciente = $_SESSION['id_paciente_token'] ?? null;

//if (!$id_paciente) {
//    header('Location: ../../index.php');
//    exit;
//}

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Órdenes Médicas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css"/>
    <link rel="stylesheet" href="../../css/principalPac.css">
    <style>
        .container-ordenes {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }

        .filtros {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filtros input {
            flex: 1;
            min-width: 250px;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .filtros input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .orden-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 5px solid #667eea;
        }

        .orden-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 25px rgba(0,0,0,0.12);
        }

        .orden-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .orden-info h3 {
            margin: 0 0 8px 0;
            color: #1e293b;
            font-size: 20px;
        }

        .orden-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 14px;
            color: #64748b;
        }

        .orden-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
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

        .badge-activa {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .badge-utilizada {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde047;
        }

        .badge-verificada {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .orden-content {
            margin: 20px 0;
        }

        .orden-section {
            margin-bottom: 15px;
        }

        .orden-section label {
            display: block;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .orden-section .content {
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            color: #334155;
        }

        .estudios-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .estudio-tag {
            background: #e0e7ff;
            color: #3730a3;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
        }

        .orden-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 15px;
            border-top: 2px solid #f1f5f9;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 14px;
            text-decoration: none;
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

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
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

        .empty-state h3 {
            color: #64748b;
            margin: 0 0 10px 0;
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
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background: white;
            max-width: 800px;
            margin: 40px auto;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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

/* --- BLOQUE NO USADO: Verificación de firma digital (módulo paciente) ---
.verificacion-result {
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
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
.firma-preview {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 15px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    color: #475569;
    max-height: 150px;
    overflow-y: auto;
    word-break: break-all;
}
--- FIN BLOQUE NO USADO --- */


        @media (max-width: 768px) {
            .orden-header {
                flex-direction: column;
                gap: 15px;
            }

            .orden-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

/* Estilos del sello del médico */

        .sello-firma {
            border: 1px dashed #444;
            border-radius: 6px;
            padding: 12px;
            display: inline-block;
            text-align: center;
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #222;
            margin-top: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .sello-firma .sello-top {
            font-weight: bold;
            font-size: 15px;
        }
        .sello-firma .sello-mid {
            font-style: italic;
            font-size: 13px;
            margin: 3px 0;
        }
        .sello-firma .sello-bottom {
            font-size: 13px;
            margin-bottom: 3px;
        }
        .sello-firma .sello-fecha {
            font-size: 12px;
            color: #555;
        }
    </style>
</head>
<body>
    <?php include('navPac.php'); ?>

    <main class="container-ordenes">
        <div class="page-header">
            <h1>
                <i class="fa-solid fa-file-medical"></i>
                Mis Órdenes Médicas
            </h1>
            <p>Consultá tus órdenes médicas y descargalas en PDF</p>
        </div>

        <div class="filtros">
            <input 
                type="text" 
                id="buscarOrden" 
                placeholder="🔍 Buscar por médico, diagnóstico o estudio..."
            >
        </div>

        <div id="listadoOrdenes">
            <div class="loading">
                <i class="fa-solid fa-spinner"></i>
                <p>Cargando tus órdenes médicas...</p>
            </div>
        </div>
    </main>

    <!-- Modal: Detalle de Orden -->
    <div class="modal" id="modalDetalle">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-file-contract"></i> Detalle de Orden Médica</h3>
                <span class="close" id="cerrarModal">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Se llenará con JS -->
            </div>
        </div>
    </div>

    <script src="mis_ordenes.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
    <?php include '../footer.php'; ?>
</body>
</html>