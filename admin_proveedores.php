<?php
// Gestión de Proveedores

session_start();
require_once 'conexion.php';
require_once 'permisos_helper.php';

// Seguridad
requerir_permiso('gestion_proveedores');

// Variables globales
$pdo = conectar();
$mensaje = $_SESSION['mensaje'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['error']);

$busqueda = $_GET['busqueda'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        switch ($accion) {
            case 'crear':
                $razon_social = trim($_POST['razon_social'] ?? '');
                if (empty($razon_social)) {
                    throw new Exception('La razón social es obligatoria');
                }
                if (strlen($razon_social) < 3) {
                    throw new Exception('La razón social debe tener al menos 3 caracteres');
                }
                
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM proveedor WHERE razon_social = :razon");
                $stmt_check->execute([':razon' => $razon_social]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('Ya existe un proveedor con esa razón social.');
                }
                
                $cuit = trim($_POST['cuit'] ?? '');
                if (!empty($cuit) && !preg_match('/^\d{2}-\d{8}-\d$/', $cuit)) {
                    throw new Exception('El CUIT debe tener el formato XX-XXXXXXXX-X (ej: 20-12345678-9)');
                }
                
                $email = trim($_POST['email'] ?? '');
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('El email ingresado no es válido');
                }
                
                $contacto_email = trim($_POST['contacto_email'] ?? '');
                if (!empty($contacto_email) && !filter_var($contacto_email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('El email del contacto no es válido');
                }
                
                $sql = "INSERT INTO proveedor (
                    razon_social, telefono, email, direccion, cuit, estado,
                    contacto_nombre, contacto_cargo, contacto_telefono, contacto_email, notas
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $razon_social,
                    trim($_POST['telefono'] ?? ''),
                    $email,
                    trim($_POST['direccion'] ?? ''),
                    $cuit,
                    $_POST['estado'] ?? 'activo',
                    trim($_POST['contacto_nombre'] ?? ''),
                    trim($_POST['contacto_cargo'] ?? ''),
                    trim($_POST['contacto_telefono'] ?? ''),
                    $contacto_email,
                    trim($_POST['notas'] ?? '')
                ]);
                
                $id_proveedor = $pdo->lastInsertId();
                
                // Auditoría
                registrar_auditoria('crear', 'proveedores', $id_proveedor, null, $_POST);
                
                $_SESSION['mensaje'] = 'Proveedor creado exitosamente';
                break;
                
            case 'editar':
                $id_proveedor = intval($_POST['id_proveedor'] ?? 0);
                if ($id_proveedor <= 0) {
                    throw new Exception('ID de proveedor inválido');
                }
                
                // Guardar datos anteriores
                $stmt_ant = $pdo->prepare("SELECT * FROM proveedor WHERE id_proveedor = ?");
                $stmt_ant->execute([$id_proveedor]);
                $datos_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);
                
                $razon_social = trim($_POST['razon_social'] ?? '');
                if (empty($razon_social)) {
                    throw new Exception('La razón social es obligatoria');
                }
                
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM proveedor WHERE razon_social = :razon AND id_proveedor != :id");
                $stmt_check->execute([':razon' => $razon_social, ':id' => $id_proveedor]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('Ya existe otro proveedor con esa razón social.');
                }
                
                $cuit = trim($_POST['cuit'] ?? '');
                if (!empty($cuit) && !preg_match('/^\d{2}-\d{8}-\d$/', $cuit)) {
                    throw new Exception('El CUIT debe tener el formato XX-XXXXXXXX-X (ej: 20-12345678-9)');
                }
                
                $email = trim($_POST['email'] ?? '');
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('El email ingresado no es válido');
                }
                
                $contacto_email = trim($_POST['contacto_email'] ?? '');
                if (!empty($contacto_email) && !filter_var($contacto_email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('El email del contacto no es válido');
                }
                
                $sql = "UPDATE proveedor SET 
                    razon_social = ?,
                    telefono = ?,
                    email = ?,
                    direccion = ?,
                    cuit = ?,
                    estado = ?,
                    contacto_nombre = ?,
                    contacto_cargo = ?,
                    contacto_telefono = ?,
                    contacto_email = ?,
                    notas = ?
                WHERE id_proveedor = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $razon_social,
                    trim($_POST['telefono'] ?? ''),
                    $email,
                    trim($_POST['direccion'] ?? ''),
                    $cuit,
                    $_POST['estado'] ?? 'activo',
                    trim($_POST['contacto_nombre'] ?? ''),
                    trim($_POST['contacto_cargo'] ?? ''),
                    trim($_POST['contacto_telefono'] ?? ''),
                    $contacto_email,
                    trim($_POST['notas'] ?? ''),
                    $id_proveedor
                ]);
                
                // Auditoría
                registrar_auditoria('editar', 'proveedores', $id_proveedor, $datos_anteriores, $_POST);
                
                $_SESSION['mensaje'] = 'Proveedor actualizado exitosamente';
                break;
                
            case 'eliminar':
                $id_proveedor = intval($_POST['id_proveedor'] ?? 0);
                
                $sql_check = "SELECT COUNT(*) FROM productos WHERE id_proveedor = ?";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$id_proveedor]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('No se puede eliminar: el proveedor tiene productos asociados');
                }
                
                // Guardar datos antes de eliminar
                $stmt_ant = $pdo->prepare("SELECT * FROM proveedor WHERE id_proveedor = ?");
                $stmt_ant->execute([$id_proveedor]);
                $datos_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);
                
                $sql = "DELETE FROM proveedor WHERE id_proveedor = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_proveedor]);
                
                // Auditoría
                registrar_auditoria('eliminar', 'proveedores', $id_proveedor, $datos_anteriores, null);
                
                $_SESSION['mensaje'] = 'Proveedor eliminado exitosamente';
                break;
                
            case 'cambiar_estado':
                $id_proveedor = intval($_POST['id_proveedor'] ?? 0);
                $nuevo_estado = $_POST['estado'] ?? 'activo';
                
                // Guardar estado anterior
                $stmt_ant = $pdo->prepare("SELECT estado FROM proveedor WHERE id_proveedor = ?");
                $stmt_ant->execute([$id_proveedor]);
                $estado_anterior = $stmt_ant->fetchColumn();
                
                $sql = "UPDATE proveedor SET estado = ? WHERE id_proveedor = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nuevo_estado, $id_proveedor]);
                
                // Auditoría
                registrar_auditoria('editar', 'proveedores', $id_proveedor, 
                    ['estado' => $estado_anterior], 
                    ['estado' => $nuevo_estado]
                );
                
                $_SESSION['mensaje'] = 'Estado del proveedor actualizado';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error en la base de datos: ' . $e->getMessage();
    }
    
    $params = [];
    if (!empty($busqueda)) $params[] = 'busqueda=' . urlencode($busqueda);
    if (!empty($filtro_estado)) $params[] = 'filtro_estado=' . urlencode($filtro_estado);
    if ($pagina_actual > 1) $params[] = 'pagina=' . $pagina_actual;
    
    $redirect = 'admin_proveedores.php' . (count($params) > 0 ? '?' . implode('&', $params) : '');
    header('Location: ' . $redirect);
    exit;
}

// Obtener datos

// Contar total de registros
$sql_count = "SELECT COUNT(*) as total FROM proveedor WHERE 1=1";
$params_count = [];

if (!empty($busqueda)) {
    $sql_count .= " AND (razon_social LIKE ? OR cuit LIKE ? OR email LIKE ? OR contacto_nombre LIKE ?)";
    $params_count[] = "%$busqueda%";
    $params_count[] = "%$busqueda%";
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

// Obtener proveedores de la página actual
$sql_proveedores = "SELECT * FROM proveedor WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql_proveedores .= " AND (razon_social LIKE ? OR cuit LIKE ? OR email LIKE ? OR contacto_nombre LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if (!empty($filtro_estado)) {
    $sql_proveedores .= " AND estado = ?";
    $params[] = $filtro_estado;
}

$sql_proveedores .= " ORDER BY razon_social LIMIT ? OFFSET ?";
$params[] = $registros_por_pagina;
$params[] = $offset;

$stmt_proveedores = $pdo->prepare($sql_proveedores);
$stmt_proveedores->execute($params);
$proveedores = $stmt_proveedores->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
    SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivos
    FROM proveedor";
$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Proveedor para editar
$proveedor_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $sql_editar = "SELECT * FROM proveedor WHERE id_proveedor = ?";
    $stmt_editar = $pdo->prepare($sql_editar);
    $stmt_editar->execute([$_GET['editar']]);
    $proveedor_editar = $stmt_editar->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Gestión de Proveedores</title>
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
        .stat-card .etiqueta { color: #8b949e; font-size: 0.8rem; margin-top: 2px; }
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
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        
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
            .form-row, .form-row-3 { grid-template-columns: 1fr; }
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
    <h2><i class="fas fa-truck"></i> Gestión de Proveedores</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="icono"><i class="fas fa-truck"></i></div>
            <div class="numero"><?= $stats['total'] ?></div>
            <div class="etiqueta">Total Proveedores</div>
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
                    <input type="text" name="busqueda" placeholder="Razón social, CUIT, email o contacto..." value="<?= htmlspecialchars($busqueda) ?>">
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
                        <a href="admin_proveedores.php" class="btn-filtro"><i class="fas fa-undo"></i> Limpiar</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <span style="color: #8b949e; font-size: 0.9rem;">
            <i class="fas fa-list"></i> <?= count($proveedores) ?> de <?= $total_registros ?> proveedores encontrados
            <?php if (!empty($busqueda) || !empty($filtro_estado)): ?>
                <span style="color: #c9a84c;">(filtros aplicados)</span>
            <?php endif; ?>
        </span>
        <button type="button" class="btn-crear" id="btnToggleFormulario" onclick="toggleFormulario()">
            <i class="fas fa-plus-circle" id="iconoBoton"></i>
            <span id="textoBoton">Nuevo Proveedor</span>
        </button>
    </div>
    
    <div id="formularioProveedor" class="formulario-toggle <?= $proveedor_editar ? 'visible' : '' ?>">
        <h3>
            <i class="fas <?= $proveedor_editar ? 'fa-pencil-alt' : 'fa-plus-circle' ?>"></i>
            <?= $proveedor_editar ? 'Editar Proveedor' : 'Nuevo Proveedor' ?>
        </h3>
        <form method="POST" action="">
            <input type="hidden" name="accion" value="<?= $proveedor_editar ? 'editar' : 'crear' ?>">
            <?php if ($proveedor_editar): ?>
                <input type="hidden" name="id_proveedor" value="<?= $proveedor_editar['id_proveedor'] ?>">
            <?php endif; ?>
            
            <!-- Datos de la empresa -->
            <div style="background: rgba(201,168,76,0.05); padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                <h4 style="color: #c9a84c; margin-bottom: 12px; font-size: 0.9rem;"><i class="fas fa-building"></i> Datos de la Empresa</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Razón Social <span style="color: #f85149;">*</span></label>
                        <input type="text" name="razon_social" 
                               value="<?= $proveedor_editar ? htmlspecialchars($proveedor_editar['razon_social']) : '' ?>" 
                               required minlength="3" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> CUIT</label>
                        <input type="text" name="cuit" 
                               value="<?= $proveedor_editar ? htmlspecialchars($proveedor_editar['cuit']) : '' ?>" 
                               maxlength="20" placeholder="20-12345678-9">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Teléfono</label>
                        <input type="text" name="telefono" 
                               value="<?= $proveedor_editar ? htmlspecialchars($proveedor_editar['telefono']) : '' ?>"
                               placeholder="+54 9 370 486-3574">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" 
                               value="<?= $proveedor_editar ? htmlspecialchars($proveedor_editar['email']) : '' ?>"
                               placeholder="proveedor@empresa.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Dirección</label>
                    <input type="text" name="direccion" 
                           value="<?= $proveedor_editar ? htmlspecialchars($proveedor_editar['direccion']) : '' ?>"
                           placeholder="Calle, número, ciudad, provincia">
                </div>
            </div>
            
            <!-- Datos de contacto -->
            <div style="background: rgba(88,166,255,0.05); padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                <h4 style="color: #58a6ff; margin-bottom: 12px; font-size: 0.9rem;"><i class="fas fa-user-tie"></i> Persona de Contacto</h4>
                <p style="color: #8b949e; font-size: 0.8rem; margin-bottom: 12px;">
                    <i class="fas fa-info-circle"></i> Información de la persona con quien tratar directamente.
                </p>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nombre</label>
                        <input type="text" name="contacto_nombre" 
                               value="<?= $proveedor_editar ? htmlspecialchars($proveedor_editar['contacto_nombre'] ?? '') : '' ?>"
                               placeholder="Juan Pérez" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-briefcase"></i> Cargo</label>
                        <input type="text" name="contacto_cargo" 
                               value="<?= $proveedor_editar ? htmlspecialchars($proveedor_editar['contacto_cargo'] ?? '') : '' ?>"
                               placeholder="Gerente de Ventas, Encargado..." maxlength="100">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-phone-alt"></i> Teléfono</label>
                        <input type="text" name="contacto_telefono" 
                               value="<?= $proveedor_editar ? htmlspecialchars($proveedor_editar['contacto_telefono'] ?? '') : '' ?>"
                               placeholder="+54 9 370 486-3574" maxlength="30">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="contacto_email" 
                               value="<?= $proveedor_editar ? htmlspecialchars($proveedor_editar['contacto_email'] ?? '') : '' ?>"
                               placeholder="juan.perez@empresa.com" maxlength="100">
                    </div>
                </div>
            </div>
            
            <!-- Estado y notas -->
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Estado</label>
                    <select name="estado">
                        <option value="activo" <?= ($proveedor_editar && $proveedor_editar['estado'] === 'activo') ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= ($proveedor_editar && $proveedor_editar['estado'] === 'inactivo') ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> Notas adicionales</label>
                    <textarea name="notas" rows="2" maxlength="500" placeholder="Observaciones sobre el proveedor..."><?= $proveedor_editar ? htmlspecialchars($proveedor_editar['notas'] ?? '') : '' ?></textarea>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                <button type="button" class="btn-modal-secondary" onclick="toggleFormulario()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn-primario">
                    <i class="fas fa-save"></i> <?= $proveedor_editar ? 'Actualizar' : 'Crear' ?>
                </button>
            </div>
        </form>
    </div>
    
    <div class="tabla-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Razón Social</th>
                    <th>Contacto</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($proveedores)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #8b949e;">
                            <i class="fas fa-inbox" style="font-size: 3rem; display: block; margin-bottom: 12px; color: #30363d;"></i>
                            <?php if (!empty($busqueda) || !empty($filtro_estado)): ?>
                                No se encontraron proveedores con los filtros aplicados.
                            <?php else: ?>
                                No hay proveedores registrados.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proveedores as $proveedor): ?>
                        <tr>
                            <td style="font-weight: 600; color: #8b949e;">#<?= $proveedor['id_proveedor'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($proveedor['razon_social']) ?></strong>
                                <br><small style="color: #8b949e;"><?= htmlspecialchars($proveedor['direccion'] ?? '') ?></small>
                            </td>
                            <td>
                                <?php if (!empty($proveedor['contacto_nombre'])): ?>
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="color: #58a6ff; font-weight: 600;"><?= htmlspecialchars($proveedor['contacto_nombre']) ?></span>
                                        <span style="color: #8b949e; font-size: 0.7rem;"><?= htmlspecialchars($proveedor['contacto_cargo'] ?? '') ?></span>
                                        <?php if (!empty($proveedor['contacto_telefono'])): ?>
                                            <span style="color: #8b949e; font-size: 0.7rem;"><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($proveedor['contacto_telefono']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #8b949e; font-size: 0.8rem;">Sin contacto</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($proveedor['telefono'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($proveedor['email'] ?? '-') ?></td>
                            <td>
                                <span class="badge-estado <?= $proveedor['estado'] === 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                                    <i class="fas <?= $proveedor['estado'] === 'activo' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                    <?= ucfirst($proveedor['estado']) ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">
                                    <a href="?editar=<?= $proveedor['id_proveedor'] ?>&pagina=<?= $pagina_actual ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>" class="btn-accion btn-editar" title="Editar" onclick="toggleFormulario()">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="id_proveedor" value="<?= $proveedor['id_proveedor'] ?>">
                                        <input type="hidden" name="estado" value="<?= $proveedor['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                        <button type="submit" name="cambiar_estado" class="btn-accion btn-cambiar-estado" title="<?= $proveedor['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>">
                                            <i class="fas <?= $proveedor['estado'] === 'activo' ? 'fa-pause' : 'fa-play' ?>"></i>
                                            <?= $proveedor['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este proveedor?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_proveedor" value="<?= $proveedor['id_proveedor'] ?>">
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
        <?php if ($pagina_actual > 1): ?>
            <a href="?pagina=<?= $pagina_actual - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>" class="btn-pagina">
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
            echo '<a href="?pagina=1&busqueda=' . urlencode($busqueda) . '&filtro_estado=' . urlencode($filtro_estado) . '" class="btn-pagina">1</a>';
            if ($inicio > 2) {
                echo '<span class="btn-pagina deshabilitada">…</span>';
            }
        }
        
        for ($i = $inicio; $i <= $fin; $i++): ?>
            <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>" class="btn-pagina <?= $i === $pagina_actual ? 'activa' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor;
        
        if ($fin < $total_paginas) {
            if ($fin < $total_paginas - 1) {
                echo '<span class="btn-pagina deshabilitada">…</span>';
            }
            echo '<a href="?pagina=' . $total_paginas . '&busqueda=' . urlencode($busqueda) . '&filtro_estado=' . urlencode($filtro_estado) . '" class="btn-pagina">' . $total_paginas . '</a>';
        }
        ?>
        
        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="?pagina=<?= $pagina_actual + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>" class="btn-pagina">
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php else: ?>
            <span class="btn-pagina deshabilitada"><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
    </div>
    <div class="info-paginacion">
        Página <?= $pagina_actual ?> de <?= $total_paginas ?> (<?= $total_registros ?> proveedores en total)
    </div>
    <?php endif; ?>
    
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Gestión de Proveedores</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

<script>
    function toggleFormulario() {
        const form = document.getElementById('formularioProveedor');
        const btn = document.getElementById('btnToggleFormulario');
        const icono = document.getElementById('iconoBoton');
        const texto = document.getElementById('textoBoton');
        
        if (form.classList.contains('visible')) {
            form.classList.remove('visible');
            icono.className = 'fas fa-plus-circle';
            texto.textContent = 'Nuevo Proveedor';
        } else {
            form.classList.add('visible');
            icono.className = 'fas fa-times-circle';
            texto.textContent = 'Cerrar formulario';
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['editar'])): ?>
            const form = document.getElementById('formularioProveedor');
            const btn = document.getElementById('btnToggleFormulario');
            const icono = document.getElementById('iconoBoton');
            const texto = document.getElementById('textoBoton');
            form.classList.add('visible');
            icono.className = 'fas fa-times-circle';
            texto.textContent = 'Cerrar formulario';
        <?php endif; ?>
    });
</script>

</body>
</html>