<?php
session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('gestion_categorias');

$pdo = conectar();
$mensaje = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear'])) {
        $nombre = trim($_POST['nombre']);
        
        $stmt = $pdo->prepare("INSERT INTO categorias (nombre, estado) VALUES (:nom, 'activo')");
        if ($stmt->execute([':nom' => $nombre])) {
            $mensaje = "✅ Categoría creada correctamente.";
        } else {
            $error = "❌ Error al crear categoría.";
        }
    }
    
    if (isset($_POST['cambiar_estado'])) {
        $id = (int)$_POST['id_categoria'];
        $nuevo_estado = $_POST['nuevo_estado'];
        
        $stmt = $pdo->prepare("UPDATE categorias SET estado = :estado WHERE id_categoria = :id");
        if ($stmt->execute([':estado' => $nuevo_estado, ':id' => $id])) {
            $mensaje = "✅ Estado actualizado.";
        } else {
            $error = "❌ Error al actualizar estado.";
        }
    }
}

// Listar categorías
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY id_categoria")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SalonHub - Gestionar Categorías</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .grilla-categorias { display: grid; gap: 15px; margin-top: 20px; }
        .categoria-item { background: rgba(255,255,255,0.03); padding: 15px 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .categoria-nombre { font-size: 1.1rem; font-weight: bold; }
        .badge-estado { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; }
        .badge-activo { background: #2ecc71; color: white; }
        .badge-inactivo { background: #e74c3c; color: white; }
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
    <h2>📁 Gestionar Categorías</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- Formulario para crear nueva categoría -->
    <div class="form-servicio">
        <h3>➕ Nueva Categoría</h3>
        <form method="POST">
            <div class="fila-campos">
                <div class="campo-grupo">
                    <label>Nombre de la categoría</label>
                    <input type="text" name="nombre" placeholder="Ej: Peluquería, Manicuría, Promociones" required>
                </div>
            </div>
            <button type="submit" name="crear" class="btn btn-primario">Crear Categoría</button>
        </form>
    </div>
    
    <!-- Lista de categorías existentes -->
    <h3>📋 Categorías Existentes</h3>
    <div class="grilla-categorias">
        <?php foreach ($categorias as $cat): ?>
        <div class="categoria-item">
            <span class="categoria-nombre">📂 <?= htmlspecialchars($cat['nombre']) ?></span>
            <div>
                <span class="badge-estado <?= $cat['estado'] === 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                    <?= $cat['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                </span>
                <form method="POST" style="display:inline; margin-left: 10px;">
                    <input type="hidden" name="id_categoria" value="<?= $cat['id_categoria'] ?>">
                    <input type="hidden" name="nuevo_estado" value="<?= $cat['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                    <button type="submit" name="cambiar_estado" class="btn-accion" style="background: <?= $cat['estado'] === 'activo' ? '#e74c3c' : '#2ecc71' ?>; color:white;">
                        <?= $cat['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
