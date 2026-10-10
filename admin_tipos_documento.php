<?php
// Gestión de Tipos de Documento

session_start();
require_once 'conexion.php';
require_once 'permisos_helper.php';

requerir_permiso('gestion_productos');

$pdo = conectar();
$mensaje = $_SESSION['mensaje'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['error']);

$busqueda = $_GET['busqueda'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        switch ($accion) {
            case 'crear':
                $nombre = trim($_POST['nombre'] ?? '');
                $abreviatura = trim($_POST['abreviatura'] ?? '');
                
                if (empty($nombre) || strlen($nombre) < 2) {
                    throw new Exception('El nombre debe tener al menos 2 caracteres.');
                }
                
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tipos_documento WHERE nombre = :nombre");
                $stmt_check->execute([':nombre' => $nombre]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('Ya existe un tipo de documento con ese nombre.');
                }
                
                $stmt = $pdo->prepare("INSERT INTO tipos_documento (nombre, abreviatura, estado) VALUES (?, ?, ?)");
                $stmt->execute([$nombre, $abreviatura, $_POST['estado'] ?? 'activo']);
                
                $id_tipo_documento = $pdo->lastInsertId();
                
                // Auditoría
                registrar_auditoria('crear', 'tipos_documento', $id_tipo_documento, null, $_POST);
                
                $_SESSION['mensaje'] = 'Tipo de documento creado exitosamente.';
                break;
                
            case 'editar':
                $id_tipo_documento = intval($_POST['id_tipo_documento'] ?? 0);
                $nombre = trim($_POST['nombre'] ?? '');
                $abreviatura = trim($_POST['abreviatura'] ?? '');
                
                if (empty($nombre) || strlen($nombre) < 2) {
                    throw new Exception('El nombre debe tener al menos 2 caracteres.');
                }
                
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tipos_documento WHERE nombre = :nombre AND id_tipo_documento != :id");
                $stmt_check->execute([':nombre' => $nombre, ':id' => $id_tipo_documento]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('Ya existe otro tipo de documento con ese nombre.');
                }
                
                // Guardar datos anteriores
                $stmt_ant = $pdo->prepare("SELECT * FROM tipos_documento WHERE id_tipo_documento = ?");
                $stmt_ant->execute([$id_tipo_documento]);
                $datos_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("UPDATE tipos_documento SET nombre = ?, abreviatura = ?, estado = ? WHERE id_tipo_documento = ?");
                $stmt->execute([$nombre, $abreviatura, $_POST['estado'] ?? 'activo', $id_tipo_documento]);
                
                // Auditoría
                registrar_auditoria('editar', 'tipos_documento', $id_tipo_documento, $datos_anteriores, $_POST);
                
                $_SESSION['mensaje'] = 'Tipo de documento actualizado exitosamente.';
                break;
                
            case 'eliminar':
                $id_tipo_documento = intval($_POST['id_tipo_documento'] ?? 0);
                
                // Guardar datos antes de eliminar
                $stmt_ant = $pdo->prepare("SELECT * FROM tipos_documento WHERE id_tipo_documento = ?");
                $stmt_ant->execute([$id_tipo_documento]);
                $datos_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("DELETE FROM tipos_documento WHERE id_tipo_documento = ?");
                $stmt->execute([$id_tipo_documento]);
                
                // Auditoría
                registrar_auditoria('eliminar', 'tipos_documento', $id_tipo_documento, $datos_anteriores, null);
                
                $_SESSION['mensaje'] = 'Tipo de documento eliminado exitosamente.';
                break;
                
            case 'cambiar_estado':
                $id_tipo_documento = intval($_POST['id_tipo_documento'] ?? 0);
                $nuevo_estado = $_POST['estado'] ?? 'activo';
                
                // Guardar estado anterior
                $stmt_ant = $pdo->prepare("SELECT estado FROM tipos_documento WHERE id_tipo_documento = ?");
                $stmt_ant->execute([$id_tipo_documento]);
                $estado_anterior = $stmt_ant->fetchColumn();
                
                $stmt = $pdo->prepare("UPDATE tipos_documento SET estado = ? WHERE id_tipo_documento = ?");
                $stmt->execute([$nuevo_estado, $id_tipo_documento]);
                
                // Auditoría
                registrar_auditoria('editar', 'tipos_documento', $id_tipo_documento, 
                    ['estado' => $estado_anterior], 
                    ['estado' => $nuevo_estado]
                );
                
                $_SESSION['mensaje'] = 'Estado del tipo de documento actualizado.';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    $params = [];
    if (!empty($busqueda)) $params[] = 'busqueda=' . urlencode($busqueda);
    if (!empty($filtro_estado)) $params[] = 'filtro_estado=' . urlencode($filtro_estado);
    if ($pagina_actual > 1) $params[] = 'pagina=' . $pagina_actual;
    header('Location: admin_tipos_documento.php' . (count($params) > 0 ? '?' . implode('&', $params) : ''));
    exit;
}

// Obtener datos
$sql_count = "SELECT COUNT(*) as total FROM tipos_documento WHERE 1=1";
$params_count = [];
if (!empty($busqueda)) {
    $sql_count .= " AND (nombre LIKE ? OR abreviatura LIKE ?)";
    $params_count[] = "%$busqueda%";
    $params_count[] = "%$busqueda%";
}
if (!empty($filtro_estado)) {
    $sql_count .= " AND estado = ?";
    $params_count[] = $filtro_estado;
}
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params_count);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

$sql = "SELECT * FROM tipos_documento WHERE 1=1";
$params = [];
if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE ? OR abreviatura LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}
if (!empty($filtro_estado)) {
    $sql .= " AND estado = ?";
    $params[] = $filtro_estado;
}
$sql .= " ORDER BY nombre LIMIT ? OFFSET ?";
$params[] = $registros_por_pagina;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_stats = "SELECT COUNT(*) as total, 
    SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
    SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivos
    FROM tipos_documento";
$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$tipo_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM tipos_documento WHERE id_tipo_documento = ?");
    $stmt->execute([$_GET['editar']]);
    $tipo_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Gestión de Tipos de Documento</title>
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
        .stat-card.danger .icono { color: #f85149; } .stat-card.danger { border-top: 3px solid #f85149; }
        
        .filtros-container { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
        .filtros-container .row { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
        .filtros-container .col { flex: 1; min-width: 180px; }
        .filtros-container label { color: #8b949e; font-size: 0.8rem; display: block; margin-bottom: 4px; }
        .filtros-container input, .filtros-container select { width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #30363d; background: #0d1117; color: #e6edf3; font-size: 0.9rem; transition: all 0.3s; }
        .filtros-container input:focus, .filtros-container select:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        .btn-filtro { padding: 8px 16px; border-radius: 8px; border: 1px solid #30363d; background: transparent; color: #8b949e; cursor: pointer; transition: all 0.3s; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; }
        .btn-filtro:hover { border-color: #c9a84c; color: #c9a84c; }
        .btn-filtro.primary { background: #c9a84c; color: #0d1117; border-color: #c9a84c; }
        .btn-filtro.primary:hover { background: #b8943a; }
        
        .formulario-toggle { display: none; background: #161b22; padding: 20px; border-radius: 12px; border: 1px solid #30363d; margin-bottom: 24px; }
        .formulario-toggle.visible { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .formulario-toggle h3 { color: #c9a84c; margin-bottom: 16px; font-size: 1.1rem; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; color: #8b949e; font-size: 0.85rem; margin-bottom: 4px; }
        .form-group label i { color: #c9a84c; margin-right: 4px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #30363d; background: #0d1117; color: #e6edf3; font-size: 0.95rem; transition: all 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        
        .btn-primario { background: linear-gradient(135deg, #c9a84c, #b8943a); color: #0d1117; padding: 10px 24px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: all 0.3s; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primario:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(201,168,76,0.3); }
        .btn-crear { background: linear-gradient(135deg, #c9a84c, #b8943a); color: #0d1117; padding: 8px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; }
        .btn-crear:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(201,168,76,0.3); }
        .btn-modal-secondary { background: transparent; border: 1px solid #30363d; color: #8b949e; padding: 8px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 0.9rem; }
        .btn-modal-secondary:hover { border-color: #8b949e; color: #e6edf3; }
        
        .btn-accion { padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.75rem; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; color: white; }
        .btn-accion:hover { transform: scale(1.05); }
        .btn-editar { background: #d29922; } .btn-editar:hover { background: #b8943a; }
        .btn-eliminar { background: #f85149; } .btn-eliminar:hover { background: #da3633; }
        .btn-cambiar-estado { background: #d29922; } .btn-cambiar-estado:hover { background: #b8943a; }
        
        .badge-estado { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-activo { background: rgba(46,160,67,0.15); color: #3fb950; }
        .badge-inactivo { background: rgba(248,81,73,0.15); color: #f85149; }
        
        .tabla-responsive { overflow-x: auto; border-radius: 12px; border: 1px solid #30363d; }
        .tabla-datos { width: 100%; border-collapse: collapse; background: #161b22; }
        .tabla-datos th { padding: 12px 16px; text-align: left; background: rgba(201,168,76,0.05); color: #c9a84c; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #30363d; }
        .tabla-datos td { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #e6edf3; font-size: 0.9rem; }
        .tabla-datos tbody tr:hover { background: rgba(255,255,255,0.02); }
        
        .paginacion { display: flex; justify-content: center; gap: 6px; margin-top: 24px; flex-wrap: wrap; }
        .paginacion .btn-pagina { padding: 6px 14px; border-radius: 6px; border: 1px solid #30363d; background: #161b22; color: #8b949e; cursor: pointer; transition: all 0.3s; text-decoration: none; font-size: 0.85rem; }
        .paginacion .btn-pagina:hover { border-color: #c9a84c; color: #c9a84c; }
        .paginacion .btn-pagina.activa { background: #c9a84c; color: #0d1117; border-color: #c9a84c; }
        .paginacion .btn-pagina.deshabilitada { opacity: 0.3; cursor: not-allowed; }
        .info-paginacion { text-align: center; color: #8b949e; font-size: 0.8rem; margin-top: 10px; }
        
        .footer-panel { text-align: center; padding: 30px 20px 20px; border-top: 1px solid #30363d; margin-top: 40px; color: #8b949e; }
        .footer-panel span { color: #c9a84c; font-weight: 600; }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .filtros-container .row { flex-direction: column; }
            .filtros-container .col { min-width: auto; width: 100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .header-contenido { flex-wrap: wrap; gap: 10px; }
            .nav-header { flex-wrap: wrap; gap: 8px; }
            .tabla-datos { font-size: 0.8rem; }
            .tabla-datos th, .tabla-datos td { padding: 8px 10px; }
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
    <h2><i class="fas fa-id-card"></i> Gestión de Tipos de Documento</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="icono"><i class="fas fa-id-card"></i></div>
            <div class="numero"><?= $stats['total'] ?></div>
            <div class="etiqueta">Total Tipos</div>
        </div>
        <div class="stat-card success">
            <div class="icono"><i class="fas fa-check-circle"></i></div>
            <div class="numero"><?= $stats['activos'] ?></div>
            <div class="etiqueta">Activos</div>
        </div>
        <div class="stat-card danger">
            <div class="icono"><i class="fas fa-times-circle"></i></div>
            <div class="numero"><?= $stats['inactivos'] ?></div>
            <div class="etiqueta">Inactivos</div>
        </div>
    </div>
    
    <div class="filtros-container">
        <form method="GET" action="">
            <div class="row">
                <div class="col">
                    <label><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" name="busqueda" placeholder="Nombre o abreviatura..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="col">
                    <label><i class="fas fa-filter"></i> Estado</label>
                    <select name="filtro_estado">
                        <option value="">Todos</option>
                        <option value="activo" <?= $filtro_estado === 'activo' ? 'selected' : '' ?>>Activos</option>
                        <option value="inactivo" <?= $filtro_estado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                    </select>
                </div>
                <div class="col" style="flex: 0 0 auto;">
                    <label>&nbsp;</label>
                    <div class="btn-group">
                        <button type="submit" class="btn-filtro primary"><i class="fas fa-search"></i> Buscar</button>
                        <a href="admin_tipos_documento.php" class="btn-filtro"><i class="fas fa-undo"></i> Limpiar</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <span style="color: #8b949e; font-size: 0.9rem;">
            <i class="fas fa-list"></i> <?= count($tipos) ?> de <?= $total_registros ?> tipos encontrados
        </span>
        <button type="button" class="btn-crear" onclick="toggleFormulario()">
            <i class="fas fa-plus-circle"></i> Nuevo Tipo
        </button>
    </div>
    
    <div id="formularioTipoDocumento" class="formulario-toggle <?= $tipo_editar ? 'visible' : '' ?>">
        <h3><i class="fas <?= $tipo_editar ? 'fa-pencil-alt' : 'fa-plus-circle' ?>"></i> <?= $tipo_editar ? 'Editar Tipo de Documento' : 'Nuevo Tipo de Documento' ?></h3>
        <form method="POST" action="">
            <input type="hidden" name="accion" value="<?= $tipo_editar ? 'editar' : 'crear' ?>">
            <?php if ($tipo_editar): ?>
                <input type="hidden" name="id_tipo_documento" value="<?= $tipo_editar['id_tipo_documento'] ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Nombre <span style="color: #f85149;">*</span></label>
                    <input type="text" name="nombre" value="<?= $tipo_editar ? htmlspecialchars($tipo_editar['nombre']) : '' ?>" required minlength="2" maxlength="30">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-shortcode"></i> Abreviatura</label>
                    <input type="text" name="abreviatura" value="<?= $tipo_editar ? htmlspecialchars($tipo_editar['abreviatura']) : '' ?>" maxlength="10">
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-toggle-on"></i> Estado</label>
                <select name="estado">
                    <option value="activo" <?= ($tipo_editar && $tipo_editar['estado'] === 'activo') ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= ($tipo_editar && $tipo_editar['estado'] === 'inactivo') ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-modal-secondary" onclick="toggleFormulario()"><i class="fas fa-times"></i> Cancelar</button>
                <button type="submit" class="btn-primario"><i class="fas fa-save"></i> <?= $tipo_editar ? 'Actualizar' : 'Crear' ?></button>
            </div>
        </form>
    </div>
    
    <div class="tabla-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Abreviatura</th>
                    <th>Estado</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tipos)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: #8b949e;">
                            <i class="fas fa-inbox" style="font-size: 3rem; display: block; margin-bottom: 12px; color: #30363d;"></i>
                            No hay tipos de documento registrados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tipos as $tipo): ?>
                        <tr>
                            <td style="font-weight: 600; color: #8b949e;">#<?= $tipo['id_tipo_documento'] ?></td>
                            <td><strong><?= htmlspecialchars($tipo['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($tipo['abreviatura'] ?? '-') ?></td>
                            <td>
                                <span class="badge-estado <?= $tipo['estado'] === 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                                    <?= ucfirst($tipo['estado']) ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">
                                    <a href="?editar=<?= $tipo['id_tipo_documento'] ?>&pagina=<?= $pagina_actual ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>" class="btn-accion btn-editar" title="Editar" onclick="toggleFormulario()">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="id_tipo_documento" value="<?= $tipo['id_tipo_documento'] ?>">
                                        <input type="hidden" name="estado" value="<?= $tipo['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                        <button type="submit" name="cambiar_estado" class="btn-accion btn-cambiar-estado" title="<?= $tipo['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>">
                                            <i class="fas <?= $tipo['estado'] === 'activo' ? 'fa-pause' : 'fa-play' ?>"></i>
                                            <?= $tipo['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este tipo de documento?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_tipo_documento" value="<?= $tipo['id_tipo_documento'] ?>">
                                        <button type="submit" class="btn-accion btn-eliminar" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total_paginas > 1): ?>
    <div class="paginacion">
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>" class="btn-pagina <?= $i === $pagina_actual ? 'activa' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
    <div class="info-paginacion">Página <?= $pagina_actual ?> de <?= $total_paginas ?> (<?= $total_registros ?> tipos)</div>
    <?php endif; ?>
    
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Gestión de Tipos de Documento</p>
</footer>

<script>
    function toggleFormulario() {
        const form = document.getElementById('formularioTipoDocumento');
        if (form.classList.contains('visible')) {
            form.classList.remove('visible');
        } else {
            form.classList.add('visible');
        }
    }
    
    <?php if (isset($_GET['editar'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('formularioTipoDocumento').classList.add('visible');
    });
    <?php endif; ?>
</script>

</body>
</html>