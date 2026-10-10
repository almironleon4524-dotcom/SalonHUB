<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// SalonHub - Dashboard de Administración

session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_permiso('ver_reportes');

$rol        = $_SESSION['rol']     ?? 'cliente';
$usuario    = $_SESSION['usuario'] ?? 'Usuario';

$pdo = conectar();

// ==================== ESTADÍSTICAS ====================

// Total de usuarios
$stmt = $pdo->query("SELECT COUNT(*) as total FROM usuario");
$total_usuarios = $stmt->fetchColumn();

// Usuarios por rol
$stmt = $pdo->query("SELECT rol, COUNT(*) as cantidad FROM usuario GROUP BY rol");
$usuarios_por_rol = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total de clientes
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cliente");
$total_clientes = $stmt->fetchColumn();

// Total de productos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM productos");
$total_productos = $stmt->fetchColumn();

// Total de servicios
$stmt = $pdo->query("SELECT COUNT(*) as total FROM servicios");
$total_servicios = $stmt->fetchColumn();

// Total de citas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cita");
$total_citas = $stmt->fetchColumn();

// Citas por estado
$stmt = $pdo->query("SELECT estado_cita, COUNT(*) as cantidad FROM cita GROUP BY estado_cita");
$citas_por_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Citas de hoy
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cita WHERE DATE(fecha_hora) = CURDATE()");
$citas_hoy = $stmt->fetchColumn();

// Últimos 5 usuarios registrados
$stmt = $pdo->query("
    SELECT u.*, p.nombre, p.apellido 
    FROM usuario u
    LEFT JOIN persona p ON p.id_persona = u.id_cliente
    ORDER BY u.id_usuario DESC
    LIMIT 5
");
$ultimos_usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Últimas 5 citas
$stmt = $pdo->query("
    SELECT c.*, p.nombre as cliente_nombre, p.apellido as cliente_apellido
    FROM cita c
    LEFT JOIN cliente cl ON c.id_cliente = cl.id_cliente
    LEFT JOIN persona p ON cl.id_persona = p.id_persona
    ORDER BY c.id_cita DESC
    LIMIT 5
");
$ultimas_citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Productos con stock bajo
$stmt = $pdo->query("
    SELECT nombre_producto, stock_actual, stock_minimo
    FROM productos
    WHERE stock_actual <= stock_minimo AND stock_actual > 0
    ORDER BY stock_actual ASC
    LIMIT 5
");
$stock_bajo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Productos sin stock
$stmt = $pdo->query("
    SELECT nombre_producto, stock_actual
    FROM productos
    WHERE stock_actual = 0
    ORDER BY nombre_producto
    LIMIT 5
");
$sin_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub | Dashboard</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #0d1117; color: #e6edf3; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; }
        
        .header-panel { background: #161b22; border-bottom: 1px solid #30363d; padding: 0 20px; position: sticky; top: 0; z-index: 100; }
        .header-contenido { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; padding: 12px 0; }
        .logo-header a { font-size: 1.5rem; font-weight: 700; color: #c9a84c; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .logo-header a i { font-size: 1.8rem; }
        .nav-header { display: flex; align-items: center; gap: 16px; }
        .btn-outline-sm { padding: 6px 16px; border-radius: 8px; font-size: 0.8rem; text-decoration: none; transition: all 0.3s; background: transparent; border: 1px solid #30363d; color: #8b949e; }
        .btn-outline-sm:hover { border-color: #c9a84c; color: #c9a84c; }
        .btn-danger-outline:hover { border-color: #e74c3c; color: #e74c3c; }
        
        .contenedor-panel { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .contenedor-panel h2 { color: #e6edf3; font-size: 1.8rem; margin-bottom: 24px; border-bottom: 2px solid #30363d; padding-bottom: 12px; }
        .contenedor-panel h2 i { color: #c9a84c; margin-right: 10px; }
        
        /* Estadísticas */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 30px; }
        .stat-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 20px; text-align: center; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
        .stat-card .icono { font-size: 2rem; margin-bottom: 8px; }
        .stat-card .numero { font-size: 2.5rem; font-weight: 700; color: #e6edf3; }
        .stat-card .etiqueta { color: #8b949e; font-size: 0.85rem; margin-top: 4px; }
        .stat-card.primary .icono { color: #58a6ff; } .stat-card.primary { border-top: 3px solid #58a6ff; }
        .stat-card.success .icono { color: #3fb950; } .stat-card.success { border-top: 3px solid #3fb950; }
        .stat-card.warning .icono { color: #d29922; } .stat-card.warning { border-top: 3px solid #d29922; }
        .stat-card.danger .icono { color: #f85149; } .stat-card.danger { border-top: 3px solid #f85149; }
        .stat-card.info .icono { color: #58a6ff; } .stat-card.info { border-top: 3px solid #58a6ff; }
        
        /* Secciones del dashboard */
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px; }
        .dashboard-section { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 20px; }
        .dashboard-section.full { grid-column: 1 / -1; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #30363d; padding-bottom: 12px; }
        .section-header h3 { color: #c9a84c; font-size: 1.1rem; margin: 0; }
        .section-header h3 i { margin-right: 8px; }
        .badge-contador { background: rgba(201,168,76,0.15); color: #c9a84c; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; }
        
        /* Tablas del dashboard */
        .tabla-dashboard { width: 100%; border-collapse: collapse; }
        .tabla-dashboard th { text-align: left; padding: 8px 12px; color: #8b949e; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #30363d; }
        .tabla-dashboard td { padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 0.9rem; }
        .tabla-dashboard tbody tr:hover { background: rgba(255,255,255,0.02); }
        .tabla-dashboard .empty-message { text-align: center; padding: 30px; color: #8b949e; }
        .tabla-dashboard .empty-message i { font-size: 2rem; display: block; margin-bottom: 10px; color: #30363d; }
        
        /* Avatar usuario */
        .usuario-info { display: flex; align-items: center; gap: 12px; }
        .usuario-avatar { width: 32px; height: 32px; border-radius: 50%; background: #c9a84c; color: #0d1117; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; }
        .usuario-nombre { font-weight: 600; color: #e6edf3; }
        .usuario-username { font-size: 0.75rem; color: #8b949e; }
        
        /* Badges */
        .badge-rol-table { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-table-cliente { background: rgba(88,166,255,0.15); color: #58a6ff; }
        .badge-table-empleado { background: rgba(46,160,67,0.15); color: #3fb950; }
        .badge-table-administrador { background: rgba(201,168,76,0.15); color: #c9a84c; }
        
        .badge-estado { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-activo { background: rgba(46,160,67,0.15); color: #3fb950; }
        .badge-inactivo { background: rgba(248,81,73,0.15); color: #f85149; }
        
        .badge-cita { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-cita-pendiente { background: rgba(210,153,34,0.15); color: #d29922; }
        .badge-cita-confirmada { background: rgba(46,160,67,0.15); color: #3fb950; }
        .badge-cita-cancelada { background: rgba(248,81,73,0.15); color: #f85149; }
        .badge-cita-completada { background: rgba(88,166,255,0.15); color: #58a6ff; }
        
        .badge-stock { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-stock-danger { background: rgba(248,81,73,0.15); color: #f85149; }
        .badge-stock-warning { background: rgba(210,153,34,0.15); color: #d29922; }
        
        .btn-outline-sm { padding: 6px 16px; border-radius: 8px; font-size: 0.8rem; text-decoration: none; transition: all 0.3s; background: transparent; border: 1px solid #30363d; color: #8b949e; }
        .btn-outline-sm:hover { border-color: #c9a84c; color: #c9a84c; }
        .btn-outline-sm i { margin-right: 4px; }
        
        /* Gráfico de roles */
        .rol-chart { display: flex; gap: 16px; flex-wrap: wrap; }
        .rol-item { display: flex; align-items: center; gap: 8px; }
        .rol-color { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
        .rol-color-admin { background: #c9a84c; }
        .rol-color-empleado { background: #3fb950; }
        .rol-color-cliente { background: #58a6ff; }
        .rol-item span { color: #e6edf3; font-size: 0.85rem; }
        .rol-item strong { color: #c9a84c; margin-left: auto; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .header-contenido { flex-wrap: wrap; gap: 10px; }
            .nav-header { flex-wrap: wrap; gap: 8px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
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
            <span class="usuario-info" style="color: #8b949e; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-user-circle" style="color: #c9a84c;"></i>
                <?= htmlspecialchars($usuario) ?>
                <span style="padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; background: #c9a84c; color: #0d1117;">
                    <?= ucfirst($rol) ?>
                </span>
            </span>
            <a href="home.php" class="btn-outline-sm"><i class="fas fa-arrow-left"></i> Panel</a>
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2><i class="fas fa-chart-bar"></i> Dashboard de Administración</h2>

    <!-- Tarjetas de estadísticas -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="icono"><i class="fas fa-users"></i></div>
            <div class="numero"><?= $total_usuarios ?></div>
            <div class="etiqueta">Total Usuarios</div>
        </div>
        <div class="stat-card success">
            <div class="icono"><i class="fas fa-calendar-check"></i></div>
            <div class="numero"><?= $total_citas ?></div>
            <div class="etiqueta">Total Citas</div>
        </div>
        <div class="stat-card warning">
            <div class="icono"><i class="fas fa-calendar-day"></i></div>
            <div class="numero"><?= $citas_hoy ?></div>
            <div class="etiqueta">Citas de Hoy</div>
        </div>
        <div class="stat-card info">
            <div class="icono"><i class="fas fa-box"></i></div>
            <div class="numero"><?= $total_productos ?></div>
            <div class="etiqueta">Productos en Stock</div>
        </div>
        <div class="stat-card success">
            <div class="icono"><i class="fas fa-cut"></i></div>
            <div class="numero"><?= $total_servicios ?></div>
            <div class="etiqueta">Servicios</div>
        </div>
        <div class="stat-card primary">
            <div class="icono"><i class="fas fa-user-friends"></i></div>
            <div class="numero"><?= $total_clientes ?></div>
            <div class="etiqueta">Clientes Registrados</div>
        </div>
    </div>

    <!-- Grid de secciones -->
    <div class="dashboard-grid">

        <!-- Distribución de Roles -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-user-tag"></i> Distribución de Roles</h3>
            </div>
            <div class="rol-chart">
                <?php 
                $colores = ['administrador' => 'admin', 'empleado' => 'empleado', 'cliente' => 'cliente'];
                foreach ($usuarios_por_rol as $rol_data):
                ?>
                <div class="rol-item">
                    <span class="rol-color rol-color-<?= $colores[$rol_data['rol']] ?? 'cliente' ?>"></span>
                    <span><?= ucfirst($rol_data['rol']) ?></span>
                    <strong><?= $rol_data['cantidad'] ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Citas por Estado -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-chart-pie"></i> Citas por Estado</h3>
            </div>
            <?php if (empty($citas_por_estado)): ?>
                <p style="color: #8b949e; text-align: center; padding: 10px;">No hay citas registradas.</p>
            <?php else: ?>
                <?php foreach ($citas_por_estado as $estado): ?>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.04);">
                    <span class="badge-cita badge-cita-<?= strtolower($estado['estado_cita']) ?>">
                        <?= ucfirst($estado['estado_cita']) ?>
                    </span>
                    <strong style="color: #c9a84c;"><?= $estado['cantidad'] ?></strong>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Últimos Usuarios -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-user-plus"></i> Últimos Usuarios</h3>
                <span class="badge-contador"><i class="fas fa-users"></i> <?= count($ultimos_usuarios) ?> recientes</span>
            </div>
            <?php if (empty($ultimos_usuarios)): ?>
                <div class="empty-message">
                    <i class="fas fa-inbox"></i>
                    No hay usuarios registrados aún.
                </div>
            <?php else: ?>
                <table class="tabla-dashboard">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimos_usuarios as $u): 
                            $iniciales = '';
                            if (!empty($u['nombre']) && !empty($u['apellido'])) {
                                $iniciales = strtoupper(substr($u['nombre'], 0, 1) . substr($u['apellido'], 0, 1));
                            } else {
                                $iniciales = strtoupper(substr($u['usuario'] ?? 'U', 0, 2));
                            }
                            
                            $badge_class = '';
                            switch($u['rol']) {
                                case 'cliente': $badge_class = 'badge-table-cliente'; break;
                                case 'empleado': $badge_class = 'badge-table-empleado'; break;
                                case 'administrador': $badge_class = 'badge-table-administrador'; break;
                                default: $badge_class = 'badge-table-cliente';
                            }
                            
                            $estado_class = ($u['estado'] ?? 'activo') === 'activo' ? 'badge-activo' : 'badge-inactivo';
                            $icono_rol = $u['rol'] === 'cliente' ? '<i class="fas fa-user"></i>' : ($u['rol'] === 'empleado' ? '<i class="fas fa-user-tie"></i>' : '<i class="fas fa-crown"></i>');
                        ?>
                        <tr>
                            <td>
                                <div class="usuario-info">
                                    <div class="usuario-avatar"><?= $iniciales ?></div>
                                    <div>
                                        <div class="usuario-nombre">
                                            <?= htmlspecialchars($u['nombre'] ?? $u['usuario'] ?? 'Usuario') ?>
                                            <?= htmlspecialchars($u['apellido'] ?? '') ?>
                                        </div>
                                        <div class="usuario-username">@<?= htmlspecialchars($u['usuario'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-rol-table <?= $badge_class ?>">
                                    <?= $icono_rol . ' ' . ucfirst($u['rol'] ?? 'cliente') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-estado <?= $estado_class ?>">
                                    <?= ucfirst($u['estado'] ?? 'activo') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top: 12px; text-align: right;">
                    <a href="abm.php?tab=usuarios" class="btn-outline-sm" style="border-color: #c9a84c; color: #c9a84c;">
                        Ver todos <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Últimas Citas -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-clock"></i> Últimas Citas</h3>
                <span class="badge-contador"><i class="fas fa-calendar"></i> <?= count($ultimas_citas) ?> recientes</span>
            </div>
            <?php if (empty($ultimas_citas)): ?>
                <div class="empty-message">
                    <i class="fas fa-calendar-times"></i>
                    No hay citas registradas aún.
                </div>
            <?php else: ?>
                <table class="tabla-dashboard">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimas_citas as $c): 
                            $estado_class = 'badge-cita-' . strtolower($c['estado_cita'] ?? 'pendiente');
                            $nombre_cliente = $c['cliente_nombre'] ?? 'Cliente';
                            $apellido_cliente = $c['cliente_apellido'] ?? '';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($nombre_cliente . ' ' . $apellido_cliente) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($c['fecha_hora'])) ?></td>
                            <td>
                                <span class="badge-cita <?= $estado_class ?>">
                                    <?= ucfirst($c['estado_cita'] ?? 'pendiente') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top: 12px; text-align: right;">
                    <a href="panel_admin_citas.php" class="btn-outline-sm" style="border-color: #c9a84c; color: #c9a84c;">
                        Ver todas <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stock Bajo -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #d29922;"></i> Stock Bajo</h3>
                <span class="badge-contador" style="background: rgba(210,153,34,0.15); color: #d29922;">
                    <i class="fas fa-box"></i> <?= count($stock_bajo) ?> productos
                </span>
            </div>
            <?php if (empty($stock_bajo)): ?>
                <div class="empty-message" style="color: #3fb950;">
                    <i class="fas fa-check-circle"></i>
                    No hay productos con stock bajo.
                </div>
            <?php else: ?>
                <table class="tabla-dashboard">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Stock</th>
                            <th>Mínimo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_bajo as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nombre_producto']) ?></td>
                            <td><span class="badge-stock badge-stock-warning"><?= $p['stock_actual'] ?></span></td>
                            <td><?= $p['stock_minimo'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top: 12px; text-align: right;">
                    <a href="admin_productos.php" class="btn-outline-sm" style="border-color: #c9a84c; color: #c9a84c;">
                        Gestionar stock <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sin Stock -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3><i class="fas fa-times-circle" style="color: #f85149;"></i> Productos Agotados</h3>
                <span class="badge-contador" style="background: rgba(248,81,73,0.15); color: #f85149;">
                    <i class="fas fa-box"></i> <?= count($sin_stock) ?> agotados
                </span>
            </div>
            <?php if (empty($sin_stock)): ?>
                <div class="empty-message" style="color: #3fb950;">
                    <i class="fas fa-check-circle"></i>
                    No hay productos agotados.
                </div>
            <?php else: ?>
                <table class="tabla-dashboard">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sin_stock as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nombre_producto']) ?></td>
                            <td><span class="badge-stock badge-stock-danger">0</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top: 12px; text-align: right;">
                    <a href="admin_productos.php" class="btn-outline-sm" style="border-color: #c9a84c; color: #c9a84c;">
                        Gestionar stock <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>

</main>

<footer class="footer-panel" style="text-align: center; padding: 30px 20px 20px; border-top: 1px solid #30363d; margin-top: 40px; color: #8b949e;">
    <p>&copy; <?= date('Y') ?> <span style="color: #c9a84c; font-weight: 600;">SalonHub</span> — Dashboard</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

</body>
</html>