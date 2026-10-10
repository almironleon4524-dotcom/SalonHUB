<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bandeja de Entrada de Notificaciones

session_start();
require_once __DIR__ . '/permisos_helper.php';
require_once __DIR__ . '/notificaciones_helper.php';

// Requerir login
requerir_login();

$logueado = esta_logueado();
$rol = $_SESSION['rol'] ?? 'invitado';
$usuario = $_SESSION['usuario'] ?? 'Invitado';
$id_usuario = $_SESSION['id_usuario'];

// Cargar permisos
$mis_permisos = obtener_todos_permisos((int)$id_usuario);

$pdo = conectar();

// Limpieza automática (1 vez por día)
ejecutar_limpieza_notificaciones();

// Parámetros de filtros
$busqueda = $_GET['busqueda'] ?? '';
$filtro_prioridad = $_GET['filtro_prioridad'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';
$filtro_modulo = $_GET['filtro_modulo'] ?? '';
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 15;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Construir WHERE
$where = "WHERE (id_usuario = :id_usuario OR (id_usuario IS NULL AND rol_destino = :rol))";
$params = [':id_usuario' => $id_usuario, ':rol' => $rol];

if (!empty($busqueda)) {
    $where .= " AND (titulo LIKE :busqueda OR mensaje LIKE :busqueda2)";
    $params[':busqueda'] = "%$busqueda%";
    $params[':busqueda2'] = "%$busqueda%";
}

if (!empty($filtro_prioridad)) {
    $where .= " AND prioridad = :prioridad";
    $params[':prioridad'] = $filtro_prioridad;
}

if ($filtro_estado === 'no_leidas') {
    $where .= " AND leido = 0";
} elseif ($filtro_estado === 'leidas') {
    $where .= " AND leido = 1";
}

if (!empty($filtro_modulo)) {
    $where .= " AND modulo = :modulo";
    $params[':modulo'] = $filtro_modulo;
}

// Contar total
$sql_count = "SELECT COUNT(*) FROM notificaciones $where";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener registros
$sql = "SELECT * FROM notificaciones $where ORDER BY created_at DESC LIMIT $registros_por_pagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stmt_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN leido = 0 THEN 1 ELSE 0 END) as no_leidas,
        SUM(CASE WHEN prioridad = 'critica' AND leido = 0 THEN 1 ELSE 0 END) as criticas,
        SUM(CASE WHEN prioridad = 'alta' AND leido = 0 THEN 1 ELSE 0 END) as altas
    FROM notificaciones 
    WHERE (id_usuario = ? OR (id_usuario IS NULL AND rol_destino = ?))
");
$stmt_stats->execute([$id_usuario, $rol]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Obtener módulos únicos para el filtro
$stmt_modulos = $pdo->query("SELECT DISTINCT modulo FROM notificaciones WHERE modulo IS NOT NULL ORDER BY modulo");
$modulos_disponibles = $stmt_modulos->fetchAll(PDO::FETCH_COLUMN);

// Colores por prioridad
function color_prioridad($p) {
    $colores = [
        'critica' => '#f85149',
        'alta' => '#d29922',
        'media' => '#58a6ff',
        'baja' => '#8b949e'
    ];
    return $colores[$p] ?? '#8b949e';
}

// Iconos por tipo
function icono_tipo($tipo) {
    $iconos = [
        'stock_agotado' => 'fa-times-circle',
        'stock_bajo' => 'fa-exclamation-triangle',
        'stock_repuesto' => 'fa-check-circle',
        'producto_creado' => 'fa-box',
        'producto_editado' => 'fa-box-open',
        'producto_eliminado' => 'fa-box',
        'producto_roto' => 'fa-broken-chain',
        'producto_vencido' => 'fa-calendar-times',
        'producto_por_vencer' => 'fa-clock',
        'nueva_cita' => 'fa-calendar-plus',
        'cita_creada' => 'fa-calendar-plus',
        'cita_confirmada' => 'fa-check-circle',
        'cita_cancelada' => 'fa-times-circle',
        'cita_reagendada' => 'fa-calendar-day',
        'cita_recordatorio' => 'fa-bell',
        'cita_completada' => 'fa-check',
        'no_asistio' => 'fa-user-slash',
        'venta_realizada' => 'fa-shopping-cart',
        'venta_cancelada' => 'fa-ban',
        'pago_registrado' => 'fa-money-bill',
        'pago_pendiente' => 'fa-clock',
        'diferencia_caja' => 'fa-cash-register',
        'cierre_exitoso' => 'fa-check-circle',
        'nuevo_cliente' => 'fa-user-plus',
        'cliente_inactivo' => 'fa-user-clock',
        'cumpleanos' => 'fa-birthday-cake',
        'nuevo_usuario' => 'fa-user-plus',
        'cambio_rol' => 'fa-user-shield',
        'cuenta_bloqueada' => 'fa-user-lock',
        'backup_fallido' => 'fa-database',
        'backup_completado' => 'fa-database',
        'error_sistema' => 'fa-exclamation-triangle',
        'promocion' => 'fa-tag',
        'feriado' => 'fa-calendar-times',
        'cambio_horario' => 'fa-clock'
    ];
    return $iconos[$tipo] ?? 'fa-bell';
}

// Iconos por módulo (colores suaves)
function color_modulo($modulo) {
    $colores = [
        'inventario' => '#3fb950',
        'citas' => '#58a6ff',
        'caja' => '#d29922',
        'ventas' => '#a371f7',
        'clientes' => '#f778ba',
        'usuarios' => '#ff7b72',
        'sistema' => '#8b949e',
        'marketing' => '#ffa657',
        'servicios' => '#79c0ff'
    ];
    return $colores[$modulo] ?? '#8b949e';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Mis Notificaciones</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #0d1117; color: #e6edf3; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; }
        
        .header-panel { background: #161b22; border-bottom: 1px solid #30363d; padding: 0 20px; position: sticky; top: 0; z-index: 100; }
        .header-contenido { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; padding: 12px 0; }
        .logo-header a { font-size: 1.5rem; font-weight: 700; color: #c9a84c; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .logo-header a i { font-size: 1.8rem; }
        .nav-header { display: flex; align-items: center; gap: 16px; }
        .usuario-info { color: #8b949e; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .usuario-info i { font-size: 1.1rem; color: #c9a84c; }
        .badge-rol { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; }
        .badge-admin { background: #c9a84c; color: #0d1117; }
        .badge-empleado { background: #0f5c45; color: white; }
        .badge-cliente { background: #4a5568; color: white; }
        .btn-outline-sm { padding: 6px 16px; border-radius: 8px; font-size: 0.8rem; text-decoration: none; transition: all 0.3s; background: transparent; border: 1px solid #30363d; color: #8b949e; }
        .btn-outline-sm:hover { border-color: #c9a84c; color: #c9a84c; }
        .btn-danger-outline:hover { border-color: #e74c3c; color: #e74c3c; }
        
        .contenedor-panel { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .contenedor-panel h2 { color: #e6edf3; font-size: 1.8rem; margin-bottom: 24px; border-bottom: 2px solid #30363d; padding-bottom: 12px; }
        .contenedor-panel h2 i { color: #c9a84c; margin-right: 10px; }
        
        /* Estadísticas */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 16px 20px; text-align: center; transition: all 0.3s ease; }
        .stat-card .icono { font-size: 1.5rem; margin-bottom: 4px; }
        .stat-card .numero { font-size: 2rem; font-weight: 700; color: #e6edf3; }
        .stat-card .etiqueta { color: #8b949e; font-size: 0.8rem; }
        .stat-card.primary .icono { color: #58a6ff; } .stat-card.primary { border-top: 3px solid #58a6ff; }
        .stat-card.danger .icono { color: #f85149; } .stat-card.danger { border-top: 3px solid #f85149; }
        .stat-card.warning .icono { color: #d29922; } .stat-card.warning { border-top: 3px solid #d29922; }
        .stat-card.info .icono { color: #a371f7; } .stat-card.info { border-top: 3px solid #a371f7; }
        
        /* Filtros */
        .filtros-container { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
        .filtros-container .row { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
        .filtros-container .col { flex: 1; min-width: 160px; }
        .filtros-container label { color: #8b949e; font-size: 0.8rem; display: block; margin-bottom: 4px; }
        .filtros-container input, .filtros-container select { width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #30363d; background: #0d1117; color: #e6edf3; font-size: 0.9rem; transition: all 0.3s; }
        .filtros-container input:focus, .filtros-container select:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        .btn-filtro { padding: 8px 16px; border-radius: 8px; border: 1px solid #30363d; background: transparent; color: #8b949e; cursor: pointer; transition: all 0.3s; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-filtro:hover { border-color: #c9a84c; color: #c9a84c; }
        .btn-filtro.primary { background: #c9a84c; color: #0d1117; border-color: #c9a84c; font-weight: 600; }
        .btn-filtro.primary:hover { background: #b8943a; }
        
        /* Botones de acción */
        .acciones-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .contador-resultados { color: #8b949e; font-size: 0.9rem; }
        .btn-accion-top { padding: 8px 16px; border-radius: 8px; border: 1px solid #30363d; background: transparent; color: #8b949e; cursor: pointer; transition: all 0.3s; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-accion-top:hover { border-color: #c9a84c; color: #c9a84c; }
        .btn-accion-top.peligro:hover { border-color: #f85149; color: #f85149; }
        
        /* Cards de notificaciones */
        .lista-notificaciones { display: flex; flex-direction: column; gap: 12px; }
        
        .notif-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 16px 20px;
            transition: all 0.3s ease;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            position: relative;
        }
        
        .notif-card:hover {
            border-color: #c9a84c;
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .notif-card.no-leida {
            border-left: 4px solid #c9a84c;
            background: rgba(201, 168, 76, 0.03);
        }
        
        .notif-card.prioridad-critica { border-left: 4px solid #f85149; }
        .notif-card.prioridad-alta { border-left: 4px solid #d29922; }
        .notif-card.prioridad-media { border-left: 4px solid #58a6ff; }
        .notif-card.prioridad-baja { border-left: 4px solid #6b7280; }
        
        .notif-icono {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .notif-contenido { flex: 1; min-width: 0; }
        
        .notif-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }
        
        .notif-titulo {
            color: #c9a84c;
            font-size: 0.95rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .notif-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .notif-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .badge-modulo {
            background: rgba(255,255,255,0.05);
            color: #8b949e;
            border: 1px solid #30363d;
        }
        
        .badge-prioridad-critica { background: rgba(248,81,73,0.15); color: #f85149; }
        .badge-prioridad-alta { background: rgba(210,153,34,0.15); color: #d29922; }
        .badge-prioridad-media { background: rgba(88,166,255,0.15); color: #58a6ff; }
        .badge-prioridad-baja { background: rgba(107,114,128,0.15); color: #8b949e; }
        
        .badge-nueva {
            background: #c9a84c;
            color: #0d1117;
            font-weight: 700;
        }
        
        .notif-mensaje {
            color: #e6edf3;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 8px;
        }
        
        .notif-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .notif-fecha { color: #8b949e; font-size: 0.75rem; display: flex; align-items: center; gap: 6px; }
        
        .notif-acciones { display: flex; gap: 6px; }
        
        .btn-notif {
            padding: 4px 12px;
            border-radius: 6px;
            border: 1px solid #30363d;
            background: transparent;
            color: #8b949e;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-notif:hover { border-color: #c9a84c; color: #c9a84c; }
        .btn-notif.ir { border-color: rgba(201,168,76,0.3); background: rgba(201,168,76,0.08); color: #c9a84c; }
        .btn-notif.ir:hover { background: #c9a84c; color: #0d1117; }
        
        /* Sin notificaciones */
        .sin-notificaciones {
            text-align: center;
            padding: 60px 20px;
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            color: #8b949e;
        }
        
        .sin-notificaciones i {
            font-size: 4rem;
            color: #30363d;
            margin-bottom: 16px;
            display: block;
        }
        
        .sin-notificaciones h3 { color: #e6edf3; margin-bottom: 8px; }
        
        /* Paginación */
        .paginacion { display: flex; justify-content: center; gap: 6px; margin-top: 24px; flex-wrap: wrap; }
        .paginacion .btn-pagina { padding: 6px 14px; border-radius: 6px; border: 1px solid #30363d; background: #161b22; color: #8b949e; cursor: pointer; transition: all 0.3s; text-decoration: none; font-size: 0.85rem; }
        .paginacion .btn-pagina:hover { border-color: #c9a84c; color: #c9a84c; }
        .paginacion .btn-pagina.activa { background: #c9a84c; color: #0d1117; border-color: #c9a84c; }
        .paginacion .btn-pagina.deshabilitada { opacity: 0.3; cursor: not-allowed; }
        .info-paginacion { text-align: center; color: #8b949e; font-size: 0.8rem; margin-top: 10px; }
        
        /* Footer */
        .footer-panel { text-align: center; padding: 30px 20px 20px; border-top: 1px solid #30363d; margin-top: 40px; color: #8b949e; }
        .footer-panel span { color: #c9a84c; font-weight: 600; }
        
        @media (max-width: 768px) {
            .filtros-container .row { flex-direction: column; }
            .filtros-container .col { min-width: auto; width: 100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .header-contenido { flex-wrap: wrap; gap: 10px; }
            .nav-header { flex-wrap: wrap; gap: 8px; }
            .notif-card { padding: 12px 16px; gap: 12px; }
            .notif-icono { width: 36px; height: 36px; font-size: 1rem; }
            .acciones-bar { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 480px) { 
            .stats-grid { grid-template-columns: 1fr; }
            .notif-header { flex-direction: column; }
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
                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($usuario) ?>
                <span class="badge-rol badge-<?= $rol ?>"><?= ucfirst($rol) ?></span>
            </span>
            <a href="home.php" class="btn-outline-sm"><i class="fas fa-arrow-left"></i> Inicio</a>
            <a href="preferencias_notificaciones.php" class="btn-outline-sm"><i class="fas fa-cog"></i> Preferencias</a>
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2><i class="fas fa-bell"></i> Mis Notificaciones</h2>
    
    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="icono"><i class="fas fa-bell"></i></div>
            <div class="numero"><?= number_format($stats['total']) ?></div>
            <div class="etiqueta">Total</div>
        </div>
        <div class="stat-card info">
            <div class="icono"><i class="fas fa-envelope"></i></div>
            <div class="numero"><?= number_format($stats['no_leidas']) ?></div>
            <div class="etiqueta">No Leídas</div>
        </div>
        <div class="stat-card danger">
            <div class="icono"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="numero"><?= number_format($stats['criticas']) ?></div>
            <div class="etiqueta">Críticas</div>
        </div>
        <div class="stat-card warning">
            <div class="icono"><i class="fas fa-bolt"></i></div>
            <div class="numero"><?= number_format($stats['altas']) ?></div>
            <div class="etiqueta">Altas</div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="filtros-container">
        <form method="GET" action="">
            <div class="row">
                <div class="col">
                    <label><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" name="busqueda" placeholder="Título o mensaje..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="col">
                    <label><i class="fas fa-exclamation-triangle"></i> Prioridad</label>
                    <select name="filtro_prioridad">
                        <option value="">Todas</option>
                        <option value="critica" <?= $filtro_prioridad === 'critica' ? 'selected' : '' ?>>Crítica</option>
                        <option value="alta" <?= $filtro_prioridad === 'alta' ? 'selected' : '' ?>>Alta</option>
                        <option value="media" <?= $filtro_prioridad === 'media' ? 'selected' : '' ?>>Media</option>
                        <option value="baja" <?= $filtro_prioridad === 'baja' ? 'selected' : '' ?>>Baja</option>
                    </select>
                </div>
                <div class="col">
                    <label><i class="fas fa-envelope"></i> Estado</label>
                    <select name="filtro_estado">
                        <option value="">Todas</option>
                        <option value="no_leidas" <?= $filtro_estado === 'no_leidas' ? 'selected' : '' ?>>No leídas</option>
                        <option value="leidas" <?= $filtro_estado === 'leidas' ? 'selected' : '' ?>>Leídas</option>
                    </select>
                </div>
                <div class="col">
                    <label><i class="fas fa-th-large"></i> Módulo</label>
                    <select name="filtro_modulo">
                        <option value="">Todos</option>
                        <?php foreach ($modulos_disponibles as $mod): ?>
                            <option value="<?= htmlspecialchars($mod) ?>" <?= $filtro_modulo === $mod ? 'selected' : '' ?>>
                                <?= ucfirst($mod) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col" style="flex: 0 0 auto;">
                    <label>&nbsp;</label>
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn-filtro primary"><i class="fas fa-filter"></i> Filtrar</button>
                        <a href="notificaciones.php" class="btn-filtro"><i class="fas fa-undo"></i> Limpiar</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Barra de acciones -->
    <div class="acciones-bar">
        <span class="contador-resultados">
            <i class="fas fa-list"></i> <?= count($notificaciones) ?> de <?= number_format($total_registros) ?> notificaciones
            <?php if (!empty($busqueda) || !empty($filtro_prioridad) || !empty($filtro_estado) || !empty($filtro_modulo)): ?>
                <span style="color: #c9a84c;">(filtros aplicados)</span>
            <?php endif; ?>
        </span>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if ($stats['no_leidas'] > 0): ?>
                <button class="btn-accion-top" onclick="marcarTodasLeidas()">
                    <i class="fas fa-check-double"></i> Marcar todas como leídas
                </button>
            <?php endif; ?>
            <a href="preferencias_notificaciones.php" class="btn-accion-top">
                <i class="fas fa-cog"></i> Preferencias
            </a>
        </div>
    </div>
    
    <!-- Lista de notificaciones -->
    <?php if (empty($notificaciones)): ?>
        <div class="sin-notificaciones">
            <i class="fas fa-inbox"></i>
            <h3>No hay notificaciones</h3>
            <p><?= (!empty($busqueda) || !empty($filtro_prioridad) || !empty($filtro_estado) || !empty($filtro_modulo)) ? 'No se encontraron notificaciones con los filtros aplicados.' : 'Cuando tengas novedades van a aparecer acá.' ?></p>
        </div>
    <?php else: ?>
        <div class="lista-notificaciones">
            <?php foreach ($notificaciones as $notif): 
                $color = color_prioridad($notif['prioridad']);
                $color_mod = color_modulo($notif['modulo']);
                $clase_no_leida = $notif['leido'] == 0 ? 'no-leida' : '';
                $clase_prioridad = 'prioridad-' . $notif['prioridad'];
            ?>
                <div class="notif-card <?= $clase_no_leida ?> <?= $clase_prioridad ?>" id="notif-<?= $notif['id_notificacion'] ?>">
                    <div class="notif-icono" style="background: <?= $color_mod ?>20; color: <?= $color_mod ?>;">
                        <i class="fas <?= icono_tipo($notif['tipo']) ?>"></i>
                    </div>
                    <div class="notif-contenido">
                        <div class="notif-header">
                            <div class="notif-titulo"><?= htmlspecialchars($notif['titulo'] ?? 'Notificación') ?></div>
                            <div class="notif-badges">
                                <?php if ($notif['leido'] == 0): ?>
                                    <span class="notif-badge badge-nueva">Nueva</span>
                                <?php endif; ?>
                                <span class="notif-badge badge-prioridad-<?= $notif['prioridad'] ?>">
                                    <?= ucfirst($notif['prioridad']) ?>
                                </span>
                                <?php if ($notif['modulo']): ?>
                                    <span class="notif-badge badge-modulo"><?= ucfirst($notif['modulo']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="notif-mensaje"><?= htmlspecialchars($notif['mensaje']) ?></div>
                        <div class="notif-footer">
                            <div class="notif-fecha">
                                <i class="fas fa-clock"></i>
                                <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                            </div>
                            <div class="notif-acciones">
                                <?php if ($notif['leido'] == 0): ?>
                                    <button class="btn-notif" onclick="marcarLeida(<?= $notif['id_notificacion'] ?>)">
                                        <i class="fas fa-check"></i> Marcar como leída
                                    </button>
                                <?php endif; ?>
                                <?php if (!empty($notif['url_accion'])): ?>
                                    <a href="<?= htmlspecialchars($notif['url_accion']) ?>" class="btn-notif ir" onclick="marcarLeidaSilencioso(<?= $notif['id_notificacion'] ?>)">
                                        <i class="fas fa-arrow-right"></i> Ver
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="paginacion">
            <?php if ($pagina_actual > 1): ?>
                <a href="?pagina=<?= $pagina_actual - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_prioridad=<?= urlencode($filtro_prioridad) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>&filtro_modulo=<?= urlencode($filtro_modulo) ?>" class="btn-pagina">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="btn-pagina deshabilitada"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>
            
            <?php
            $rango = 3;
            $inicio = max(1, $pagina_actual - $rango);
            $fin = min($total_paginas, $pagina_actual + $rango);
            
            if ($inicio > 1) {
                echo '<a href="?pagina=1&busqueda=' . urlencode($busqueda) . '&filtro_prioridad=' . urlencode($filtro_prioridad) . '&filtro_estado=' . urlencode($filtro_estado) . '&filtro_modulo=' . urlencode($filtro_modulo) . '" class="btn-pagina">1</a>';
                if ($inicio > 2) {
                    echo '<span class="btn-pagina deshabilitada">…</span>';
                }
            }
            
            for ($i = $inicio; $i <= $fin; $i++): ?>
                <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_prioridad=<?= urlencode($filtro_prioridad) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>&filtro_modulo=<?= urlencode($filtro_modulo) ?>" class="btn-pagina <?= $i === $pagina_actual ? 'activa' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor;
            
            if ($fin < $total_paginas) {
                if ($fin < $total_paginas - 1) {
                    echo '<span class="btn-pagina deshabilitada">…</span>';
                }
                echo '<a href="?pagina=' . $total_paginas . '&busqueda=' . urlencode($busqueda) . '&filtro_prioridad=' . urlencode($filtro_prioridad) . '&filtro_estado=' . urlencode($filtro_estado) . '&filtro_modulo=' . urlencode($filtro_modulo) . '" class="btn-pagina">' . $total_paginas . '</a>';
            }
            ?>
            
            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="?pagina=<?= $pagina_actual + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_prioridad=<?= urlencode($filtro_prioridad) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>&filtro_modulo=<?= urlencode($filtro_modulo) ?>" class="btn-pagina">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="btn-pagina deshabilitada"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <div class="info-paginacion">
            Página <?= $pagina_actual ?> de <?= $total_paginas ?> (<?= number_format($total_registros) ?> notificaciones en total)
        </div>
        <?php endif; ?>
    <?php endif; ?>
    
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Mis Notificaciones</p>
</footer>

<script>
// Marcar una notificación como leída
function marcarLeida(id) {
    fetch('ajax/marcar_notificacion_leida.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_notificacion=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('notif-' + id);
            if (card) {
                card.classList.remove('no-leida', 'prioridad-critica', 'prioridad-alta', 'prioridad-media', 'prioridad-baja');
                // Remover badge "Nueva"
                const badgeNueva = card.querySelector('.badge-nueva');
                if (badgeNueva) badgeNueva.remove();
                // Remover botón de marcar como leída
                const btnMarcar = card.querySelector('.btn-notif:not(.ir)');
                if (btnMarcar) btnMarcar.remove();
                // Recargar la página para actualizar contadores
                setTimeout(() => location.reload(), 300);
            }
        }
    })
    .catch(err => console.error('Error:', err));
}

// Marcar silenciosamente (sin recargar, para ir a la URL)
function marcarLeidaSilencioso(id) {
    fetch('ajax/marcar_notificacion_leida.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_notificacion=' + id
    }).catch(err => console.error('Error:', err));
}

// Marcar todas como leídas
function marcarTodasLeidas() {
    if (!confirm('¿Marcar todas las notificaciones como leídas?')) return;
    
    fetch('ajax/marcar_todas_leidas.php', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(err => console.error('Error:', err));
}
</script>

</body>
</html>