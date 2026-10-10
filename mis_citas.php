<?php
/**
 * SalonHub - Mis Citas
 * Muestra las citas del cliente logueado
 */

session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('ver_mis_citas');

$pdo = conectar();
$id_cliente = $_SESSION['id_cliente'];

// Obtener citas del cliente
$stmt = $pdo->prepare("
    SELECT c.id_cita, c.fecha_hora, c.estado_cita, c.observaciones,
           s.nombre as servicio_nombre, s.duracion, s.precio,
           e.id_empleado, p.nombre as empleado_nombre, p.apellido as empleado_apellido
    FROM cita c
    LEFT JOIN detalle_cita dc ON dc.id_cita = c.id_cita
    LEFT JOIN servicios s ON s.id_servicio = dc.id_servicio
    LEFT JOIN empleado e ON e.id_empleado = c.id_empleado
    LEFT JOIN persona p ON p.id_persona = e.id_persona
    WHERE c.id_cliente = :id_cliente AND c.visible_panel = 1
    ORDER BY c.fecha_hora DESC
");
$stmt->execute([':id_cliente' => $id_cliente]);
$citas = $stmt->fetchAll();

// Estadísticas
$total = count($citas);
$pendientes = 0;
$confirmadas = 0;
$canceladas = 0;
$completadas = 0;

foreach ($citas as $cita) {
    switch ($cita['estado_cita']) {
        case 'pendiente': $pendientes++; break;
        case 'confirmada': $confirmadas++; break;
        case 'cancelada': $canceladas++; break;
        case 'completada': $completadas++; break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Citas - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* =================================================== */
        /* ESTILOS GENERALES (igual que los otros archivos)   */
        /* =================================================== */
        
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
            margin-bottom: 24px;
            border-bottom: 2px solid #30363d;
            padding-bottom: 12px;
        }
        
        .contenedor-panel h2 i {
            color: #c9a84c;
            margin-right: 10px;
        }
        
        /* =================================================== */
        /* ALERTAS                                            */
        /* =================================================== */
        
        .alerta {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alerta-info {
            background: rgba(88, 166, 255, 0.08);
            border: 1px solid #30363d;
            color: #8b949e;
        }
        
        .alerta-info a {
            color: #c9a84c;
            text-decoration: none;
            font-weight: 600;
        }
        
        .alerta-info a:hover {
            text-decoration: underline;
        }
        
        /* =================================================== */
        /* ESTADÍSTICAS                                       */
        /* =================================================== */
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 10px;
            padding: 14px 16px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }
        
        .stat-card .numero {
            font-size: 1.6rem;
            font-weight: 700;
            color: #e6edf3;
        }
        
        .stat-card .etiqueta {
            color: #8b949e;
            font-size: 0.7rem;
            margin-top: 2px;
        }
        
        .stat-card .icono {
            font-size: 1.2rem;
            margin-bottom: 2px;
        }
        
        .stat-card.total .icono { color: #58a6ff; }
        .stat-card.total { border-top: 3px solid #58a6ff; }
        
        .stat-card.pendiente .icono { color: #d29922; }
        .stat-card.pendiente { border-top: 3px solid #d29922; }
        
        .stat-card.confirmada .icono { color: #3fb950; }
        .stat-card.confirmada { border-top: 3px solid #3fb950; }
        
        .stat-card.cancelada .icono { color: #f85149; }
        .stat-card.cancelada { border-top: 3px solid #f85149; }
        
        .stat-card.completada .icono { color: #58a6ff; }
        .stat-card.completada { border-top: 3px solid #58a6ff; }
        
        /* =================================================== */
        /* TARJETAS DE CITAS                                   */
        /* =================================================== */
        
        .cita-card {
            background: #161b22;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 12px;
            border: 1px solid #30363d;
            border-left: 4px solid #c9a84c;
            transition: all 0.3s ease;
        }
        
        .cita-card:hover {
            border-color: rgba(201, 168, 76, 0.3);
            transform: translateX(4px);
        }
        
        .cita-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .cita-fecha {
            font-size: 1.1rem;
            font-weight: 700;
            color: #c9a84c;
        }
        
        .cita-fecha i {
            margin-right: 8px;
        }
        
        .cita-estado {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 20px;
        }
        
        .cita-estado-pendiente {
            background: rgba(210, 153, 34, 0.15);
            color: #d29922;
            border: 1px solid rgba(210, 153, 34, 0.2);
        }
        
        .cita-estado-confirmada {
            background: rgba(46, 160, 67, 0.15);
            color: #3fb950;
            border: 1px solid rgba(46, 160, 67, 0.2);
        }
        
        .cita-estado-completada {
            background: rgba(88, 166, 255, 0.15);
            color: #58a6ff;
            border: 1px solid rgba(88, 166, 255, 0.2);
        }
        
        .cita-estado-cancelada {
            background: rgba(248, 81, 73, 0.15);
            color: #f85149;
            border: 1px solid rgba(248, 81, 73, 0.2);
        }
        
        .cita-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px 20px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
        }
        
        .cita-body .campo {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #8b949e;
            font-size: 0.9rem;
        }
        
        .cita-body .campo i {
            color: #c9a84c;
            width: 18px;
            text-align: center;
        }
        
        .cita-body .campo strong {
            color: #e6edf3;
            font-weight: 500;
        }
        
        .cita-observaciones {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
            color: #8b949e;
            font-size: 0.85rem;
        }
        
        .cita-observaciones i {
            color: #c9a84c;
            margin-right: 6px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8b949e;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #30363d;
            display: block;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            color: #e6edf3;
            margin-bottom: 8px;
        }
        
        /* =================================================== */
        /* FOOTER                                             */
        /* =================================================== */
        
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
        
        /* =================================================== */
        /* RESPONSIVE                                          */
        /* =================================================== */
        
        @media (max-width: 768px) {
            .cita-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .cita-body {
                grid-template-columns: 1fr;
                gap: 4px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header-contenido {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .nav-header {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .contenedor-panel h2 {
                font-size: 1.4rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
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
            <a href="home.php" class="btn-outline-sm"><i class="fas fa-arrow-left"></i> Volver</a>
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2><i class="fas fa-calendar-alt"></i> Mis Citas</h2>
    
    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="icono"><i class="fas fa-calendar-alt"></i></div>
            <div class="numero"><?= $total ?></div>
            <div class="etiqueta">Total</div>
        </div>
        <div class="stat-card pendiente">
            <div class="icono"><i class="fas fa-clock"></i></div>
            <div class="numero"><?= $pendientes ?></div>
            <div class="etiqueta">Pendientes</div>
        </div>
        <div class="stat-card confirmada">
            <div class="icono"><i class="fas fa-check-circle"></i></div>
            <div class="numero"><?= $confirmadas ?></div>
            <div class="etiqueta">Confirmadas</div>
        </div>
        <div class="stat-card completada">
            <div class="icono"><i class="fas fa-check-double"></i></div>
            <div class="numero"><?= $completadas ?></div>
            <div class="etiqueta">Completadas</div>
        </div>
        <div class="stat-card cancelada">
            <div class="icono"><i class="fas fa-times-circle"></i></div>
            <div class="numero"><?= $canceladas ?></div>
            <div class="etiqueta">Canceladas</div>
        </div>
    </div>
    
    <!-- Lista de citas -->
    <?php if (empty($citas)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-plus"></i>
            <h3>No tenés citas programadas</h3>
            <p>Reservá tu primer turno en el salón.</p>
            <a href="nueva_cita.php" class="btn btn-primario" style="display: inline-block; margin-top: 12px;">
                <i class="fas fa-plus-circle"></i> Reservar un turno
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($citas as $cita): 
            $estado_class = 'cita-estado-' . $cita['estado_cita'];
            $estado_icon = 'fa-clock';
            switch($cita['estado_cita']) {
                case 'pendiente': $estado_icon = 'fa-clock'; break;
                case 'confirmada': $estado_icon = 'fa-check-circle'; break;
                case 'completada': $estado_icon = 'fa-check-double'; break;
                case 'cancelada': $estado_icon = 'fa-times-circle'; break;
            }
        ?>
            <div class="cita-card">
                <div class="cita-header">
                    <span class="cita-fecha">
                        <i class="fas fa-calendar-day"></i>
                        <?= date('d/m/Y', strtotime($cita['fecha_hora'])) ?>
                        <span style="color: #8b949e; font-weight: 400;">a las</span>
                        <?= date('H:i', strtotime($cita['fecha_hora'])) ?>
                    </span>
                    <span class="cita-estado <?= $estado_class ?>">
                        <i class="fas <?= $estado_icon ?>"></i>
                        <?= ucfirst($cita['estado_cita']) ?>
                    </span>
                </div>
                
                <div class="cita-body">
                    <div class="campo">
                        <i class="fas fa-cut"></i>
                        <strong>Servicio:</strong> 
                        <?= htmlspecialchars($cita['servicio_nombre'] ?? 'No especificado') ?>
                    </div>
                    <div class="campo">
                        <i class="fas fa-user-tie"></i>
                        <strong>Empleado:</strong> 
                        <?php if ($cita['empleado_nombre']): ?>
                            <?= htmlspecialchars($cita['empleado_nombre'] . ' ' . $cita['empleado_apellido']) ?>
                        <?php else: ?>
                            <span style="color: #8b949e;">Por asignar</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($cita['duracion']): ?>
                    <div class="campo">
                        <i class="far fa-clock"></i>
                        <strong>Duración:</strong> <?= htmlspecialchars($cita['duracion']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($cita['precio']): ?>
                    <div class="campo">
                        <i class="fas fa-dollar-sign"></i>
                        <strong>Precio:</strong> $<?= number_format($cita['precio'], 2) ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($cita['observaciones']): ?>
                    <div class="cita-observaciones">
                        <i class="fas fa-comment"></i>
                        <?= htmlspecialchars($cita['observaciones']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="nueva_cita.php" class="btn btn-primario" style="display: inline-block;">
                <i class="fas fa-plus-circle"></i> Reservar nuevo turno
            </a>
        </div>
    <?php endif; ?>
    
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Mis Citas</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

</body>
</html>