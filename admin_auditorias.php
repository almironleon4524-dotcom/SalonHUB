<?php
// Panel de Auditorías

session_start();
require_once 'conexion.php';
require_once 'permisos_helper.php';

// Seguridad
requerir_permiso('gestion_configuracion');

$pdo = conectar();
$mensaje = $_SESSION['mensaje'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['error']);

// Filtros
$filtro_usuario = $_GET['filtro_usuario'] ?? '';
$filtro_tabla = $_GET['filtro_tabla'] ?? '';
$filtro_accion = $_GET['filtro_accion'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 20;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener datos para los filtros
$usuarios = $pdo->query("SELECT id_usuario, usuario FROM usuario ORDER BY usuario")->fetchAll(PDO::FETCH_ASSOC);
$tablas = $pdo->query("SELECT DISTINCT tabla_afectada FROM auditorias ORDER BY tabla_afectada")->fetchAll(PDO::FETCH_COLUMN);
$acciones = $pdo->query("SELECT DISTINCT accion FROM auditorias ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);

// Construir consulta con filtros
$where = "WHERE 1=1";
$params = [];

if (!empty($filtro_usuario)) {
    $where .= " AND a.id_usuario = :filtro_usuario";
    $params[':filtro_usuario'] = $filtro_usuario;
}

if (!empty($filtro_tabla)) {
    $where .= " AND a.tabla_afectada = :filtro_tabla";
    $params[':filtro_tabla'] = $filtro_tabla;
}

if (!empty($filtro_accion)) {
    $where .= " AND a.accion = :filtro_accion";
    $params[':filtro_accion'] = $filtro_accion;
}

if (!empty($filtro_fecha_desde)) {
    $where .= " AND DATE(a.fecha) >= :fecha_desde";
    $params[':fecha_desde'] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $where .= " AND DATE(a.fecha) <= :fecha_hasta";
    $params[':fecha_hasta'] = $filtro_fecha_hasta;
}

if (!empty($busqueda)) {
    $where .= " AND (a.tabla_afectada LIKE :busqueda OR a.accion LIKE :busqueda2 OR u.usuario LIKE :busqueda3)";
    $params[':busqueda'] = "%$busqueda%";
    $params[':busqueda2'] = "%$busqueda%";
    $params[':busqueda3'] = "%$busqueda%";
}

// Contar total
$sql_count = "SELECT COUNT(*) FROM auditorias a LEFT JOIN usuario u ON a.id_usuario = u.id_usuario $where";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener registros
$sql = "SELECT a.*, u.usuario, u.email 
        FROM auditorias a 
        LEFT JOIN usuario u ON a.id_usuario = u.id_usuario 
        $where 
        ORDER BY a.fecha DESC 
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(1, $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats_total = $pdo->query("SELECT COUNT(*) FROM auditorias")->fetchColumn();
$stats_hoy = $pdo->query("SELECT COUNT(*) FROM auditorias WHERE DATE(fecha) = CURDATE()")->fetchColumn();
$stats_semana = $pdo->query("SELECT COUNT(*) FROM auditorias WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$stats_usuarios = $pdo->query("SELECT COUNT(DISTINCT id_usuario) FROM auditorias")->fetchColumn();

// Helper para iconos según acción
function icono_accion($accion) {
    switch ($accion) {
        case 'crear': return 'fa-plus-circle';
        case 'editar': return 'fa-pencil-alt';
        case 'eliminar': return 'fa-trash';
        case 'login': return 'fa-sign-in-alt';
        case 'logout': return 'fa-sign-out-alt';
        default: return 'fa-circle';
    }
}

function color_accion($accion) {
    switch ($accion) {
        case 'crear': return '#3fb950';
        case 'editar': return '#d29922';
        case 'eliminar': return '#f85149';
        case 'login': return '#58a6ff';
        case 'logout': return '#8b949e';
        default: return '#8b949e';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Auditorías</title>
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
        
        .alerta { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alerta-exito { background: rgba(46,160,67,0.15); border: 1px solid #238636; color: #3fb950; }
        .alerta-error { background: rgba(248,81,73,0.15); border: 1px solid #da3633; color: #f85149; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 16px 20px; text-align: center; transition: all 0.3s ease; }
        .stat-card .icono { font-size: 1.5rem; margin-bottom: 4px; }
        .stat-card .numero { font-size: 2rem; font-weight: 700; color: #e6edf3; }
        .stat-card .etiqueta { color: #8b949e; font-size: 0.8rem; }
        .stat-card.primary .icono { color: #58a6ff; } .stat-card.primary { border-top: 3px solid #58a6ff; }
        .stat-card.success .icono { color: #3fb950; } .stat-card.success { border-top: 3px solid #3fb950; }
        .stat-card.warning .icono { color: #d29922; } .stat-card.warning { border-top: 3px solid #d29922; }
        .stat-card.info .icono { color: #a371f7; } .stat-card.info { border-top: 3px solid #a371f7; }
        
        .filtros-container { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
        .filtros-container .row { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
        .filtros-container .col { flex: 1; min-width: 150px; }
        .filtros-container label { color: #8b949e; font-size: 0.8rem; display: block; margin-bottom: 4px; }
        .filtros-container input, .filtros-container select { width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #30363d; background: #0d1117; color: #e6edf3; font-size: 0.9rem; transition: all 0.3s; }
        .filtros-container input:focus, .filtros-container select:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        .btn-filtro { padding: 8px 16px; border-radius: 8px; border: 1px solid #30363d; background: transparent; color: #8b949e; cursor: pointer; transition: all 0.3s; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; }
        .btn-filtro:hover { border-color: #c9a84c; color: #c9a84c; }
        .btn-filtro.primary { background: #c9a84c; color: #0d1117; border-color: #c9a84c; }
        .btn-filtro.primary:hover { background: #b8943a; }
        
        .tabla-responsive { overflow-x: auto; border-radius: 12px; border: 1px solid #30363d; }
        .tabla-datos { width: 100%; border-collapse: collapse; background: #161b22; }
        .tabla-datos th { padding: 12px 16px; text-align: left; background: rgba(201,168,76,0.05); color: #c9a84c; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #30363d; }
        .tabla-datos td { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #e6edf3; font-size: 0.9rem; vertical-align: middle; }
        .tabla-datos tbody tr:hover { background: rgba(255,255,255,0.02); }
        
        .badge-accion { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-crear { background: rgba(46,160,67,0.15); color: #3fb950; }
        .badge-editar { background: rgba(210,153,34,0.15); color: #d29922; }
        .badge-eliminar { background: rgba(248,81,73,0.15); color: #f85149; }
        .badge-login { background: rgba(88,166,255,0.15); color: #58a6ff; }
        .badge-logout { background: rgba(139,148,158,0.15); color: #8b949e; }
        
        .badge-tabla { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; background: rgba(201,168,76,0.1); color: #c9a84c; border: 1px solid rgba(201,168,76,0.2); }
        
        .btn-ver { padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.75rem; transition: all 0.2s; background: #58a6ff; color: white; display: inline-flex; align-items: center; gap: 4px; }
        .btn-ver:hover { background: #3b8fd4; transform: scale(1.05); }
        
        .usuario-info { display: flex; flex-direction: column; }
        .usuario-info strong { color: #e6edf3; font-size: 0.85rem; }
        .usuario-info small { color: #8b949e; font-size: 0.7rem; }
        
        .fecha-info { display: flex; flex-direction: column; }
        .fecha-info strong { color: #e6edf3; font-size: 0.8rem; }
        .fecha-info small { color: #8b949e; font-size: 0.7rem; }
        
        .paginacion { display: flex; justify-content: center; gap: 6px; margin-top: 24px; flex-wrap: wrap; }
        .paginacion .btn-pagina { padding: 6px 14px; border-radius: 6px; border: 1px solid #30363d; background: #161b22; color: #8b949e; cursor: pointer; transition: all 0.3s; text-decoration: none; font-size: 0.85rem; }
        .paginacion .btn-pagina:hover { border-color: #c9a84c; color: #c9a84c; }
        .paginacion .btn-pagina.activa { background: #c9a84c; color: #0d1117; border-color: #c9a84c; }
        .paginacion .btn-pagina.deshabilitada { opacity: 0.3; cursor: not-allowed; }
        .info-paginacion { text-align: center; color: #8b949e; font-size: 0.8rem; margin-top: 10px; }
        
        /* Modal detalle */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            padding: 20px;
            overflow-y: auto;
        }
        .modal-overlay.visible { display: flex; align-items: flex-start; justify-content: center; padding-top: 40px; }
        .modal-detalle {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            max-width: 800px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #30363d;
            background: rgba(201,168,76,0.05);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .modal-header h3 { color: #c9a84c; font-size: 1.1rem; margin: 0; }
        .modal-close { background: transparent; border: none; color: #8b949e; font-size: 1.5rem; cursor: pointer; padding: 0; width: 32px; height: 32px; border-radius: 6px; transition: all 0.3s; }
        .modal-close:hover { background: rgba(248,81,73,0.15); color: #f85149; }
        .modal-body { padding: 20px; }
        
        .detalle-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .detalle-item { background: rgba(255,255,255,0.03); padding: 10px 14px; border-radius: 8px; border: 1px solid #30363d; }
        .detalle-item label { color: #8b949e; font-size: 0.75rem; display: block; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .detalle-item span { color: #e6edf3; font-size: 0.9rem; word-break: break-all; }
        
        .json-box {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 14px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #e6edf3;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .json-title { color: #c9a84c; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .json-title.datos-antes { color: #f85149; }
        .json-title.datos-despues { color: #3fb950; }
        
        .sin-datos { color: #8b949e; font-style: italic; }
        
        .footer-panel { text-align: center; padding: 30px 20px 20px; border-top: 1px solid #30363d; margin-top: 40px; color: #8b949e; }
        .footer-panel span { color: #c9a84c; font-weight: 600; }
        
        @media (max-width: 768px) {
            .filtros-container .row { flex-direction: column; }
            .filtros-container .col { min-width: auto; width: 100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .header-contenido { flex-wrap: wrap; gap: 10px; }
            .nav-header { flex-wrap: wrap; gap: 8px; }
            .tabla-datos { font-size: 0.8rem; }
            .tabla-datos th, .tabla-datos td { padding: 8px 10px; }
            .detalle-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
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
            <a href="home.php" class="btn-outline-sm"><i class="fas fa-arrow-left"></i> Panel</a>
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2><i class="fas fa-history"></i> Auditorías del Sistema</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="icono"><i class="fas fa-history"></i></div>
            <div class="numero"><?= number_format($stats_total) ?></div>
            <div class="etiqueta">Total Registros</div>
        </div>
        <div class="stat-card success">
            <div class="icono"><i class="fas fa-calendar-day"></i></div>
            <div class="numero"><?= number_format($stats_hoy) ?></div>
            <div class="etiqueta">Hoy</div>
        </div>
        <div class="stat-card warning">
            <div class="icono"><i class="fas fa-calendar-week"></i></div>
            <div class="numero"><?= number_format($stats_semana) ?></div>
            <div class="etiqueta">Últimos 7 días</div>
        </div>
        <div class="stat-card info">
            <div class="icono"><i class="fas fa-users"></i></div>
            <div class="numero"><?= number_format($stats_usuarios) ?></div>
            <div class="etiqueta">Usuarios Activos</div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="filtros-container">
        <form method="GET" action="">
            <div class="row">
                <div class="col">
                    <label><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" name="busqueda" placeholder="Tabla, acción o usuario..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="col">
                    <label><i class="fas fa-user"></i> Usuario</label>
                    <select name="filtro_usuario">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id_usuario'] ?>" <?= $filtro_usuario == $u['id_usuario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['usuario']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label><i class="fas fa-table"></i> Tabla</label>
                    <select name="filtro_tabla">
                        <option value="">Todas</option>
                        <?php foreach ($tablas as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $filtro_tabla === $t ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label><i class="fas fa-bolt"></i> Acción</label>
                    <select name="filtro_accion">
                        <option value="">Todas</option>
                        <?php foreach ($acciones as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>" <?= $filtro_accion === $a ? 'selected' : '' ?>>
                                <?= ucfirst($a) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label><i class="fas fa-calendar"></i> Desde</label>
                    <input type="date" name="fecha_desde" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
                </div>
                <div class="col">
                    <label><i class="fas fa-calendar"></i> Hasta</label>
                    <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
                </div>
                <div class="col" style="flex: 0 0 auto;">
                    <label>&nbsp;</label>
                    <div class="btn-group">
                        <button type="submit" class="btn-filtro primary"><i class="fas fa-filter"></i> Filtrar</button>
                        <a href="admin_auditorias.php" class="btn-filtro"><i class="fas fa-undo"></i> Limpiar</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <div style="margin-bottom: 16px;">
        <span style="color: #8b949e; font-size: 0.9rem;">
            <i class="fas fa-list"></i> <?= count($auditorias) ?> de <?= number_format($total_registros) ?> registros encontrados
            <?php if (!empty($busqueda) || !empty($filtro_usuario) || !empty($filtro_tabla) || !empty($filtro_accion) || !empty($filtro_fecha_desde) || !empty($filtro_fecha_hasta)): ?>
                <span style="color: #c9a84c;">(filtros aplicados)</span>
            <?php endif; ?>
        </span>
    </div>
    
    <!-- Tabla de auditorías -->
    <div class="tabla-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Tabla</th>
                    <th>ID Reg.</th>
                    <th>IP</th>
                    <th style="text-align: center;">Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($auditorias)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #8b949e;">
                            <i class="fas fa-inbox" style="font-size: 3rem; display: block; margin-bottom: 12px; color: #30363d;"></i>
                            No hay registros de auditoría con los filtros aplicados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($auditorias as $aud): ?>
                        <tr>
                            <td style="font-weight: 600; color: #8b949e;">#<?= $aud['id_auditoria'] ?></td>
                            <td>
                                <div class="fecha-info">
                                    <strong><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($aud['fecha'])) ?></strong>
                                    <small><i class="fas fa-clock"></i> <?= date('H:i:s', strtotime($aud['fecha'])) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="usuario-info">
                                    <strong><i class="fas fa-user"></i> <?= htmlspecialchars($aud['usuario'] ?? 'Desconocido') ?></strong>
                                    <small><?= htmlspecialchars($aud['email'] ?? '') ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="badge-accion badge-<?= $aud['accion'] ?>">
                                    <i class="fas <?= icono_accion($aud['accion']) ?>"></i>
                                    <?= ucfirst($aud['accion']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-tabla">
                                    <i class="fas fa-table"></i> <?= htmlspecialchars($aud['tabla_afectada']) ?>
                                </span>
                            </td>
                            <td style="color: #8b949e;"><?= $aud['id_registro'] ?? '-' ?></td>
                            <td style="color: #8b949e; font-size: 0.8rem;"><?= htmlspecialchars($aud['ip'] ?? '-') ?></td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-ver" onclick="verDetalle(<?= $aud['id_auditoria'] ?>)">
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>
    <div class="paginacion">
        <?php if ($pagina_actual > 1): ?>
            <a href="?pagina=<?= $pagina_actual - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_usuario=<?= urlencode($filtro_usuario) ?>&filtro_tabla=<?= urlencode($filtro_tabla) ?>&filtro_accion=<?= urlencode($filtro_accion) ?>&fecha_desde=<?= urlencode($filtro_fecha_desde) ?>&fecha_hasta=<?= urlencode($filtro_fecha_hasta) ?>" class="btn-pagina">
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
            echo '<a href="?pagina=1&busqueda=' . urlencode($busqueda) . '&filtro_usuario=' . urlencode($filtro_usuario) . '&filtro_tabla=' . urlencode($filtro_tabla) . '&filtro_accion=' . urlencode($filtro_accion) . '&fecha_desde=' . urlencode($filtro_fecha_desde) . '&fecha_hasta=' . urlencode($filtro_fecha_hasta) . '" class="btn-pagina">1</a>';
            if ($inicio > 2) {
                echo '<span class="btn-pagina deshabilitada">…</span>';
            }
        }
        
        for ($i = $inicio; $i <= $fin; $i++): ?>
            <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_usuario=<?= urlencode($filtro_usuario) ?>&filtro_tabla=<?= urlencode($filtro_tabla) ?>&filtro_accion=<?= urlencode($filtro_accion) ?>&fecha_desde=<?= urlencode($filtro_fecha_desde) ?>&fecha_hasta=<?= urlencode($filtro_fecha_hasta) ?>" class="btn-pagina <?= $i === $pagina_actual ? 'activa' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor;
        
        if ($fin < $total_paginas) {
            if ($fin < $total_paginas - 1) {
                echo '<span class="btn-pagina deshabilitada">…</span>';
            }
            echo '<a href="?pagina=' . $total_paginas . '&busqueda=' . urlencode($busqueda) . '&filtro_usuario=' . urlencode($filtro_usuario) . '&filtro_tabla=' . urlencode($filtro_tabla) . '&filtro_accion=' . urlencode($filtro_accion) . '&fecha_desde=' . urlencode($filtro_fecha_desde) . '&fecha_hasta=' . urlencode($filtro_fecha_hasta) . '" class="btn-pagina">' . $total_paginas . '</a>';
        }
        ?>
        
        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="?pagina=<?= $pagina_actual + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_usuario=<?= urlencode($filtro_usuario) ?>&filtro_tabla=<?= urlencode($filtro_tabla) ?>&filtro_accion=<?= urlencode($filtro_accion) ?>&fecha_desde=<?= urlencode($filtro_fecha_desde) ?>&fecha_hasta=<?= urlencode($filtro_fecha_hasta) ?>" class="btn-pagina">
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php else: ?>
            <span class="btn-pagina deshabilitada"><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
    </div>
    <div class="info-paginacion">
        Página <?= $pagina_actual ?> de <?= $total_paginas ?> (<?= number_format($total_registros) ?> registros en total)
    </div>
    <?php endif; ?>
    
</main>

<!-- Modal de detalle -->
<div class="modal-overlay" id="modalDetalle" onclick="cerrarModal(event)">
    <div class="modal-detalle" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detalle de Auditoría</h3>
            <button type="button" class="modal-close" onclick="cerrarModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <p style="text-align: center; color: #8b949e;">Cargando...</p>
        </div>
    </div>
</div>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Auditorías del Sistema</p>
</footer>

<script>
// Datos de auditorías para el modal
const auditorias = <?= json_encode($auditorias) ?>;

function verDetalle(id) {
    const aud = auditorias.find(a => a.id_auditoria == id);
    if (!aud) return;
    
    const datosAntes = aud.datos_anteriores ? JSON.stringify(JSON.parse(aud.datos_anteriores), null, 2) : null;
    const datosDespues = aud.datos_nuevos ? JSON.stringify(JSON.parse(aud.datos_nuevos), null, 2) : null;
    
    let html = `
        <div class="detalle-grid">
            <div class="detalle-item">
                <label>ID Auditoría</label>
                <span>#${aud.id_auditoria}</span>
            </div>
            <div class="detalle-item">
                <label>Fecha y Hora</label>
                <span>${new Date(aud.fecha).toLocaleString('es-AR')}</span>
            </div>
            <div class="detalle-item">
                <label>Usuario</label>
                <span>${aud.usuario || 'Desconocido'}</span>
            </div>
            <div class="detalle-item">
                <label>Email</label>
                <span>${aud.email || '-'}</span>
            </div>
            <div class="detalle-item">
                <label>Acción</label>
                <span>${aud.accion.charAt(0).toUpperCase() + aud.accion.slice(1)}</span>
            </div>
            <div class="detalle-item">
                <label>Tabla Afectada</label>
                <span>${aud.tabla_afectada}</span>
            </div>
            <div class="detalle-item">
                <label>ID Registro</label>
                <span>${aud.id_registro || '-'}</span>
            </div>
            <div class="detalle-item">
                <label>IP</label>
                <span>${aud.ip || '-'}</span>
            </div>
        </div>
    `;
    
    // User Agent
    if (aud.user_agent) {
        html += `
            <div class="detalle-item" style="margin-bottom: 20px;">
                <label>Navegador</label>
                <span style="font-size: 0.8rem;">${aud.user_agent}</span>
            </div>
        `;
    }
    
    // Datos antes
    if (datosAntes) {
        html += `
            <div class="json-title datos-antes">
                <i class="fas fa-arrow-left"></i> Datos Anteriores
            </div>
            <div class="json-box">${datosAntes}</div>
        `;
    }
    
    // Datos después
    if (datosDespues) {
        html += `
            <div class="json-title datos-despues" style="margin-top: 16px;">
                <i class="fas fa-arrow-right"></i> Datos Nuevos
            </div>
            <div class="json-box">${datosDespues}</div>
        `;
    }
    
    if (!datosAntes && !datosDespues) {
        html += `<p class="sin-datos" style="text-align: center; padding: 20px;">No hay datos adicionales para este registro.</p>`;
    }
    
    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('modalDetalle').classList.add('visible');
}

function cerrarModal(event) {
    if (event && event.target !== event.currentTarget) return;
    document.getElementById('modalDetalle').classList.remove('visible');
}

// Cerrar con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModal();
    }
});
</script>

</body>
</html>