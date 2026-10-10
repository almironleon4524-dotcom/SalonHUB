<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Gestión de Productos

session_start();
require_once 'conexion.php';
require_once 'permisos_helper.php';
require_once 'notificaciones_helper.php';

// Seguridad
requerir_permiso('gestion_productos');

// Variables globales
$pdo = conectar();
$mensaje = $_SESSION['mensaje'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['error']);

$busqueda = $_GET['busqueda'] ?? '';
$filtro_tipo = $_GET['filtro_tipo'] ?? '';
$filtro_stock = $_GET['filtro_stock'] ?? '';
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        switch ($accion) {
            case 'crear':
                $nombre_producto = trim($_POST['nombre_producto'] ?? '');
                if (empty($nombre_producto)) {
                    throw new Exception('El nombre del producto es obligatorio');
                }
                if (strlen($nombre_producto) < 3) {
                    throw new Exception('El nombre debe tener al menos 3 caracteres');
                }
                
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE nombre_producto = :nombre");
                $stmt_check->execute([':nombre' => $nombre_producto]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('Ya existe un producto con ese nombre.');
                }
                
                $precio_venta = floatval($_POST['precio_venta'] ?? 0);
                if ($precio_venta <= 0) {
                    throw new Exception('El precio de venta debe ser mayor a 0');
                }
                
                $stock_actual = intval($_POST['stock_actual'] ?? 0);
                if ($stock_actual < 0) {
                    throw new Exception('El stock no puede ser negativo');
                }
                
                $sql = "INSERT INTO productos (
                    nombre_producto, descripcion, stock_actual, stock_minimo, 
                    precio_compra, precio_venta, tipo_producto, id_proveedor, id_marca
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nombre_producto,
                    trim($_POST['descripcion'] ?? ''),
                    $stock_actual,
                    intval($_POST['stock_minimo'] ?? 0),
                    floatval($_POST['precio_compra'] ?? 0),
                    $precio_venta,
                    $_POST['tipo_producto'] ?? 'consumible',
                    !empty($_POST['id_proveedor']) ? intval($_POST['id_proveedor']) : null,
                    !empty($_POST['id_marca']) ? intval($_POST['id_marca']) : null
                ]);
                
                $id_producto = $pdo->lastInsertId();
                
                // Auditoría
                registrar_auditoria('crear', 'productos', $id_producto, null, $_POST);
                
                $_SESSION['mensaje'] = 'Producto creado exitosamente';
                break;
                
            case 'editar':
                $id_producto = intval($_POST['id_producto'] ?? 0);
                if ($id_producto <= 0) {
                    throw new Exception('ID de producto inválido');
                }
                
                // Guardar datos anteriores
                $stmt_ant = $pdo->prepare("SELECT * FROM productos WHERE id_producto = ?");
                $stmt_ant->execute([$id_producto]);
                $datos_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);
                
                $nombre_producto = trim($_POST['nombre_producto'] ?? '');
                if (empty($nombre_producto)) {
                    throw new Exception('El nombre del producto es obligatorio');
                }
                
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE nombre_producto = :nombre AND id_producto != :id");
                $stmt_check->execute([':nombre' => $nombre_producto, ':id' => $id_producto]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('Ya existe otro producto con ese nombre.');
                }
                
                $precio_venta = floatval($_POST['precio_venta'] ?? 0);
                if ($precio_venta <= 0) {
                    throw new Exception('El precio de venta debe ser mayor a 0');
                }
                
                $sql = "UPDATE productos SET 
                    nombre_producto = ?,
                    descripcion = ?,
                    stock_actual = ?,
                    stock_minimo = ?,
                    precio_compra = ?,
                    precio_venta = ?,
                    tipo_producto = ?,
                    id_proveedor = ?,
                    id_marca = ?
                WHERE id_producto = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nombre_producto,
                    trim($_POST['descripcion'] ?? ''),
                    intval($_POST['stock_actual'] ?? 0),
                    intval($_POST['stock_minimo'] ?? 0),
                    floatval($_POST['precio_compra'] ?? 0),
                    $precio_venta,
                    $_POST['tipo_producto'] ?? 'consumible',
                    !empty($_POST['id_proveedor']) ? intval($_POST['id_proveedor']) : null,
                    !empty($_POST['id_marca']) ? intval($_POST['id_marca']) : null,
                    $id_producto
                ]);
                
                // Auditoría
                registrar_auditoria('editar', 'productos', $id_producto, $datos_anteriores, $_POST);
                
                // Notificación si el stock quedó bajo
                $stmt_prod = $pdo->prepare("SELECT nombre_producto, stock_actual, stock_minimo FROM productos WHERE id_producto = ?");
                $stmt_prod->execute([$id_producto]);
                $prod = $stmt_prod->fetch(PDO::FETCH_ASSOC);
                
                if ($prod) {
                    if ($prod['stock_actual'] == 0) {
                        notificar_stock_agotado($id_producto, $prod['nombre_producto']);
                    } elseif ($prod['stock_actual'] <= $prod['stock_minimo']) {
                        notificar_stock_bajo($id_producto, $prod['nombre_producto'], $prod['stock_actual']);
                    }
                }
                
                $_SESSION['mensaje'] = 'Producto actualizado exitosamente';
                break;
                
            case 'eliminar':
                $id_producto = intval($_POST['id_producto'] ?? 0);
                
                $sql_check = "SELECT COUNT(*) FROM detalle_servicio WHERE id_servicio = ?";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$id_producto]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception('No se puede eliminar: el producto está siendo usado en servicios');
                }
                
                $sql_check_mov = "SELECT COUNT(*) FROM movimiento_stock WHERE id_producto = ?";
                $stmt_check_mov = $pdo->prepare($sql_check_mov);
                $stmt_check_mov->execute([$id_producto]);
                if ($stmt_check_mov->fetchColumn() > 0) {
                    throw new Exception('No se puede eliminar: el producto tiene movimientos de stock');
                }
                
                // Guardar datos antes de eliminar
                $stmt_ant = $pdo->prepare("SELECT * FROM productos WHERE id_producto = ?");
                $stmt_ant->execute([$id_producto]);
                $datos_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);
                
                $sql = "DELETE FROM productos WHERE id_producto = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_producto]);
                
                // Auditoría
                registrar_auditoria('eliminar', 'productos', $id_producto, $datos_anteriores, null);
                
                $_SESSION['mensaje'] = 'Producto eliminado exitosamente';
                break;
                
            case 'actualizar_stock':
                $id_producto = intval($_POST['id_producto'] ?? 0);
                $nuevo_stock = intval($_POST['nuevo_stock'] ?? 0);
                $motivo = trim($_POST['motivo'] ?? 'Ajuste manual de stock');
                
                if ($nuevo_stock < 0) {
                    throw new Exception('El stock no puede ser negativo');
                }
                
                $sql_stock = "SELECT stock_actual FROM productos WHERE id_producto = ?";
                $stmt_stock = $pdo->prepare($sql_stock);
                $stmt_stock->execute([$id_producto]);
                $stock_anterior = $stmt_stock->fetchColumn();
                
                $sql_update = "UPDATE productos SET stock_actual = ? WHERE id_producto = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$nuevo_stock, $id_producto]);
                
                $sql_mov = "INSERT INTO movimiento_stock (
                    id_producto, tipo_movimiento, cantidad_movida, 
                    cantidad_total, fecha_hora, motivo
                ) VALUES (?, ?, ?, ?, NOW(), ?)";
                $stmt_mov = $pdo->prepare($sql_mov);
                $cantidad_movida = $nuevo_stock - $stock_anterior;
                $stmt_mov->execute([
                    $id_producto,
                    $cantidad_movida > 0 ? 'entrada' : 'salida',
                    abs($cantidad_movida),
                    $nuevo_stock,
                    $motivo
                ]);
                
                // Auditoría
                registrar_auditoria('editar', 'productos', $id_producto, 
                    ['stock_actual' => $stock_anterior], 
                    ['stock_actual' => $nuevo_stock, 'motivo' => $motivo]
                );
                
                // Notificación de stock
                $stmt_prod = $pdo->prepare("SELECT nombre_producto, stock_actual, stock_minimo FROM productos WHERE id_producto = ?");
                $stmt_prod->execute([$id_producto]);
                $prod = $stmt_prod->fetch(PDO::FETCH_ASSOC);
                
                if ($prod) {
                    if ($prod['stock_actual'] == 0) {
                        notificar_stock_agotado($id_producto, $prod['nombre_producto']);
                    } elseif ($prod['stock_actual'] <= $prod['stock_minimo']) {
                        notificar_stock_bajo($id_producto, $prod['nombre_producto'], $prod['stock_actual']);
                    }
                }
                
                $_SESSION['mensaje'] = 'Stock actualizado exitosamente';
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error en la base de datos: ' . $e->getMessage();
    }
    
    $params = [];
    if (!empty($busqueda)) $params[] = 'busqueda=' . urlencode($busqueda);
    if (!empty($filtro_tipo)) $params[] = 'filtro_tipo=' . urlencode($filtro_tipo);
    if (!empty($filtro_stock)) $params[] = 'filtro_stock=' . urlencode($filtro_stock);
    if ($pagina_actual > 1) $params[] = 'pagina=' . $pagina_actual;
    
    $redirect = 'admin_productos.php' . (count($params) > 0 ? '?' . implode('&', $params) : '');
    header('Location: ' . $redirect);
    exit;
}

// Obtener datos

// Contar total de registros
$sql_count = "SELECT COUNT(*) as total FROM productos p WHERE 1=1";
$params_count = [];

if (!empty($busqueda)) {
    $sql_count .= " AND (p.nombre_producto LIKE ? OR p.descripcion LIKE ?)";
    $params_count[] = "%$busqueda%";
    $params_count[] = "%$busqueda%";
}

if (!empty($filtro_tipo)) {
    $sql_count .= " AND p.tipo_producto = ?";
    $params_count[] = $filtro_tipo;
}

if ($filtro_stock === 'bajo') {
    $sql_count .= " AND p.stock_actual <= p.stock_minimo AND p.stock_actual > 0";
} elseif ($filtro_stock === 'sin_stock') {
    $sql_count .= " AND p.stock_actual = 0";
} elseif ($filtro_stock === 'stock_normal') {
    $sql_count .= " AND p.stock_actual > p.stock_minimo";
}

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params_count);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener productos de la página actual
$sql_productos = "SELECT p.*, pr.razon_social as nombre_proveedor, m.nombre as nombre_marca
                  FROM productos p
                  LEFT JOIN proveedor pr ON p.id_proveedor = pr.id_proveedor
                  LEFT JOIN marcas m ON p.id_marca = m.id_marca
                  WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql_productos .= " AND (p.nombre_producto LIKE ? OR p.descripcion LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if (!empty($filtro_tipo)) {
    $sql_productos .= " AND p.tipo_producto = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_stock === 'bajo') {
    $sql_productos .= " AND p.stock_actual <= p.stock_minimo AND p.stock_actual > 0";
} elseif ($filtro_stock === 'sin_stock') {
    $sql_productos .= " AND p.stock_actual = 0";
} elseif ($filtro_stock === 'stock_normal') {
    $sql_productos .= " AND p.stock_actual > p.stock_minimo";
}

$sql_productos .= " ORDER BY p.nombre_producto LIMIT ? OFFSET ?";
$params[] = $registros_por_pagina;
$params[] = $offset;

$stmt_productos = $pdo->prepare($sql_productos);
$stmt_productos->execute($params);
$productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

// Proveedores activos
$sql_proveedores = "SELECT id_proveedor, razon_social 
                    FROM proveedor 
                    WHERE estado = 'activo' 
                    ORDER BY razon_social";
$stmt_proveedores = $pdo->prepare($sql_proveedores);
$stmt_proveedores->execute();
$proveedores = $stmt_proveedores->fetchAll(PDO::FETCH_ASSOC);

// Marcas activas
$sql_marcas = "SELECT id_marca, nombre 
               FROM marcas 
               WHERE estado = 'activo' 
               ORDER BY nombre";
$stmt_marcas = $pdo->prepare($sql_marcas);
$stmt_marcas->execute();
$marcas = $stmt_marcas->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN stock_actual <= stock_minimo AND stock_actual > 0 THEN 1 ELSE 0 END) as stock_bajo,
    SUM(CASE WHEN stock_actual = 0 THEN 1 ELSE 0 END) as sin_stock
    FROM productos";
$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Producto para editar
$producto_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $sql_editar = "SELECT * FROM productos WHERE id_producto = ?";
    $stmt_editar = $pdo->prepare($sql_editar);
    $stmt_editar->execute([$_GET['editar']]);
    $producto_editar = $stmt_editar->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Gestión de Productos</title>
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
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
        .stat-card .icono { font-size: 1.5rem; margin-bottom: 4px; }
        .stat-card .numero { font-size: 2rem; font-weight: 700; color: #e6edf3; }
        .stat-card .etiqueta { color: #8b949e; font-size: 0.8rem; margin-top: 2px; }
        .stat-card.primary .icono { color: #58a6ff; } .stat-card.primary { border-top: 3px solid #58a6ff; }
        .stat-card.warning .icono { color: #d29922; } .stat-card.warning { border-top: 3px solid #d29922; }
        .stat-card.danger .icono { color: #f85149; } .stat-card.danger { border-top: 3px solid #f85149; }
        .stat-card.success .icono { color: #3fb950; } .stat-card.success { border-top: 3px solid #3fb950; }
        
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
        
        .tabla-responsive { overflow-x: auto; border-radius: 12px; border: 1px solid #30363d; }
        .tabla-datos { width: 100%; border-collapse: collapse; background: #161b22; }
        .tabla-datos th { padding: 12px 16px; text-align: left; background: rgba(201,168,76,0.05); color: #c9a84c; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #30363d; }
        .tabla-datos td { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #e6edf3; font-size: 0.9rem; }
        .tabla-datos tbody tr:hover { background: rgba(255,255,255,0.02); }
        
        .badge-stock { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge-stock-success { background: rgba(46,160,67,0.15); color: #3fb950; }
        .badge-stock-warning { background: rgba(210,153,34,0.15); color: #d29922; }
        .badge-stock-danger { background: rgba(248,81,73,0.15); color: #f85149; }
        .badge-tipo { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-tipo-consumible { background: rgba(88,166,255,0.15); color: #58a6ff; }
        .badge-tipo-servicio { background: rgba(46,160,67,0.15); color: #3fb950; }
        .badge-tipo-equipamiento { background: rgba(210,153,34,0.15); color: #d29922; }
        
        .btn-accion { padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.75rem; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; color: white; }
        .btn-accion:hover { transform: scale(1.05); }
        .btn-ver { background: #58a6ff; } .btn-ver:hover { background: #3b8fd4; }
        .btn-editar { background: #d29922; } .btn-editar:hover { background: #b8943a; }
        .btn-stock { background: #3fb950; } .btn-stock:hover { background: #2ea043; }
        .btn-eliminar { background: #f85149; } .btn-eliminar:hover { background: #da3633; }
        
        .btn-crear { background: linear-gradient(135deg, #c9a84c, #b8943a); color: #0d1117; padding: 8px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; }
        .btn-crear:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(201,168,76,0.3); }
        
        .formulario-toggle { display: none; background: #161b22; padding: 20px; border-radius: 12px; border: 1px solid #30363d; margin-bottom: 24px; }
        .formulario-toggle.visible { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .formulario-toggle h3 { color: #c9a84c; margin-bottom: 16px; font-size: 1.1rem; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; color: #8b949e; font-size: 0.85rem; margin-bottom: 4px; }
        .form-group label i { color: #c9a84c; margin-right: 4px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #30363d; background: #0d1117; color: #e6edf3; font-size: 0.95rem; transition: all 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        
        .btn-modal { padding: 8px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 0.9rem; }
        .btn-modal-secondary { background: transparent; border: 1px solid #30363d; color: #8b949e; }
        .btn-modal-secondary:hover { border-color: #8b949e; color: #e6edf3; }
        .btn-modal-primary { background: linear-gradient(135deg, #c9a84c, #b8943a); color: #0d1117; }
        .btn-modal-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(201,168,76,0.3); }
        
        .modal-content { background: #161b22; color: #e6edf3; border: 1px solid #30363d; }
        .modal-header { border-bottom: 1px solid #30363d; }
        .modal-footer { border-top: 1px solid #30363d; }
        .btn-close { filter: invert(1); }
        
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
    <h2><i class="fas fa-box"></i> Gestión de Productos</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="icono"><i class="fas fa-box"></i></div>
            <div class="numero"><?= $stats['total'] ?></div>
            <div class="etiqueta">Total Productos</div>
        </div>
        <div class="stat-card warning">
            <div class="icono"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="numero"><?= $stats['stock_bajo'] ?></div>
            <div class="etiqueta">Stock Bajo</div>
        </div>
        <div class="stat-card danger">
            <div class="icono"><i class="fas fa-times-circle"></i></div>
            <div class="numero"><?= $stats['sin_stock'] ?></div>
            <div class="etiqueta">Sin Stock</div>
        </div>
        <div class="stat-card success">
            <div class="icono"><i class="fas fa-check-circle"></i></div>
            <div class="numero"><?= $stats['total'] - $stats['stock_bajo'] - $stats['sin_stock'] ?></div>
            <div class="etiqueta">Stock Normal</div>
        </div>
    </div>
    
    <div class="filtros-container">
        <form method="GET" action="">
            <div class="row">
                <div class="col">
                    <label><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" name="busqueda" placeholder="Nombre o descripción..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="col">
                    <label><i class="fas fa-filter"></i> Tipo</label>
                    <select name="filtro_tipo">
                        <option value="">Todos</option>
                        <option value="consumible" <?= $filtro_tipo === 'consumible' ? 'selected' : '' ?>>Consumible</option>
                        <option value="servicio" <?= $filtro_tipo === 'servicio' ? 'selected' : '' ?>>Para Servicio</option>
                        <option value="equipamiento" <?= $filtro_tipo === 'equipamiento' ? 'selected' : '' ?>>Equipamiento</option>
                    </select>
                </div>
                <div class="col">
                    <label><i class="fas fa-boxes"></i> Stock</label>
                    <select name="filtro_stock">
                        <option value="">Todos</option>
                        <option value="bajo" <?= $filtro_stock === 'bajo' ? 'selected' : '' ?>>Stock Bajo</option>
                        <option value="sin_stock" <?= $filtro_stock === 'sin_stock' ? 'selected' : '' ?>>Sin Stock</option>
                        <option value="stock_normal" <?= $filtro_stock === 'stock_normal' ? 'selected' : '' ?>>Stock Normal</option>
                    </select>
                </div>
                <div class="col" style="flex: 0 0 auto;">
                    <label>&nbsp;</label>
                    <div class="btn-group">
                        <button type="submit" class="btn-filtro primary"><i class="fas fa-search"></i> Buscar</button>
                        <a href="admin_productos.php" class="btn-filtro"><i class="fas fa-undo"></i> Limpiar</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <span style="color: #8b949e; font-size: 0.9rem;">
            <i class="fas fa-list"></i> <?= count($productos) ?> de <?= $total_registros ?> productos encontrados
            <?php if (!empty($busqueda) || !empty($filtro_tipo) || !empty($filtro_stock)): ?>
                <span style="color: #c9a84c;">(filtros aplicados)</span>
            <?php endif; ?>
        </span>
        <button type="button" class="btn-crear" id="btnToggleFormulario" onclick="toggleFormulario()">
            <i class="fas fa-plus-circle" id="iconoBoton"></i>
            <span id="textoBoton">Nuevo Producto</span>
        </button>
    </div>
    
    <div id="formularioProducto" class="formulario-toggle <?= $producto_editar ? 'visible' : '' ?>">
        <h3>
            <i class="fas <?= $producto_editar ? 'fa-pencil-alt' : 'fa-plus-circle' ?>"></i>
            <?= $producto_editar ? 'Editar Producto' : 'Nuevo Producto' ?>
        </h3>
        <form method="POST" action="">
            <input type="hidden" name="accion" value="<?= $producto_editar ? 'editar' : 'crear' ?>">
            <?php if ($producto_editar): ?>
                <input type="hidden" name="id_producto" value="<?= $producto_editar['id_producto'] ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nombre del Producto <span style="color: #f85149;">*</span></label>
                    <input type="text" name="nombre_producto" 
                           value="<?= $producto_editar ? htmlspecialchars($producto_editar['nombre_producto']) : '' ?>" 
                           required minlength="3" maxlength="80">
                    <small style="color: #8b949e;">Mínimo 3 caracteres. El nombre no puede repetirse.</small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-tags"></i> Tipo <span style="color: #f85149;">*</span></label>
                    <select name="tipo_producto" required>
                        <option value="consumible" <?= ($producto_editar && $producto_editar['tipo_producto'] === 'consumible') ? 'selected' : '' ?>>Consumible</option>
                        <option value="servicio" <?= ($producto_editar && $producto_editar['tipo_producto'] === 'servicio') ? 'selected' : '' ?>>Para Servicio</option>
                        <option value="equipamiento" <?= ($producto_editar && $producto_editar['tipo_producto'] === 'equipamiento') ? 'selected' : '' ?>>Equipamiento</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Descripción</label>
                <textarea name="descripcion" rows="2" maxlength="200"><?= $producto_editar ? htmlspecialchars($producto_editar['descripcion'] ?? '') : '' ?></textarea>
                <small style="color: #8b949e;">Máximo 200 caracteres.</small>
            </div>
            
            <div class="form-row-3">
                <div class="form-group">
                    <label><i class="fas fa-boxes"></i> Stock Actual</label>
                    <input type="number" name="stock_actual" value="<?= $producto_editar ? $producto_editar['stock_actual'] : 0 ?>" min="0">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-exclamation-triangle"></i> Stock Mínimo</label>
                    <input type="number" name="stock_minimo" value="<?= $producto_editar ? $producto_editar['stock_minimo'] : 5 ?>" min="0">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> Precio Venta <span style="color: #f85149;">*</span></label>
                    <input type="number" step="0.01" name="precio_venta" value="<?= $producto_editar ? $producto_editar['precio_venta'] : 0 ?>" min="0.01" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Precio de Compra</label>
                    <input type="number" step="0.01" name="precio_compra" value="<?= $producto_editar ? $producto_editar['precio_compra'] : 0 ?>" min="0">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-truck"></i> Proveedor</label>
                    <select name="id_proveedor">
                        <option value="">Sin proveedor</option>
                        <?php foreach ($proveedores as $proveedor): ?>
                            <option value="<?= $proveedor['id_proveedor'] ?>" 
                                <?= ($producto_editar && $producto_editar['id_proveedor'] == $proveedor['id_proveedor']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proveedor['razon_social']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-copyright"></i> Marca</label>
                <select name="id_marca">
                    <option value="">Sin marca</option>
                    <?php foreach ($marcas as $marca): ?>
                        <option value="<?= $marca['id_marca'] ?>" 
                            <?= ($producto_editar && $producto_editar['id_marca'] == $marca['id_marca']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($marca['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #8b949e;">
                    <i class="fas fa-info-circle"></i> 
                    <a href="admin_marcas.php" style="color: #c9a84c; text-decoration: none;" target="_blank">
                        Gestionar marcas
                    </a>
                </small>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="toggleFormulario()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn-modal btn-modal-primary">
                    <i class="fas fa-save"></i> <?= $producto_editar ? 'Actualizar' : 'Crear' ?>
                </button>
            </div>
        </form>
    </div>
    
    <div class="tabla-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Stock</th>
                    <th>Precio Venta</th>
                    <th>Proveedor</th>
                    <th>Marca</th>
                    <th>Tipo</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($productos)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #8b949e;">
                            <i class="fas fa-inbox" style="font-size: 3rem; display: block; margin-bottom: 12px; color: #30363d;"></i>
                            <?php if (!empty($busqueda) || !empty($filtro_tipo) || !empty($filtro_stock)): ?>
                                No se encontraron productos con los filtros aplicados.
                            <?php else: ?>
                                No hay productos registrados.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($productos as $producto): 
                        $stock = $producto['stock_actual'];
                        $minimo = $producto['stock_minimo'];
                        if ($stock == 0) {
                            $stock_class = 'badge-stock-danger';
                            $stock_icon = 'fa-times-circle';
                        } elseif ($stock <= $minimo) {
                            $stock_class = 'badge-stock-warning';
                            $stock_icon = 'fa-exclamation-triangle';
                        } else {
                            $stock_class = 'badge-stock-success';
                            $stock_icon = 'fa-check-circle';
                        }
                        
                        $tipo_class = 'badge-tipo-' . ($producto['tipo_producto'] ?? 'consumible');
                    ?>
                        <tr>
                            <td style="font-weight: 600; color: #8b949e;">#<?= $producto['id_producto'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($producto['nombre_producto']) ?></strong>
                                <br><small style="color: #8b949e;"><?= htmlspecialchars(substr($producto['descripcion'] ?? '', 0, 50)) ?></small>
                            </td>
                            <td>
                                <span class="badge-stock <?= $stock_class ?>">
                                    <i class="fas <?= $stock_icon ?>"></i> <?= $stock ?>
                                </span>
                                <br><small style="color: #8b949e;">Mín: <?= $minimo ?></small>
                            </td>
                            <td><strong>$<?= number_format($producto['precio_venta'], 2) ?></strong></td>
                            <td><?= htmlspecialchars($producto['nombre_proveedor'] ?? 'Sin proveedor') ?></td>
                            <td>
                                <?php if ($producto['nombre_marca']): ?>
                                    <span style="color: #c9a84c;"><?= htmlspecialchars($producto['nombre_marca']) ?></span>
                                <?php else: ?>
                                    <span style="color: #8b949e; font-size: 0.8rem;">Sin marca</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-tipo <?= $tipo_class ?>">
                                    <?= ucfirst($producto['tipo_producto'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">
                                    <button type="button" class="btn-accion btn-ver" data-bs-toggle="modal" data-bs-target="#modalVerProducto<?= $producto['id_producto'] ?>" title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="?editar=<?= $producto['id_producto'] ?>&pagina=<?= $pagina_actual ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_tipo=<?= urlencode($filtro_tipo) ?>&filtro_stock=<?= urlencode($filtro_stock) ?>" class="btn-accion btn-editar" title="Editar" onclick="toggleFormulario()">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <button type="button" class="btn-accion btn-stock" data-bs-toggle="modal" data-bs-target="#modalStock<?= $producto['id_producto'] ?>" title="Actualizar stock">
                                        <i class="fas fa-plus-minus"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este producto?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_producto" value="<?= $producto['id_producto'] ?>">
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
            <a href="?pagina=<?= $pagina_actual - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_tipo=<?= urlencode($filtro_tipo) ?>&filtro_stock=<?= urlencode($filtro_stock) ?>" class="btn-pagina">
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
            echo '<a href="?pagina=1&busqueda=' . urlencode($busqueda) . '&filtro_tipo=' . urlencode($filtro_tipo) . '&filtro_stock=' . urlencode($filtro_stock) . '" class="btn-pagina">1</a>';
            if ($inicio > 2) {
                echo '<span class="btn-pagina deshabilitada">…</span>';
            }
        }
        
        for ($i = $inicio; $i <= $fin; $i++): ?>
            <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_tipo=<?= urlencode($filtro_tipo) ?>&filtro_stock=<?= urlencode($filtro_stock) ?>" class="btn-pagina <?= $i === $pagina_actual ? 'activa' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor;
        
        if ($fin < $total_paginas) {
            if ($fin < $total_paginas - 1) {
                echo '<span class="btn-pagina deshabilitada">…</span>';
            }
            echo '<a href="?pagina=' . $total_paginas . '&busqueda=' . urlencode($busqueda) . '&filtro_tipo=' . urlencode($filtro_tipo) . '&filtro_stock=' . urlencode($filtro_stock) . '" class="btn-pagina">' . $total_paginas . '</a>';
        }
        ?>
        
        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="?pagina=<?= $pagina_actual + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_tipo=<?= urlencode($filtro_tipo) ?>&filtro_stock=<?= urlencode($filtro_stock) ?>" class="btn-pagina">
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php else: ?>
            <span class="btn-pagina deshabilitada"><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
    </div>
    <div class="info-paginacion">
        Página <?= $pagina_actual ?> de <?= $total_paginas ?> (<?= $total_registros ?> productos en total)
    </div>
    <?php endif; ?>
    
    <!-- Modales -->
    <?php foreach ($productos as $producto): ?>
    <div class="modal fade" id="modalVerProducto<?= $producto['id_producto'] ?>" tabindex="-1" aria-hidden="true" style="display:none;">
        <div class="modal-dialog">
            <div class="modal-content" style="background: #161b22; color: #e6edf3; border: 1px solid #30363d;">
                <div class="modal-header" style="border-bottom: 1px solid #30363d;">
                    <h5 class="modal-title" style="color: #c9a84c;">
                        <i class="fas fa-info-circle"></i> Detalle del Producto
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <?php 
                    $stmt_modal = $pdo->prepare("SELECT p.*, pr.razon_social as nombre_proveedor, pr.telefono, pr.email, m.nombre as nombre_marca FROM productos p LEFT JOIN proveedor pr ON p.id_proveedor = pr.id_proveedor LEFT JOIN marcas m ON p.id_marca = m.id_marca WHERE p.id_producto = ?");
                    $stmt_modal->execute([$producto['id_producto']]);
                    $producto_detalle = $stmt_modal->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <?php if ($producto_detalle): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px;">
                            <div><strong style="color: #8b949e;">ID:</strong> <?= $producto_detalle['id_producto'] ?></div>
                            <div><strong style="color: #8b949e;">Nombre:</strong> <?= htmlspecialchars($producto_detalle['nombre_producto']) ?></div>
                            <div style="grid-column: span 2;"><strong style="color: #8b949e;">Descripción:</strong> <?= htmlspecialchars($producto_detalle['descripcion'] ?? 'Sin descripción') ?></div>
                            <div><strong style="color: #8b949e;">Tipo:</strong> <?= ucfirst($producto_detalle['tipo_producto'] ?? 'N/A') ?></div>
                            <div><strong style="color: #8b949e;">Stock Actual:</strong> <?= $producto_detalle['stock_actual'] ?></div>
                            <div><strong style="color: #8b949e;">Stock Mínimo:</strong> <?= $producto_detalle['stock_minimo'] ?></div>
                            <div><strong style="color: #8b949e;">Precio Compra:</strong> $<?= number_format($producto_detalle['precio_compra'], 2) ?></div>
                            <div><strong style="color: #8b949e;">Precio Venta:</strong> $<?= number_format($producto_detalle['precio_venta'], 2) ?></div>
                            <div><strong style="color: #8b949e;">Proveedor:</strong>
                                <?php if ($producto_detalle['nombre_proveedor']): ?>
                                    <?= htmlspecialchars($producto_detalle['nombre_proveedor']) ?>
                                    <br><small style="color: #8b949e;">Tel: <?= $producto_detalle['telefono'] ?? 'N/A' ?></small>
                                <?php else: ?>
                                    Sin proveedor
                                <?php endif; ?>
                            </div>
                            <div><strong style="color: #8b949e;">Marca:</strong>
                                <?= htmlspecialchars($producto_detalle['nombre_marca'] ?? 'Sin marca') ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #8b949e;">No se encontró el producto</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #30363d;">
                    <button type="button" class="btn-modal btn-modal-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalStock<?= $producto['id_producto'] ?>" tabindex="-1" aria-hidden="true" style="display:none;">
        <div class="modal-dialog">
            <div class="modal-content" style="background: #161b22; color: #e6edf3; border: 1px solid #30363d;">
                <div class="modal-header" style="border-bottom: 1px solid #30363d;">
                    <h5 class="modal-title" style="color: #c9a84c;">
                        <i class="fas fa-boxes"></i> Actualizar Stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="actualizar_stock">
                        <input type="hidden" name="id_producto" value="<?= $producto['id_producto'] ?>">
                        
                        <div style="text-align: center; padding: 10px; margin-bottom: 16px; background: rgba(201, 168, 76, 0.05); border-radius: 8px;">
                            <strong style="color: #c9a84c;"><?= htmlspecialchars($producto['nombre_producto']) ?></strong>
                            <br><small style="color: #8b949e;">Stock actual: <strong><?= $producto['stock_actual'] ?></strong></small>
                        </div>
                        
                        <div class="form-group">
                            <label>Nuevo Stock</label>
                            <input type="number" name="nuevo_stock" value="<?= $producto['stock_actual'] ?>" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Motivo del ajuste</label>
                            <input type="text" name="motivo" placeholder="Ej: Compra, Devolución, Ajuste..." required>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #30363d;">
                        <button type="button" class="btn-modal btn-modal-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cancelar</button>
                        <button type="submit" class="btn-modal btn-modal-primary">
                            <i class="fas fa-save"></i> Actualizar Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Gestión de Productos</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function toggleFormulario() {
        const form = document.getElementById('formularioProducto');
        const btn = document.getElementById('btnToggleFormulario');
        const icono = document.getElementById('iconoBoton');
        const texto = document.getElementById('textoBoton');
        
        if (form.classList.contains('visible')) {
            form.classList.remove('visible');
            icono.className = 'fas fa-plus-circle';
            texto.textContent = 'Nuevo Producto';
        } else {
            form.classList.add('visible');
            icono.className = 'fas fa-times-circle';
            texto.textContent = 'Cerrar formulario';
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['editar'])): ?>
            const form = document.getElementById('formularioProducto');
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