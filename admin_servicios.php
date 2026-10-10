<?php
// Gestión de Servicios

session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('gestion_servicios');

$pdo = conectar();
$mensaje = '';
$error = '';

// Variables para filtros y paginación
$busqueda = $_GET['busqueda'] ?? '';
$filtro_categoria = $_GET['filtro_categoria'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener categorías para el filtro y formulario
$categorias = $pdo->query("SELECT id_categoria, nombre FROM categorias WHERE estado = 'activo' ORDER BY nombre")->fetchAll();

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Crear
        if (isset($_POST['crear'])) {
            $nombre = trim($_POST['nombre']);
            $categoria = (int)$_POST['categoria'];
            $duracion = trim($_POST['duracion']);
            $precio = (float)$_POST['precio'];
            $descripcion = trim($_POST['descripcion'] ?? '');
            
            if (empty($nombre) || strlen($nombre) < 2) {
                throw new Exception('El nombre debe tener al menos 2 caracteres.');
            }
            if (strlen($nombre) > 100) {
                throw new Exception('El nombre no puede tener más de 100 caracteres.');
            }
            if ($categoria <= 0) {
                throw new Exception('Seleccioná una categoría válida.');
            }
            if (empty($duracion)) {
                throw new Exception('La duración es obligatoria.');
            }
            if ($precio <= 0) {
                throw new Exception('El precio debe ser mayor a 0.');
            }
            
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM servicios WHERE nombre = :nombre");
            $stmt_check->execute([':nombre' => $nombre]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception('Ya existe un servicio con ese nombre.');
            }
            
            $stmt = $pdo->prepare("INSERT INTO servicios (id_categoria, nombre, descripcion, duracion, precio, estado) 
                                   VALUES (:cat, :nom, :desc, :dur, :pre, 'activo')");
            $stmt->execute([
                ':cat' => $categoria, 
                ':nom' => $nombre, 
                ':desc' => $descripcion, 
                ':dur' => $duracion, 
                ':pre' => $precio
            ]);
            
            $id_servicio = $pdo->lastInsertId();
            
            // Auditoría
            registrar_auditoria('crear', 'servicios', $id_servicio, null, $_POST);
            
            $mensaje = "Servicio creado correctamente.";
        }
        
        // Editar
        if (isset($_POST['editar'])) {
            $id = (int)$_POST['id_servicio'];
            $nombre = trim($_POST['nombre']);
            $categoria = (int)$_POST['categoria'];
            $duracion = trim($_POST['duracion']);
            $precio = (float)$_POST['precio'];
            $descripcion = trim($_POST['descripcion'] ?? '');
            
            if (empty($nombre) || strlen($nombre) < 2) {
                throw new Exception('El nombre debe tener al menos 2 caracteres.');
            }
            if (strlen($nombre) > 100) {
                throw new Exception('El nombre no puede tener más de 100 caracteres.');
            }
            if ($categoria <= 0) {
                throw new Exception('Seleccioná una categoría válida.');
            }
            if (empty($duracion)) {
                throw new Exception('La duración es obligatoria.');
            }
            if ($precio <= 0) {
                throw new Exception('El precio debe ser mayor a 0.');
            }
            
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM servicios WHERE nombre = :nombre AND id_servicio != :id");
            $stmt_check->execute([':nombre' => $nombre, ':id' => $id]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception('Ya existe otro servicio con ese nombre.');
            }
            
            // Guardar datos anteriores
            $stmt_ant = $pdo->prepare("SELECT * FROM servicios WHERE id_servicio = :id");
            $stmt_ant->execute([':id' => $id]);
            $datos_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("UPDATE servicios SET 
                id_categoria = :cat, 
                nombre = :nom, 
                descripcion = :desc, 
                duracion = :dur, 
                precio = :pre 
                WHERE id_servicio = :id");
            $stmt->execute([
                ':cat' => $categoria, 
                ':nom' => $nombre, 
                ':desc' => $descripcion, 
                ':dur' => $duracion, 
                ':pre' => $precio, 
                ':id' => $id
            ]);
            
            // Auditoría
            registrar_auditoria('editar', 'servicios', $id, $datos_anteriores, $_POST);
            
            $mensaje = "Servicio actualizado correctamente.";
        }
        
        // Cambiar estado
        if (isset($_POST['cambiar_estado'])) {
            $id = (int)$_POST['id_servicio'];
            $nuevo_estado = $_POST['nuevo_estado'];
            
            // Guardar estado anterior
            $stmt_ant = $pdo->prepare("SELECT estado FROM servicios WHERE id_servicio = :id");
            $stmt_ant->execute([':id' => $id]);
            $estado_anterior = $stmt_ant->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE servicios SET estado = :estado WHERE id_servicio = :id");
            $stmt->execute([':estado' => $nuevo_estado, ':id' => $id]);
            
            // Auditoría
            registrar_auditoria('editar', 'servicios', $id, 
                ['estado' => $estado_anterior], 
                ['estado' => $nuevo_estado]
            );
            
            $mensaje = "Estado actualizado correctamente.";
        }
        
        // Eliminar
        if (isset($_POST['eliminar'])) {
            $id = (int)$_POST['id_servicio'];
            
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM detalle_cita WHERE id_servicio = :id");
            $stmt_check->execute([':id' => $id]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception('No se puede eliminar: el servicio tiene citas asociadas.');
            }
            
            // Guardar datos antes de eliminar
            $stmt_ant = $pdo->prepare("SELECT * FROM servicios WHERE id_servicio = :id");
            $stmt_ant->execute([':id' => $id]);
            $datos_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM servicios WHERE id_servicio = :id");
            $stmt->execute([':id' => $id]);
            
            // Auditoría
            registrar_auditoria('eliminar', 'servicios', $id, $datos_anteriores, null);
            
            $mensaje = "Servicio eliminado correctamente.";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}

// Obtener datos con filtros y paginación

// Contar total de registros
$sql_count = "SELECT COUNT(*) as total FROM servicios s WHERE 1=1";
$params_count = [];

if (!empty($busqueda)) {
    $sql_count .= " AND (s.nombre LIKE ? OR s.descripcion LIKE ?)";
    $params_count[] = "%$busqueda%";
    $params_count[] = "%$busqueda%";
}

if (!empty($filtro_categoria)) {
    $sql_count .= " AND s.id_categoria = ?";
    $params_count[] = $filtro_categoria;
}

if (!empty($filtro_estado)) {
    $sql_count .= " AND s.estado = ?";
    $params_count[] = $filtro_estado;
}

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params_count);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener servicios de la página actual
$sql = "
    SELECT s.*, c.nombre as categoria_nombre 
    FROM servicios s 
    JOIN categorias c ON c.id_categoria = s.id_categoria 
    WHERE 1=1
";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (s.nombre LIKE ? OR s.descripcion LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if (!empty($filtro_categoria)) {
    $sql .= " AND s.id_categoria = ?";
    $params[] = $filtro_categoria;
}

if (!empty($filtro_estado)) {
    $sql .= " AND s.estado = ?";
    $params[] = $filtro_estado;
}

$sql .= " ORDER BY s.nombre LIMIT ? OFFSET ?";
$params[] = $registros_por_pagina;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$servicios = $stmt->fetchAll();

// Estadísticas
$stats = [
    'total' => $total_registros,
    'activos' => 0,
    'inactivos' => 0
];

$stmt_stats = $pdo->query("SELECT estado, COUNT(*) as cantidad FROM servicios GROUP BY estado");
while ($row = $stmt_stats->fetch()) {
    if ($row['estado'] === 'activo') {
        $stats['activos'] = $row['cantidad'];
    } else {
        $stats['inactivos'] = $row['cantidad'];
    }
}

// Servicio para editar
$servicio_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM servicios WHERE id_servicio = :id");
    $stmt->execute([':id' => $_GET['editar']]);
    $servicio_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Gestión de Servicios</title>
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
        .contenedor-panel h3 { color: #e6edf3; font-size: 1.1rem; margin-bottom: 16px; }
        .contenedor-panel h3 i { color: #c9a84c; margin-right: 8px; }
        
        .alerta { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alerta-exito { background: rgba(46,160,67,0.15); border: 1px solid #238636; color: #3fb950; }
        .alerta-error { background: rgba(248,81,73,0.15); border: 1px solid #da3633; color: #f85149; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 16px 20px; text-align: center; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
        .stat-card .icono { font-size: 1.5rem; margin-bottom: 4px; }
        .stat-card .numero { font-size: 2rem; font-weight: 700; color: #e6edf3; }
        .stat-card .etiqueta { color: #8b949e; font-size: 0.8rem; margin-top: 2px; }
        .stat-card.primary .icono { color: #58a6ff; } .stat-card.primary { border-top: 3px solid #58a6ff; }
        .stat-card.success .icono { color: #3fb950; } .stat-card.success { border-top: 3px solid #3fb950; }
        .stat-card.danger .icono { color: #f85149; } .stat-card.danger { border-top: 3px solid #f85149; }
        
        .filtros-container { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
        .filtros-container .row { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
        .filtros-container .col { flex: 1; min-width: 160px; }
        .filtros-container label { color: #8b949e; font-size: 0.8rem; display: block; margin-bottom: 4px; }
        .filtros-container input, .filtros-container select { width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #30363d; background: #0d1117; color: #e6edf3; font-size: 0.9rem; transition: all 0.3s; }
        .filtros-container input:focus, .filtros-container select:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        .btn-filtro { padding: 8px 16px; border-radius: 8px; border: 1px solid #30363d; background: transparent; color: #8b949e; cursor: pointer; transition: all 0.3s; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; }
        .btn-filtro:hover { border-color: #c9a84c; color: #c9a84c; }
        .btn-filtro.primary { background: #c9a84c; color: #0d1117; border-color: #c9a84c; }
        .btn-filtro.primary:hover { background: #b8943a; }
        
        .form-servicio { background: #161b22; padding: 24px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #30363d; display: none; }
        .form-servicio.visible { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .form-servicio h3 { color: #c9a84c; margin-bottom: 16px; font-size: 1.1rem; }
        
        .fila-campos { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .campo-grupo { display: flex; flex-direction: column; }
        .campo-grupo label { color: #8b949e; font-size: 0.85rem; margin-bottom: 4px; }
        .campo-grupo label i { color: #c9a84c; margin-right: 4px; }
        .campo-grupo input, .campo-grupo select, .campo-grupo textarea { padding: 8px 12px; border-radius: 8px; border: 1px solid #30363d; background: #0d1117; color: #e6edf3; font-size: 0.95rem; transition: all 0.3s; }
        .campo-grupo input:focus, .campo-grupo select:focus, .campo-grupo textarea:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        .campo-grupo textarea { resize: vertical; min-height: 60px; }
        
        .btn-primario { background: linear-gradient(135deg, #c9a84c, #b8943a); color: #0d1117; padding: 10px 24px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: all 0.3s; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primario:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(201,168,76,0.3); }
        .btn-crear { background: linear-gradient(135deg, #c9a84c, #b8943a); color: #0d1117; padding: 8px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; }
        .btn-crear:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(201,168,76,0.3); }
        
        .btn-accion { padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.75rem; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; color: white; }
        .btn-accion:hover { transform: scale(1.05); }
        .btn-editar { background: #d29922; } .btn-editar:hover { background: #b8943a; }
        .btn-desactivar { background: #e74c3c; } .btn-desactivar:hover { background: #c0392b; }
        .btn-activar { background: #2ecc71; } .btn-activar:hover { background: #27ae60; }
        .btn-eliminar { background: #f85149; } .btn-eliminar:hover { background: #da3633; }
        .btn-modal-secondary { background: transparent; border: 1px solid #30363d; color: #8b949e; padding: 8px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 0.9rem; }
        .btn-modal-secondary:hover { border-color: #8b949e; color: #e6edf3; }
        
        .badge-estado { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-activo { background: #238636; color: white; }
        .badge-inactivo { background: #da3633; color: white; }
        
        .grilla-servicios { display: grid; gap: 12px; margin-top: 16px; }
        .servicio-item { background: #161b22; padding: 16px 20px; border-radius: 10px; border: 1px solid #30363d; border-left: 3px solid #c9a84c; transition: all 0.3s; }
        .servicio-item:hover { border-color: rgba(201,168,76,0.3); transform: translateX(4px); }
        .servicio-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .servicio-info { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .servicio-nombre { font-size: 1.1rem; font-weight: 700; color: #c9a84c; }
        .servicio-precio { font-size: 1.1rem; font-weight: 700; color: #3fb950; }
        .servicio-duracion { color: #8b949e; font-size: 0.85rem; }
        .servicio-categoria { color: #8b949e; font-size: 0.8rem; background: rgba(255,255,255,0.03); padding: 2px 10px; border-radius: 20px; border: 1px solid #30363d; }
        .servicio-desc { color: #8b949e; font-size: 0.85rem; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.04); }
        .servicio-acciones { display: flex; gap: 4px; flex-wrap: wrap; }
        
        .paginacion { display: flex; justify-content: center; gap: 6px; margin-top: 24px; flex-wrap: wrap; }
        .paginacion .btn-pagina { padding: 6px 14px; border-radius: 6px; border: 1px solid #30363d; background: #161b22; color: #8b949e; cursor: pointer; transition: all 0.3s; text-decoration: none; font-size: 0.85rem; }
        .paginacion .btn-pagina:hover { border-color: #c9a84c; color: #c9a84c; }
        .paginacion .btn-pagina.activa { background: #c9a84c; color: #0d1117; border-color: #c9a84c; }
        .paginacion .btn-pagina.deshabilitada { opacity: 0.3; cursor: not-allowed; }
        .info-paginacion { text-align: center; color: #8b949e; font-size: 0.8rem; margin-top: 10px; }
        
        .footer-panel { text-align: center; padding: 30px 20px 20px; border-top: 1px solid #30363d; margin-top: 40px; color: #8b949e; }
        .footer-panel span { color: #c9a84c; font-weight: 600; }
        
        @media (max-width: 768px) {
            .fila-campos { grid-template-columns: 1fr; }
            .filtros-container .row { flex-direction: column; }
            .filtros-container .col { min-width: auto; width: 100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .servicio-header { flex-direction: column; align-items: flex-start; }
            .servicio-info { flex-wrap: wrap; }
            .header-contenido { flex-wrap: wrap; gap: 10px; }
            .nav-header { flex-wrap: wrap; gap: 8px; }
            .contenedor-panel h2 { font-size: 1.4rem; }
            .paginacion .btn-pagina { padding: 4px 10px; font-size: 0.75rem; }
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
    <h2><i class="fas fa-cut"></i> Gestión de Servicios</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="icono"><i class="fas fa-cut"></i></div>
            <div class="numero"><?= $stats['total'] ?></div>
            <div class="etiqueta">Total Servicios</div>
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
    
    <!-- Filtros -->
    <div class="filtros-container">
        <form method="GET" action="">
            <div class="row">
                <div class="col">
                    <label><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" name="busqueda" placeholder="Nombre o descripción..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="col">
                    <label><i class="fas fa-filter"></i> Categoría</label>
                    <select name="filtro_categoria">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>" <?= $filtro_categoria == $cat['id_categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                        <a href="admin_servicios.php" class="btn-filtro"><i class="fas fa-undo"></i> Limpiar</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Botón Nuevo Servicio -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <span style="color: #8b949e; font-size: 0.9rem;">
            <i class="fas fa-list"></i> <?= count($servicios) ?> de <?= $total_registros ?> servicios encontrados
            <?php if (!empty($busqueda) || !empty($filtro_categoria) || !empty($filtro_estado)): ?>
                <span style="color: #c9a84c;">(filtros aplicados)</span>
            <?php endif; ?>
        </span>
        <button type="button" class="btn-crear" id="btnToggleFormulario" onclick="toggleFormulario()">
            <i class="fas fa-plus-circle" id="iconoBoton"></i>
            <span id="textoBoton">Nuevo Servicio</span>
        </button>
    </div>
    
    <!-- Formulario -->
    <div id="formularioServicio" class="form-servicio <?= $servicio_editar ? 'visible' : '' ?>">
        <h3><i class="fas <?= $servicio_editar ? 'fa-pencil-alt' : 'fa-plus-circle' ?>"></i> 
            <?= $servicio_editar ? 'Editar Servicio' : 'Nuevo Servicio' ?>
        </h3>
        <form method="POST">
            <?php if ($servicio_editar): ?>
                <input type="hidden" name="id_servicio" value="<?= $servicio_editar['id_servicio'] ?>">
            <?php endif; ?>
            
            <div class="fila-campos">
                <div class="campo-grupo">
                    <label><i class="fas fa-tag"></i> Nombre <span style="color: #f85149;">*</span></label>
                    <input type="text" name="nombre" 
                           value="<?= $servicio_editar ? htmlspecialchars($servicio_editar['nombre']) : '' ?>" 
                           required minlength="2" maxlength="100">
                </div>
                <div class="campo-grupo">
                    <label><i class="fas fa-tags"></i> Categoría <span style="color: #f85149;">*</span></label>
                    <select name="categoria" required>
                        <option value="">Seleccionar categoría</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>" 
                                <?= ($servicio_editar && $servicio_editar['id_categoria'] == $cat['id_categoria']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="fila-campos">
                <div class="campo-grupo">
                    <label><i class="fas fa-clock"></i> Duración <span style="color: #f85149;">*</span></label>
                    <input type="text" name="duracion" 
                           value="<?= $servicio_editar ? htmlspecialchars($servicio_editar['duracion']) : '' ?>" 
                           placeholder="Ej: 30 min, 1 h" required>
                </div>
                <div class="campo-grupo">
                    <label><i class="fas fa-dollar-sign"></i> Precio <span style="color: #f85149;">*</span></label>
                    <input type="number" step="0.01" name="precio" 
                           value="<?= $servicio_editar ? $servicio_editar['precio'] : '' ?>" 
                           min="0.01" required>
                </div>
            </div>
            
            <div class="campo-grupo" style="margin-bottom: 16px;">
                <label><i class="fas fa-align-left"></i> Descripción (opcional)</label>
                <textarea name="descripcion" rows="2"><?= $servicio_editar ? htmlspecialchars($servicio_editar['descripcion'] ?? '') : '' ?></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-modal-secondary" onclick="toggleFormulario()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" name="<?= $servicio_editar ? 'editar' : 'crear' ?>" class="btn-primario">
                    <i class="fas fa-save"></i> <?= $servicio_editar ? 'Actualizar' : 'Crear Servicio' ?>
                </button>
            </div>
        </form>
    </div>
    
    <!-- Lista de servicios -->
    <h3><i class="fas fa-list"></i> Servicios Existentes</h3>
    
    <?php if (empty($servicios)): ?>
        <div style="text-align: center; padding: 40px; color: #8b949e; background: #161b22; border-radius: 12px; border: 1px solid #30363d;">
            <i class="fas fa-inbox" style="font-size: 3rem; display: block; margin-bottom: 12px; color: #30363d;"></i>
            <?php if (!empty($busqueda) || !empty($filtro_categoria) || !empty($filtro_estado)): ?>
                No se encontraron servicios con los filtros aplicados.
            <?php else: ?>
                No hay servicios registrados.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grilla-servicios">
            <?php foreach ($servicios as $serv): ?>
            <div class="servicio-item">
                <div class="servicio-header">
                    <div class="servicio-info">
                        <span class="servicio-nombre"><?= htmlspecialchars($serv['nombre']) ?></span>
                        <span class="servicio-precio">$<?= number_format($serv['precio'], 2) ?></span>
                        <span class="servicio-duracion"><i class="far fa-clock"></i> <?= htmlspecialchars($serv['duracion']) ?></span>
                        <span class="servicio-categoria"><i class="fas fa-tag"></i> <?= htmlspecialchars($serv['categoria_nombre']) ?></span>
                        <span class="badge-estado <?= $serv['estado'] === 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                            <i class="fas <?= $serv['estado'] === 'activo' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                            <?= ucfirst($serv['estado']) ?>
                        </span>
                    </div>
                    <div class="servicio-acciones">
                        <a href="?editar=<?= $serv['id_servicio'] ?>&pagina=<?= $pagina_actual ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_categoria=<?= urlencode($filtro_categoria) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>" class="btn-accion btn-editar" title="Editar" onclick="toggleFormulario()">
                            <i class="fas fa-pencil-alt"></i>
                        </a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id_servicio" value="<?= $serv['id_servicio'] ?>">
                            <input type="hidden" name="nuevo_estado" value="<?= $serv['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                            <button type="submit" name="cambiar_estado" class="btn-accion <?= $serv['estado'] === 'activo' ? 'btn-desactivar' : 'btn-activar' ?>" title="<?= $serv['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>">
                                <i class="fas <?= $serv['estado'] === 'activo' ? 'fa-pause' : 'fa-play' ?>"></i>
                                <?= $serv['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este servicio?');">
                            <input type="hidden" name="id_servicio" value="<?= $serv['id_servicio'] ?>">
                            <button type="submit" name="eliminar" class="btn-accion btn-eliminar" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php if ($serv['descripcion']): ?>
                    <div class="servicio-desc"><i class="fas fa-align-left"></i> <?= htmlspecialchars($serv['descripcion']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="paginacion">
            <?php if ($pagina_actual > 1): ?>
                <a href="?pagina=<?= $pagina_actual - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_categoria=<?= urlencode($filtro_categoria) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>" class="btn-pagina">
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
                echo '<a href="?pagina=1&busqueda=' . urlencode($busqueda) . '&filtro_categoria=' . urlencode($filtro_categoria) . '&filtro_estado=' . urlencode($filtro_estado) . '" class="btn-pagina">1</a>';
                if ($inicio > 2) {
                    echo '<span class="btn-pagina deshabilitada">…</span>';
                }
            }
            
            for ($i = $inicio; $i <= $fin; $i++): ?>
                <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_categoria=<?= urlencode($filtro_categoria) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>" class="btn-pagina <?= $i === $pagina_actual ? 'activa' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor;
            
            if ($fin < $total_paginas) {
                if ($fin < $total_paginas - 1) {
                    echo '<span class="btn-pagina deshabilitada">…</span>';
                }
                echo '<a href="?pagina=' . $total_paginas . '&busqueda=' . urlencode($busqueda) . '&filtro_categoria=' . urlencode($filtro_categoria) . '&filtro_estado=' . urlencode($filtro_estado) . '" class="btn-pagina">' . $total_paginas . '</a>';
            }
            ?>
            
            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="?pagina=<?= $pagina_actual + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_categoria=<?= urlencode($filtro_categoria) ?>&filtro_estado=<?= urlencode($filtro_estado) ?>" class="btn-pagina">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="btn-pagina deshabilitada"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <div class="info-paginacion">
            Página <?= $pagina_actual ?> de <?= $total_paginas ?> (<?= $total_registros ?> servicios en total)
        </div>
        <?php endif; ?>
    <?php endif; ?>
    
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Gestión de Servicios</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

<script>
    function toggleFormulario() {
        const form = document.getElementById('formularioServicio');
        const btn = document.getElementById('btnToggleFormulario');
        const icono = document.getElementById('iconoBoton');
        const texto = document.getElementById('textoBoton');
        
        if (form.classList.contains('visible')) {
            form.classList.remove('visible');
            icono.className = 'fas fa-plus-circle';
            texto.textContent = 'Nuevo Servicio';
        } else {
            form.classList.add('visible');
            icono.className = 'fas fa-times-circle';
            texto.textContent = 'Cerrar formulario';
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['editar'])): ?>
            const form = document.getElementById('formularioServicio');
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