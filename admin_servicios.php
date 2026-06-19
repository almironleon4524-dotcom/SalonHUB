<?php
session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('gestion_servicios');

$pdo = conectar();
$mensaje = '';
$error = '';

// Obtener categorías para el formulario
$categorias = $pdo->query("SELECT id_categoria, nombre FROM categorias WHERE estado = 'activo'")->fetchAll();

// Procesar formulario (Crear/Editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear'])) {
        $nombre = trim($_POST['nombre']);
        $categoria = (int)$_POST['categoria'];
        $duracion = trim($_POST['duracion']);
        $precio = (float)$_POST['precio'];
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        $stmt = $pdo->prepare("INSERT INTO servicios (id_categoria, nombre, descripcion, duracion, precio, estado) VALUES (:cat, :nom, :desc, :dur, :pre, 'activo')");
        if ($stmt->execute([':cat' => $categoria, ':nom' => $nombre, ':desc' => $descripcion, ':dur' => $duracion, ':pre' => $precio])) {
            $mensaje = "✅ Servicio creado correctamente.";
        } else {
            $error = "❌ Error al crear servicio.";
        }
    }
    
    if (isset($_POST['editar'])) {
        $id = (int)$_POST['id_servicio'];
        $nombre = trim($_POST['nombre']);
        $categoria = (int)$_POST['categoria'];
        $duracion = trim($_POST['duracion']);
        $precio = (float)$_POST['precio'];
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE servicios SET id_categoria = :cat, nombre = :nom, descripcion = :desc, duracion = :dur, precio = :pre WHERE id_servicio = :id");
        if ($stmt->execute([':cat' => $categoria, ':nom' => $nombre, ':desc' => $descripcion, ':dur' => $duracion, ':pre' => $precio, ':id' => $id])) {
            $mensaje = "✅ Servicio actualizado correctamente.";
        } else {
            $error = "❌ Error al actualizar servicio.";
        }
    }
    
    if (isset($_POST['cambiar_estado'])) {
        $id = (int)$_POST['id_servicio'];
        $nuevo_estado = $_POST['nuevo_estado'];
        
        $stmt = $pdo->prepare("UPDATE servicios SET estado = :estado WHERE id_servicio = :id");
        if ($stmt->execute([':estado' => $nuevo_estado, ':id' => $id])) {
            $mensaje = "✅ Estado actualizado.";
        } else {
            $error = "❌ Error al actualizar estado.";
        }
    }
}

// Listar servicios
$servicios = $pdo->query("
    SELECT s.*, c.nombre as categoria_nombre 
    FROM servicios s 
    JOIN categorias c ON c.id_categoria = s.id_categoria 
    ORDER BY s.id_servicio
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SalonHub - Gestionar Servicios</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .form-servicio { background: var(--loki-gris); padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .grilla-servicios { display: grid; gap: 15px; margin-top: 20px; }
        .servicio-item { background: rgba(255,255,255,0.03); padding: 15px; border-radius: 8px; border-left: 3px solid var(--loki-dorado); }
        .servicio-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .servicio-nombre { font-size: 1.1rem; font-weight: bold; color: var(--loki-dorado); }
        .servicio-precio { font-size: 1.2rem; font-weight: bold; color: #2ecc71; }
        .servicio-duracion { color: var(--texto-suave); font-size: 0.9rem; }
        .btn-edit { background: #3498db; color: white; padding: 5px 12px; border-radius: 5px; border: none; cursor: pointer; }
        .btn-toggle { background: #e67e22; color: white; padding: 5px 12px; border-radius: 5px; border: none; cursor: pointer; }
    </style>
</head>
<body>
<header class="header-panel">
    <div class="header-contenido">
        <h1 class="logo-header"><a href="home.php">✂ SalonHub</a></h1>
        <nav class="nav-header">
            <a href="home.php" class="btn btn-outline-sm">← Panel</a>
            <a href="cerrar_sesion.php" class="btn btn-outline-sm btn-danger-outline">Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2>💇 Gestionar Servicios</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- Formulario para crear nuevo servicio -->
    <div class="form-servicio">
        <h3>➕ Nuevo Servicio</h3>
        <form method="POST">
            <div class="fila-campos">
                <div class="campo-grupo">
                    <label>Nombre</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="campo-grupo">
                    <label>Categoría</label>
                    <select name="categoria" required>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="fila-campos">
                <div class="campo-grupo">
                    <label>Duración</label>
                    <input type="text" name="duracion" placeholder="Ej: 30 min, 1 hora" required>
                </div>
                <div class="campo-grupo">
                    <label>Precio ($)</label>
                    <input type="number" step="0.01" name="precio" required>
                </div>
            </div>
            <div class="campo-grupo">
                <label>Descripción (opcional)</label>
                <textarea name="descripcion" rows="2"></textarea>
            </div>
            <button type="submit" name="crear" class="btn btn-primario">Crear Servicio</button>
        </form>
    </div>
    
    <!-- Lista de servicios existentes -->
    <h3>📋 Servicios Existentes</h3>
    <div class="grilla-servicios">
        <?php foreach ($servicios as $serv): ?>
        <div class="servicio-item">
            <div class="servicio-header">
                <span class="servicio-nombre"><?= htmlspecialchars($serv['nombre']) ?></span>
                <span class="servicio-precio">$<?= number_format($serv['precio'], 2) ?></span>
            </div>
            <div class="servicio-header">
                <span class="servicio-duracion">⏱️ <?= htmlspecialchars($serv['duracion']) ?> | 📁 <?= htmlspecialchars($serv['categoria_nombre']) ?></span>
                <span>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="id_servicio" value="<?= $serv['id_servicio'] ?>">
                        <input type="hidden" name="nuevo_estado" value="<?= $serv['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                        <button type="submit" name="cambiar_estado" class="btn-toggle" style="background: <?= $serv['estado'] === 'activo' ? '#e74c3c' : '#2ecc71' ?>">
                            <?= $serv['estado'] === 'activo' ? '🔴 Desactivar' : '🟢 Activar' ?>
                        </button>
                    </form>
                </span>
            </div>
            <?php if ($serv['descripcion']): ?>
                <p style="font-size:0.85rem; color:var(--texto-suave); margin-top:8px;">📝 <?= htmlspecialchars($serv['descripcion']) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
