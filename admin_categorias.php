<?php
// Gestión de Categorías

session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('gestion_categorias');

$pdo = conectar();
$mensaje = '';
$error = '';
$busqueda = '';
$pagina_actual = 1;
$registros_por_pagina = 10;

// Obtener parámetros de búsqueda y paginación
if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
    $busqueda = trim($_GET['busqueda']);
}

if (isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0) {
    $pagina_actual = (int)$_GET['pagina'];
}

$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Procesar acciones POST (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // Crear
        if (isset($_POST['crear'])) {
            $nombre = trim($_POST['nombre']);
            
            if (empty($nombre)) throw new Exception('El nombre de la categoría es obligatorio.');
            if (strlen($nombre) < 2) throw new Exception('El nombre debe tener al menos 2 caracteres.');
            if (strlen($nombre) > 100) throw new Exception('El nombre no puede tener más de 100 caracteres.');
            
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE nombre = :nombre");
            $stmt_check->execute([':nombre' => $nombre]);
            if ($stmt_check->fetchColumn() > 0) throw new Exception('Ya existe una categoría con ese nombre.');
            
            $stmt = $pdo->prepare("INSERT INTO categorias (nombre, estado) VALUES (:nombre, 'activo')");
            $stmt->execute([':nombre' => $nombre]);
            $id_categoria = $pdo->lastInsertId();
            
            // Auditoría
            registrar_auditoria('crear', 'categorias', $id_categoria, null, $_POST);
            
            $mensaje = "Categoría creada correctamente.";
        }
        
        // Editar
        if (isset($_POST['editar'])) {
            $id = (int)$_POST['id_categoria'];
            $nombre = trim($_POST['nombre']);
            
            if (empty($nombre)) throw new Exception('El nombre de la categoría es obligatorio.');
            if (strlen($nombre) < 2) throw new Exception('El nombre debe tener al menos 2 caracteres.');
            if (strlen($nombre) > 100) throw new Exception('El nombre no puede tener más de 100 caracteres.');
            
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE nombre = :nombre AND id_categoria != :id");
            $stmt_check->execute([':nombre' => $nombre, ':id' => $id]);
            if ($stmt_check->fetchColumn() > 0) throw new Exception('Ya existe otra categoría con ese nombre.');
            
            // Guardar datos anteriores
            $stmt_ant = $pdo->prepare("SELECT * FROM categorias WHERE id_categoria = :id");
            $stmt_ant->execute([':id' => $id]);
            $datos_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("UPDATE categorias SET nombre = :nombre WHERE id_categoria = :id");
            $stmt->execute([':nombre' => $nombre, ':id' => $id]);
            
            // Auditoría
            registrar_auditoria('editar', 'categorias', $id, $datos_anteriores, $_POST);
            
            $mensaje = "Categoría actualizada correctamente.";
        }
        
        // Cambiar estado
        if (isset($_POST['cambiar_estado'])) {
            $id = (int)$_POST['id_categoria'];
            $nuevo_estado = $_POST['nuevo_estado'];
            
            // Guardar estado anterior
            $stmt_ant = $pdo->prepare("SELECT estado FROM categorias WHERE id_categoria = :id");
            $stmt_ant->execute([':id' => $id]);
            $estado_anterior = $stmt_ant->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE categorias SET estado = :estado WHERE id_categoria = :id");
            $stmt->execute([':estado' => $nuevo_estado, ':id' => $id]);
            
            // Auditoría
            registrar_auditoria('editar', 'categorias', $id, 
                ['estado' => $estado_anterior], 
                ['estado' => $nuevo_estado]
            );
            
            $mensaje = "Estado actualizado correctamente.";
        }
        
        // Eliminar
        if (isset($_POST['eliminar'])) {
            $id = (int)$_POST['id_categoria'];
            
            // Verificar servicios asociados
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM servicios WHERE id_categoria = :id");
            $stmt_check->execute([':id' => $id]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception('No se puede eliminar: la categoría tiene servicios asociados.');
            }
            
            // Verificar citas asociadas
            $stmt_check_citas = $pdo->prepare("
                SELECT COUNT(*) FROM detalle_cita dc
                JOIN servicios s ON s.id_servicio = dc.id_servicio
                WHERE s.id_categoria = :id
            ");
            $stmt_check_citas->execute([':id' => $id]);
            if ($stmt_check_citas->fetchColumn() > 0) {
                throw new Exception('No se puede eliminar: la categoría tiene citas asociadas.');
            }
            
            // Guardar datos antes de eliminar
            $stmt_ant = $pdo->prepare("SELECT * FROM categorias WHERE id_categoria = :id");
            $stmt_ant->execute([':id' => $id]);
            $datos_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM categorias WHERE id_categoria = :id");
            $stmt->execute([':id' => $id]);
            
            // Auditoría
            registrar_auditoria('eliminar', 'categorias', $id, $datos_anteriores, null);
            
            $mensaje = "Categoría eliminada correctamente.";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}

// Consulta con filtro y paginado

// Obtener el total de registros
$sql_count = "SELECT COUNT(*) as total FROM categorias WHERE 1=1";
$params_count = [];

if (!empty($busqueda)) {
    $sql_count .= " AND nombre LIKE :busqueda";
    $params_count[':busqueda'] = "%$busqueda%";
}

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params_count);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener los registros de la página actual
$sql = "SELECT * FROM categorias WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND nombre LIKE :busqueda";
    $params[':busqueda'] = "%$busqueda%";
}

$sql .= " ORDER BY nombre ASC LIMIT :offset, :limit";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);

if (!empty($busqueda)) {
    $stmt->bindValue(':busqueda', "%$busqueda%");
}

$stmt->execute();
$categorias = $stmt->fetchAll();

// Estadísticas
$stats = [
    'total' => $total_registros,
    'activas' => 0,
    'inactivas' => 0
];

$stmt_stats = $pdo->query("SELECT estado, COUNT(*) as cantidad FROM categorias GROUP BY estado");
while ($row = $stmt_stats->fetch()) {
    if ($row['estado'] === 'activo') {
        $stats['activas'] = $row['cantidad'];
    } else {
        $stats['inactivas'] = $row['cantidad'];
    }
}

// Categoría para editar
$categoria_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id_categoria = :id");
    $stmt->execute([':id' => $_GET['editar']]);
    $categoria_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Gestión de Categorías</title>
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
            margin-bottom: 24px;
            border-bottom: 2px solid #30363d;
            padding-bottom: 12px;
        }
        
        .contenedor-panel h2 i {
            color: #c9a84c;
            margin-right: 10px;
        }
        
        .contenedor-panel h3 {
            color: #e6edf3;
            font-size: 1.2rem;
            margin: 24px 0 16px 0;
        }
        
        .contenedor-panel h3 i {
            color: #c9a84c;
            margin-right: 8px;
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
        
        .form-servicio {
            background: #161b22;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #30363d;
            margin-bottom: 24px;
            display: none;
        }
        .form-servicio.visible {
            display: block;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-servicio h3 {
            color: #c9a84c;
            margin-bottom: 16px;
            font-size: 1.1rem;
        }
        
        .btn-crear {
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        .btn-crear:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
        }
        
        .fila-campos {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .campo-grupo {
            display: flex;
            flex-direction: column;
        }
        
        .campo-grupo label {
            color: #8b949e;
            font-size: 0.85rem;
            margin-bottom: 4px;
        }
        
        .campo-grupo input {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #30363d;
            background: #0d1117;
            color: #e6edf3;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .campo-grupo input:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.15);
        }
        
        .btn-primario {
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primario:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
        }
        .btn-modal-secondary {
            background: transparent;
            border: 1px solid #30363d;
            color: #8b949e;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .btn-modal-secondary:hover {
            border-color: #8b949e;
            color: #e6edf3;
        }
        
        .btn-accion {
            padding: 5px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.75rem;
            margin: 2px;
            transition: all 0.2s;
            font-weight: 600;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-accion:hover {
            transform: scale(1.05);
        }
        
        .btn-editar {
            background: #f39c12;
        }
        .btn-editar:hover {
            background: #e67e22;
        }
        
        .btn-desactivar {
            background: #e74c3c;
        }
        .btn-desactivar:hover {
            background: #c0392b;
        }
        
        .btn-activar {
            background: #2ecc71;
        }
        .btn-activar:hover {
            background: #27ae60;
        }
        
        .btn-eliminar {
            background: #e74c3c;
        }
        .btn-eliminar:hover {
            background: #c0392b;
        }
        
        .badge-estado {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-activo {
            background: #238636;
            color: white;
        }
        
        .badge-inactivo {
            background: #da3633;
            color: white;
        }
        
        .grilla-categorias {
            display: grid;
            gap: 12px;
            margin-top: 16px;
        }
        
        .categoria-item {
            background: #161b22;
            padding: 16px 20px;
            border-radius: 10px;
            border: 1px solid #30363d;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .categoria-item:hover {
            border-color: rgba(201, 168, 76, 0.3);
            transform: translateX(4px);
        }
        
        .categoria-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        
        .categoria-id {
            color: #8b949e;
            font-size: 0.8rem;
            font-weight: 600;
            min-width: 32px;
        }
        
        .categoria-nombre {
            font-size: 1.05rem;
            font-weight: 600;
            color: #e6edf3;
        }
        
        .categoria-acciones {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 10px;
            padding: 16px 20px;
            text-align: center;
        }
        
        .stat-card .numero {
            font-size: 2rem;
            font-weight: 700;
            color: #c9a84c;
        }
        
        .stat-card .etiqueta {
            color: #8b949e;
            font-size: 0.85rem;
            margin-top: 4px;
        }
        
        .filtro-container {
            background: #161b22;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #30363d;
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
            color: #8b949e;
            font-size: 0.8rem;
            margin-bottom: 4px;
        }
        
        .filtro-grupo input,
        .filtro-grupo select {
            width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #30363d;
            background: #0d1117;
            color: #e6edf3;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .filtro-grupo input:focus,
        .filtro-grupo select:focus {
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
            background: #c9a84c;
            color: #0d1117;
        }
        .btn-filtro-primary:hover {
            background: #b8943a;
        }
        
        .btn-filtro-secondary {
            background: #30363d;
            color: #8b949e;
        }
        .btn-filtro-secondary:hover {
            background: #484f58;
        }
        
        .paginacion {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        
        .paginacion .btn-pagina {
            padding: 6px 14px;
            border-radius: 6px;
            border: 1px solid #30363d;
            background: #161b22;
            color: #8b949e;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .paginacion .btn-pagina:hover {
            border-color: #c9a84c;
            color: #c9a84c;
        }
        
        .paginacion .btn-pagina.activa {
            background: #c9a84c;
            color: #0d1117;
            border-color: #c9a84c;
        }
        
        .paginacion .btn-pagina.deshabilitada {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .info-paginacion {
            text-align: center;
            color: #8b949e;
            font-size: 0.8rem;
            margin-top: 10px;
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
        
        .counter-resultados {
            color: #8b949e;
            font-size: 0.85rem;
            margin-bottom: 12px;
            display: inline-block;
        }
        
        @media (max-width: 768px) {
            .categoria-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .categoria-acciones {
                width: 100%;
                justify-content: flex-start;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .header-contenido {
                flex-wrap: wrap;
                gap: 10px;
            }
            .nav-header {
                flex-wrap: wrap;
                gap: 8px;
            }
            .filtro-container {
                flex-direction: column;
            }
            .filtro-grupo {
                min-width: auto;
                width: 100%;
            }
            .paginacion .btn-pagina {
                padding: 4px 10px;
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
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
            <a href="home.php" class="btn-outline-sm"><i class="fas fa-arrow-left"></i> Panel</a>
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2><i class="fas fa-tags"></i> Gestión de Categorías</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="numero"><?= $stats['total'] ?></div>
            <div class="etiqueta"><i class="fas fa-tags"></i> Total</div>
        </div>
        <div class="stat-card" style="border-color: #238636;">
            <div class="numero" style="color: #3fb950;"><?= $stats['activas'] ?></div>
            <div class="etiqueta"><i class="fas fa-check-circle"></i> Activas</div>
        </div>
        <div class="stat-card" style="border-color: #da3633;">
            <div class="numero" style="color: #f85149;"><?= $stats['inactivas'] ?></div>
            <div class="etiqueta"><i class="fas fa-times-circle"></i> Inactivas</div>
        </div>
    </div>
    
    <!-- Botón Nueva Categoría -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <span style="color: #8b949e; font-size: 0.9rem;">
            <i class="fas fa-list"></i> <?= count($categorias) ?> de <?= $total_registros ?> categorías encontradas
            <?php if (!empty($busqueda)): ?>
                <span style="color: #c9a84c;">(filtros aplicados)</span>
            <?php endif; ?>
        </span>
        <button type="button" class="btn-crear" id="btnToggleFormulario" onclick="toggleFormulario()">
            <i class="fas fa-plus-circle" id="iconoBoton"></i>
            <span id="textoBoton">Nueva Categoría</span>
        </button>
    </div>
    
    <!-- Formulario -->
    <div id="formularioCategoria" class="form-servicio <?= $categoria_editar ? 'visible' : '' ?>">
        <h3>
            <i class="fas <?= $categoria_editar ? 'fa-pencil-alt' : 'fa-plus-circle' ?>"></i>
            <?= $categoria_editar ? 'Editar Categoría' : 'Nueva Categoría' ?>
        </h3>
        <form method="POST">
            <?php if ($categoria_editar): ?>
                <input type="hidden" name="id_categoria" value="<?= $categoria_editar['id_categoria'] ?>">
            <?php endif; ?>
            <div class="fila-campos">
                <div class="campo-grupo">
                    <label><i class="fas fa-tag"></i> Nombre de la categoría <span style="color: #e74c3c;">*</span></label>
                    <input type="text" name="nombre" 
                           placeholder="Ej: Peluquería, Manicuría, Promociones" 
                           value="<?= $categoria_editar ? htmlspecialchars($categoria_editar['nombre']) : '' ?>"
                           required minlength="2" maxlength="100">
                    <small style="color: #8b949e; margin-top: 4px;">El nombre no puede repetirse con otra categoría existente.</small>
                </div>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                <button type="button" class="btn-modal-secondary" onclick="toggleFormulario()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" name="<?= $categoria_editar ? 'editar' : 'crear' ?>" class="btn-primario">
                    <i class="fas fa-save"></i> <?= $categoria_editar ? 'Actualizar' : 'Crear Categoría' ?>
                </button>
            </div>
        </form>
    </div>
    
    <h3><i class="fas fa-list"></i> Categorías Existentes</h3>
    
    <!-- Filtro -->
    <form method="GET" class="filtro-container">
        <div class="filtro-grupo">
            <label><i class="fas fa-search"></i> Buscar categoría</label>
            <input type="text" name="busqueda" placeholder="Ej: Peluquería, Uñas..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>
        <div class="filtro-grupo" style="flex: 0 0 auto;">
            <button type="submit" class="btn-filtro btn-filtro-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
            <a href="admin_categorias.php" class="btn-filtro btn-filtro-secondary">
                <i class="fas fa-undo"></i> Limpiar
            </a>
        </div>
    </form>
    
    <?php if (empty($categorias)): ?>
        <div class="alerta" style="background: rgba(88, 166, 255, 0.05); border: 1px solid #30363d; color: #8b949e; justify-content: center;">
            <i class="fas fa-info-circle"></i> 
            <?php if (!empty($busqueda)): ?>
                No se encontraron categorías con "<strong><?= htmlspecialchars($busqueda) ?></strong>".
            <?php else: ?>
                No hay categorías registradas. ¡Crea la primera!
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
            <span class="counter-resultados">
                <i class="fas fa-list"></i> Mostrando <?= count($categorias) ?> de <?= $total_registros ?> categorías
            </span>
        </div>
        
        <div class="grilla-categorias">
            <?php foreach ($categorias as $cat): ?>
            <div class="categoria-item">
                <div class="categoria-info">
                    <span class="categoria-id">#<?= $cat['id_categoria'] ?></span>
                    <span class="categoria-nombre"><?= htmlspecialchars($cat['nombre']) ?></span>
                    <span class="badge-estado <?= $cat['estado'] === 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                        <i class="fas <?= $cat['estado'] === 'activo' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                        <?= $cat['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                    </span>
                </div>
                <div class="categoria-acciones">
                    <a href="?editar=<?= $cat['id_categoria'] ?>&busqueda=<?= urlencode($busqueda) ?>&pagina=<?= $pagina_actual ?>" class="btn-accion btn-editar" title="Editar categoría" onclick="toggleFormulario()">
                        <i class="fas fa-pencil-alt"></i>
                    </a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id_categoria" value="<?= $cat['id_categoria'] ?>">
                        <input type="hidden" name="nuevo_estado" value="<?= $cat['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                        <button type="submit" name="cambiar_estado" class="btn-accion <?= $cat['estado'] === 'activo' ? 'btn-desactivar' : 'btn-activar' ?>" title="<?= $cat['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>">
                            <i class="fas <?= $cat['estado'] === 'activo' ? 'fa-pause' : 'fa-play' ?>"></i>
                            <?= $cat['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta categoría? Esta acción no se puede deshacer.');">
                        <input type="hidden" name="id_categoria" value="<?= $cat['id_categoria'] ?>">
                        <button type="submit" name="eliminar" class="btn-accion btn-eliminar" title="Eliminar categoría">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="paginacion">
            <?php if ($pagina_actual > 1): ?>
                <a href="?pagina=<?= $pagina_actual - 1 ?>&busqueda=<?= urlencode($busqueda) ?>" class="btn-pagina">
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
                echo '<a href="?pagina=1&busqueda=' . urlencode($busqueda) . '" class="btn-pagina">1</a>';
                if ($inicio > 2) {
                    echo '<span class="btn-pagina deshabilitada">…</span>';
                }
            }
            
            for ($i = $inicio; $i <= $fin; $i++): ?>
                <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>" class="btn-pagina <?= $i === $pagina_actual ? 'activa' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor;
            
            if ($fin < $total_paginas) {
                if ($fin < $total_paginas - 1) {
                    echo '<span class="btn-pagina deshabilitada">…</span>';
                }
                echo '<a href="?pagina=' . $total_paginas . '&busqueda=' . urlencode($busqueda) . '" class="btn-pagina">' . $total_paginas . '</a>';
            }
            ?>
            
            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="?pagina=<?= $pagina_actual + 1 ?>&busqueda=<?= urlencode($busqueda) ?>" class="btn-pagina">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="btn-pagina deshabilitada"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <div class="info-paginacion">
            Página <?= $pagina_actual ?> de <?= $total_paginas ?> (<?= $total_registros ?> categorías en total)
        </div>
        <?php endif; ?>
        
    <?php endif; ?>
    
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Gestión de Categorías</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

<script>
    function toggleFormulario() {
        const form = document.getElementById('formularioCategoria');
        const btn = document.getElementById('btnToggleFormulario');
        const icono = document.getElementById('iconoBoton');
        const texto = document.getElementById('textoBoton');
        
        if (form.classList.contains('visible')) {
            form.classList.remove('visible');
            icono.className = 'fas fa-plus-circle';
            texto.textContent = 'Nueva Categoría';
        } else {
            form.classList.add('visible');
            icono.className = 'fas fa-times-circle';
            texto.textContent = 'Cerrar formulario';
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['editar'])): ?>
            const form = document.getElementById('formularioCategoria');
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