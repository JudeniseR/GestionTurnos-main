<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

require_once('../../Persistencia/conexionBD.php');

$conn = ConexionBD::conectar();
$id_usuario = $_SESSION['id_usuario'];

// Obtener el id_paciente del titular
$stmt = $conn->prepare("SELECT id_paciente, nro_documento, token_qr FROM pacientes WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$titular = $result->fetch_assoc();
$stmt->close();

if (!$titular) {
    die("❌ No se encontró el paciente asociado a tu usuario.");
}

// Obtener datos del titular desde afiliados
$stmt = $conn->prepare("
    SELECT u.nombre, u.apellido, a.numero_afiliado, a.tipo_beneficiario
    FROM usuarios u
    LEFT JOIN afiliados a ON a.numero_documento = ?
    WHERE u.id_usuario = ?
");
$stmt->bind_param("si", $titular['nro_documento'], $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$datos_titular = $result->fetch_assoc();
$stmt->close();

// Obtener afiliados menores vinculados
$stmt = $conn->prepare("
    SELECT 
        p.id_paciente,
        p.nro_documento,
        p.token_qr,
        p.fecha_nacimiento,
        a.nombre,
        a.apellido,
        a.numero_afiliado,
        a.tipo_beneficiario
    FROM pacientes p
    INNER JOIN afiliados a ON a.numero_documento = p.nro_documento
    WHERE p.id_titular = ? 
    AND p.id_usuario IS NULL
    ORDER BY a.nombre, a.apellido
");
$stmt->bind_param("i", $titular['id_paciente']);
$stmt->execute();
$result = $stmt->get_result();
$menores = [];
while ($row = $result->fetch_assoc()) {
    $menores[] = $row;
}
$stmt->close();
$conn->close();

function calcularEdad($fecha_nacimiento) {
    if (!$fecha_nacimiento) return '-';
    $fecha = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha)->y;
    return $edad . ' años';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Credenciales</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2em;
        }
        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.5em;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .card {
            background: #f8f9fa;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .card-info {
            flex: 1;
        }
        .card-info h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        .card-info p {
            color: #666;
            margin: 5px 0;
            font-size: 0.95em;
        }
        .card-info .badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            margin-top: 8px;
        }
        .card-info .badge.menor {
            background: #f39c12;
        }
        .card-actions {
            display: flex;
            gap: 10px;
            flex-direction: column;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: scale(1.05);
        }
        .btn-secondary {
            background: #48c774;
            color: white;
        }
        .btn-secondary:hover {
            background: #3ab561;
            transform: scale(1.05);
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state img {
            width: 100px;
            opacity: 0.5;
            margin-bottom: 20px;
        }
        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            background: rgba(255,255,255,0.3);
        }
        @media (max-width: 768px) {
            .card {
                flex-direction: column;
                align-items: flex-start;
            }
            .card-actions {
                margin-top: 15px;
                width: 100%;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/interfaces/Paciente/principalPac.php" class="back-link">← Volver al inicio</a>
        
        <h1>📇 Mis Credenciales Virtuales</h1>

        <!-- Credencial del titular -->
        <div class="section">
            <h2>Tu Credencial</h2>
            <div class="card">
                <div class="card-info">
                    <h3><?= htmlspecialchars($datos_titular['nombre'] . ' ' . $datos_titular['apellido']) ?></h3>
                    <p><strong>Documento:</strong> <?= htmlspecialchars($titular['nro_documento']) ?></p>
                    <p><strong>Nº Afiliado:</strong> <?= htmlspecialchars($datos_titular['numero_afiliado'] ?? '-') ?></p>
                    <span class="badge"><?= htmlspecialchars(ucfirst($datos_titular['tipo_beneficiario'] ?? 'Titular')) ?></span>
                </div>
                <div class="card-actions">
                    <a href="verCredencialAfiliado.php?token=<?= urlencode($titular['token_qr']) ?>" 
                       class="btn btn-primary" target="_blank">
                        👁️ Ver Credencial
                    </a>
                    <a href="verCredencialAfiliado.php?token=<?= urlencode($titular['token_qr']) ?>&descargar=1" 
                       class="btn btn-secondary">
                        ⬇️ Descargar PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Credenciales de menores -->
        <div class="section">
            <h2>Credenciales de Afiliados Menores</h2>
            <?php if (empty($menores)): ?>
                <div class="empty-state">
                    <p>👶 No tienes afiliados menores registrados.</p>
                </div>
            <?php else: ?>
                <?php foreach ($menores as $menor): ?>
                    <div class="card">
                        <div class="card-info">
                            <h3><?= htmlspecialchars($menor['nombre'] . ' ' . $menor['apellido']) ?></h3>
                            <p><strong>Documento:</strong> <?= htmlspecialchars($menor['nro_documento']) ?></p>
                            <p><strong>Nº Afiliado:</strong> <?= htmlspecialchars($menor['numero_afiliado'] ?? '-') ?></p>
                            <p><strong>Edad:</strong> <?= calcularEdad($menor['fecha_nacimiento']) ?></p>
                            <span class="badge menor"><?= htmlspecialchars(ucfirst($menor['tipo_beneficiario'] ?? 'Hijo Menor')) ?></span>
                        </div>
                        <div class="card-actions">
                            <a href="verCredencialAfiliado.php?token=<?= urlencode($menor['token_qr']) ?>" 
                               class="btn btn-primary" target="_blank">
                                👁️ Ver Credencial
                            </a>
                            <a href="verCredencialAfiliado.php?token=<?= urlencode($menor['token_qr']) ?>&descargar=1" 
                               class="btn btn-secondary">
                                ⬇️ Descargar PDF
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>