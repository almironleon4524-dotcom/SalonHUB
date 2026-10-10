<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cierre de Caja

session_start();
require_once __DIR__ . '/permisos_helper.php';
require_once __DIR__ . '/notificaciones_helper.php';

requerir_login();
requerir_permiso('cerrar_caja');

$pdo = conectar();
$mensaje = '';
$error = '';
$id_usuario = $_SESSION['id_usuario'];

// Procesar cierre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cerrar_caja'])) {
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $monto_declarado = floatval($_POST['monto_declarado'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    try {
        // Calcular monto sistema del día
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(monto_total), 0) as total,
                COUNT(*) as cantidad
            FROM pago
            WHERE DATE(fecha_pago) = :fecha
              AND estado_pago = 'pagado'
        ");
        $stmt->execute([':fecha' => $fecha]);
        $resumen = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $monto_sistema = floatval($resumen['total']);
        $cantidad_pagos = intval($resumen['cantidad']);
        $diferencia = $monto_declarado - $monto_sistema;
        
        // Verificar si ya existe un cierre para ese día
        $stmt_check = $pdo->prepare("SELECT id_cierre FROM cierre_caja WHERE fecha = ?");
        $stmt_check->execute([$fecha]);
        if ($stmt_check->fetchColumn()) {
            throw new Exception('Ya existe un cierre para esa fecha.');
        }
        
        // Guardar cierre
        $stmt = $pdo->prepare("
            INSERT INTO cierre_caja 
            (fecha, id_usuario, monto_sistema, monto_declarado, diferencia, cantidad_pagos, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $fecha,
            $id_usuario,
            $monto_sistema,
            $monto_declarado,
            $diferencia,
            $cantidad_pagos,
            $observaciones
        ]);
        
        $id_cierre = $pdo->lastInsertId();
        
        // Notificar si hay diferencia
        if (abs($diferencia) > 0.01) {
            notificar_diferencia_caja($monto_sistema, $monto_declarado, $diferencia);
        }
        
        // Auditoría
        registrar_auditoria('crear', 'cierre_caja', $id_cierre, null, $_POST);
        
        if (abs($diferencia) > 0.01) {
            $mensaje = "Cierre guardado. Diferencia detectada: $" . number_format($diferencia, 2, ',', '.');
        } else {
            $mensaje = "Cierre guardado correctamente. ¡Caja cuadra!";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Filtro de fecha para el resumen
$fecha_resumen = $_GET['fecha'] ?? date('Y-m-d');

// Verificar si ya hay cierre para esa fecha
$stmt_cierre_existente = $pdo->prepare("
    SELECT c.*, u.usuario 
    FROM cierre_caja c 
    LEFT JOIN usuario u ON c.id_usuario = u.id_usuario 
    WHERE c.fecha = ?
");
$stmt_cierre_existente->execute([$fecha_resumen]);
$cierre_existente = $stmt_cierre_existente->fetch(PDO::FETCH_ASSOC);

// Resumen del día por método de pago
$stmt_pagos = $pdo->prepare("
    SELECT 
        mp.descripcion as metodo,
        COUNT(p.id_pago) as cantidad,
        COALESCE(SUM(p.monto_total), 0) as total
    FROM pago p
    LEFT JOIN metodo_pago mp ON p.id_metodo_pago = mp.id_metodo_pago
    WHERE DATE(p.fecha_pago) = :fecha
      AND p.estado_pago = 'pagado'
    GROUP BY mp.id_metodo_pago, mp.descripcion
    ORDER BY total DESC
");
$stmt_pagos->execute([':fecha' => $fecha_resumen]);
$pagos_por_metodo = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

// Total del día
$total_dia = 0;
$cantidad_dia = 0;
foreach ($pagos_por_metodo as $p) {
    $total_dia += floatval($p['total']);
    $cantidad_dia += intval($p['cantidad']);
}

// Historial de cierres (últimos 10)
$historial = $pdo->query("
    SELECT c.*, u.usuario 
    FROM cierre_caja c 
    LEFT JOIN usuario u ON c.id_usuario = u.id_usuario 
    ORDER BY c.fecha DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de Caja - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: #0d1117;
            color: #e6edf3;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        
        .header-panel {
            background: #161b22;
            border-bottom: 1px solid #30363d;
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-contenido {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 12px 0;
        }
        
        .logo-header a {
            font-size: 1.5rem;
            font-weight: 700;
            color: #c9a84c;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo-header a i {
            font-size: 1.8rem;
        }
        
        .nav-header {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .btn-outline-sm {
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.3s;
            background: transparent;
            border: 1px solid #30363d;
            color: #8b949e;
        }
        
        .btn-outline-sm:hover {
            border-color: #c9a84c;
            color: #c9a84c;
        }
        
        .btn-danger-outline:hover {
            border-color: #e74c3c;
            color: #e74c3c;
        }
        
        .contenedor-panel {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .contenedor-panel h2 {
            color: #e6edf3;
            font-size: 1.8rem;
            margin-bottom: 8px;
            border-bottom: 2px solid #30363d;
            padding-bottom: 12px;
        }
        
        .contenedor-panel h2 i {
            color: #c9a84c;
            margin-right: 10px;
        }
        
        .alerta {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alerta-exito {
            background: rgba(46, 160, 67, 0.15);
            border: 1px solid #238636;
            color: #3fb950;
        }
        
        .alerta-error {
            background: rgba(248, 81, 73, 0.15);
            border: 1px solid #da3633;
            color: #f85149;
        }
        
        .filtros-container {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }
        
        .filtro-grupo {
            flex: 1;
            min-width: 200px;
        }
        
        .filtro-grupo label {
            display: block;
            margin-bottom: 4px;
            font-size: 0.75rem;
            color: #8b949e;
            font-weight: 500;
        }
        
        .filtro-grupo label i {
            color: #c9a84c;
            margin-right: 4px;
        }
        
        .filtro-grupo input {
            width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #30363d;
            background: #0d1117;
            color: #e6edf3;
            font-size: 0.9rem;
        }
        
        .filtro-grupo input:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.15);
        }
        
        .btn-filtro {
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-filtro-primary {
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
        }
        
        .btn-filtro-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
        }
        
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .resumen-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .resumen-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }
        
        .resumen-card .titulo {
            color: #8b949e;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .resumen-card .valor {
            font-size: 2rem;
            font-weight: 700;
            color: #c9a84c;
        }
        
        .resumen-card .detalle {
            color: #8b949e;
            font-size: 0.8rem;
            margin-top: 4px;
        }
        
        .card-principal {
            background: linear-gradient(135deg, rgba(201, 168, 76, 0.1), rgba(201, 168, 76, 0.02));
            border-color: rgba(201, 168, 76, 0.3);
            grid-column: span 2;
        }
        
        .card-principal .valor {
            font-size: 2.5rem;
            color: #3fb950;
        }
        
        .pagos-lista {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .pagos-lista .header {
            background: rgba(201, 168, 76, 0.05);
            padding: 12px 20px;
            border-bottom: 1px solid #30363d;
            color: #c9a84c;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pago-item {
            padding: 12px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .pago-item:last-child {
            border-bottom: none;
        }
        
        .pago-item .metodo {
            color: #e6edf3;
            font-weight: 600;
        }
        
        .pago-item .cantidad {
            color: #8b949e;
            font-size: 0.85rem;
            margin-left: 8px;
        }
        
        .pago-item .monto {
            color: #3fb950;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .empty-pagos {
            text-align: center;
            padding: 40px 20px;
            color: #8b949e;
        }
        
        .empty-pagos i {
            font-size: 2.5rem;
            color: #30363d;
            display: block;
            margin-bottom: 12px;
        }
        
        .form-cierre {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .form-cierre h3 {
            color: #c9a84c;
            font-size: 1.1rem;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #30363d;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-cierre .form-group {
            margin-bottom: 16px;
        }
        
        .form-cierre label {
            display: block;
            color: #8b949e;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        
        .form-cierre label i {
            color: #c9a84c;
            margin-right: 4px;
        }
        
        .form-cierre input,
        .form-cierre textarea {
            width: 100%;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #30363d;
            background: #0d1117;
            color: #e6edf3;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .form-cierre input:focus,
        .form-cierre textarea:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.15);
        }
        
        .form-cierre textarea {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .btn-primario {
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primario:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f85149, #da3633);
            color: white;
        }
        
        .cierre-ok {
            background: rgba(46, 160, 67, 0.08);
            border: 1px solid rgba(46, 160, 67, 0.3);
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            border-left: 4px solid #3fb950;
        }
        
        .cierre-ok .icono {
            font-size: 2rem;
            color: #3fb950;
        }
        
        .cierre-ok .info h4 {
            color: #3fb950;
            margin-bottom: 4px;
        }
        
        .cierre-ok .info p {
            color: #8b949e;
            font-size: 0.9rem;
            margin: 2px 0;
        }
        
        .historial-tabla {
            width: 100%;
            border-collapse: collapse;
            background: #161b22;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #30363d;
        }
        
        .historial-tabla th {
            padding: 12px 16px;
            text-align: left;
            background: rgba(201, 168, 76, 0.05);
            color: #c9a84c;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #30363d;
        }
        
        .historial-tabla td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            color: #e6edf3;
            font-size: 0.9rem;
        }
        
        .historial-tabla tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .diferencia-positiva {
            color: #3fb950;
            font-weight: 700;
        }
        
        .diferencia-negativa {
            color: #f85149;
            font-weight: 700;
        }
        
        .diferencia-cero {
            color: #8b949e;
        }
        
        .footer-panel {
            text-align: center;
            padding: 30px 20px 20px;
            border-top: 1px solid #30363d;
            margin-top: 40px;
            color: #8b949e;
        }
        
        .footer-panel span {
            color: #c9a84c;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .card-principal { grid-column: span 1; }
            .filtros-container { flex-direction: column; }
            .filtro-grupo { min-width: auto; width: 100%; }
            .header-contenido { flex-wrap: wrap; gap: 10px; }
            .nav-header { flex-wrap: wrap; gap: 8px; }
            .contenedor-panel h2 { font-size: 1.4rem; }
            .historial-tabla { font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<header class="header-panel">
    <div class="header-contenido">
        <h1 class="logo-header">
            <a href="home.php">
                <i class="fas fa-cut"></i> SalonHub
            </a>
        </h1>
        <nav class="nav-header">
            <span class="usuario-info">
                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['usuario']) ?>
                <span class="badge-rol badge-<?= $_SESSION['rol'] ?>"><?= ucfirst($_SESSION['rol']) ?></span>
            </span>
            <a href="home.php" class="btn-outline-sm"><i class="fas fa-arrow-left"></i> Panel</a>
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2><i class="fas fa-cash-register"></i> Cierre de Caja</h2>
    <p style="color: #8b949e; margin-bottom: 24px;">Resumen de pagos y cierre del día</p>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- Filtro de fecha -->
    <form method="GET" class="filtros-container">
        <div class="filtro-grupo">
            <label><i class="fas fa-calendar-day"></i> Fecha del cierre</label>
            <input type="date" name="fecha" value="<?= htmlspecialchars($fecha_resumen) ?>">
        </div>
        <div>
            <button type="submit" class="btn-filtro btn-filtro-primary"><i class="fas fa-search"></i> Ver resumen</button>
        </div>
    </form>
    
    <!-- Si ya hay cierre -->
    <?php if ($cierre_existente): ?>
        <div class="cierre-ok">
            <div class="icono"><i class="fas fa-check-circle"></i></div>
            <div class="info">
                <h4>Caja cerrada el <?= date('d/m/Y', strtotime($cierre_existente['fecha'])) ?></h4>
                <p><strong>Cerrado por:</strong> <?= htmlspecialchars($cierre_existente['usuario']) ?></p>
                <p><strong>Monto sistema:</strong> $<?= number_format($cierre_existente['monto_sistema'], 2, ',', '.') ?></p>
                <p><strong>Monto declarado:</strong> $<?= number_format($cierre_existente['monto_declarado'], 2, ',', '.') ?></p>
                <p>
                    <strong>Diferencia:</strong> 
                    <?php if ($cierre_existente['diferencia'] > 0.01): ?>
                        <span class="diferencia-positiva">+$<?= number_format($cierre_existente['diferencia'], 2, ',', '.') ?></span>
                    <?php elseif ($cierre_existente['diferencia'] < -0.01): ?>
                        <span class="diferencia-negativa">-$<?= number_format(abs($cierre_existente['diferencia']), 2, ',', '.') ?></span>
                    <?php else: ?>
                        <span class="diferencia-cero">$0 (Caja cuadra)</span>
                    <?php endif; ?>
                </p>
                <?php if ($cierre_existente['observaciones']): ?>
                    <p><strong>Observaciones:</strong> <?= htmlspecialchars($cierre_existente['observaciones']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Resumen del día -->
    <div class="resumen-grid">
        <div class="resumen-card card-principal">
            <div class="titulo">Total del día</div>
            <div class="valor">$<?= number_format($total_dia, 2, ',', '.') ?></div>
            <div class="detalle"><i class="fas fa-receipt"></i> <?= $cantidad_dia ?> pagos registrados</div>
        </div>
        <div class="resumen-card">
            <div class="titulo">Métodos de pago</div>
            <div class="valor"><?= count($pagos_por_metodo) ?></div>
            <div class="detalle">Usados hoy</div>
        </div>
    </div>
    
    <!-- Detalle por método de pago -->
    <div class="pagos-lista">
        <div class="header">
            <i class="fas fa-credit-card"></i> Detalle por método de pago
        </div>
        <?php if (empty($pagos_por_metodo)): ?>
            <div class="empty-pagos">
                <i class="fas fa-inbox"></i>
                No hay pagos registrados para esta fecha
            </div>
        <?php else: ?>
            <?php foreach ($pagos_por_metodo as $p): ?>
                <div class="pago-item">
                    <div>
                        <span class="metodo"><?= htmlspecialchars($p['metodo'] ?? 'Sin método') ?></span>
                        <span class="cantidad">(<?= $p['cantidad'] ?> pagos)</span>
                    </div>
                    <span class="monto">$<?= number_format($p['total'], 2, ',', '.') ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Formulario de cierre -->
    <?php if (!$cierre_existente && $cantidad_dia > 0): ?>
        <div class="form-cierre">
            <h3><i class="fas fa-lock"></i> Realizar Cierre de Caja</h3>
            <form method="POST">
                <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha_resumen) ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-calculator"></i> Monto según sistema</label>
                        <input type="text" value="$<?= number_format($total_dia, 2, ',', '.') ?>" readonly style="background: rgba(201,168,76,0.05); cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-hand-holding-usd"></i> Monto declarado (contado)</label>
                        <input type="number" name="monto_declarado" step="0.01" min="0" required placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Observaciones (opcional)</label>
                    <textarea name="observaciones" placeholder="Notas sobre el cierre del día..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="submit" name="cerrar_caja" class="btn-primario" onclick="return confirm('¿Confirmar el cierre de caja?');">
                        <i class="fas fa-lock"></i> Cerrar Caja
                    </button>
                </div>
            </form>
        </div>
    <?php elseif (!$cierre_existente && $cantidad_dia == 0): ?>
        <div class="alerta" style="background: rgba(88, 166, 255, 0.08); border: 1px solid #30363d; color: #8b949e;">
            <i class="fas fa-info-circle"></i>
            No hay pagos registrados para esta fecha. No es necesario cerrar caja.
        </div>
    <?php endif; ?>
    
    <!-- Historial de cierres -->
    <h3 style="color: #c9a84c; margin-bottom: 16px; font-size: 1.1rem;">
        <i class="fas fa-history"></i> Historial de cierres
    </h3>
    
    <?php if (empty($historial)): ?>
        <div class="alerta" style="background: rgba(88, 166, 255, 0.08); border: 1px solid #30363d; color: #8b949e; justify-content: center;">
            <i class="fas fa-info-circle"></i>
            Todavía no hay cierres registrados
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="historial-tabla">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cerrado por</th>
                        <th>Monto sistema</th>
                        <th>Monto declarado</th>
                        <th>Diferencia</th>
                        <th>Pagos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $h): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($h['fecha'])) ?></strong></td>
                            <td><?= htmlspecialchars($h['usuario'] ?? 'Desconocido') ?></td>
                            <td>$<?= number_format($h['monto_sistema'], 2, ',', '.') ?></td>
                            <td>$<?= number_format($h['monto_declarado'], 2, ',', '.') ?></td>
                            <td>
                                <?php if ($h['diferencia'] > 0.01): ?>
                                    <span class="diferencia-positiva">+$<?= number_format($h['diferencia'], 2, ',', '.') ?></span>
                                <?php elseif ($h['diferencia'] < -0.01): ?>
                                    <span class="diferencia-negativa">-$<?= number_format(abs($h['diferencia']), 2, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="diferencia-cero">$0</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $h['cantidad_pagos'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Cierre de Caja</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

</body>
</html>