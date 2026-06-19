<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('gestion_usuarios');

$pdo = conectar();
$mensaje = '';
$error = '';

// ============================================================
// OBTENER DATOS PARA PERMISOS
// ============================================================
$permisos_disponibles = $pdo->query("SELECT * FROM permisos WHERE estado = 'activo' ORDER BY nombre_permiso")->fetchAll();
$usuario_seleccionado = null;
$permisos_actuales = [];

if (isset($_GET['permisos_id']) && is_numeric($_GET['permisos_id'])) {
    $id_permiso = (int)$_GET['permisos_id'];
    $stmt = $pdo->prepare("SELECT id_usuario, usuario, rol FROM usuario WHERE id_usuario = :id");
    $stmt->execute([':id' => $id_permiso]);
    $usuario_seleccionado = $stmt->fetch();
    if ($usuario_seleccionado) {
        $permisos_actuales = obtener_todos_permisos($id_permiso);
    }
}

// ============================================================
// GUARDAR PERMISOS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_permisos']) && isset($_POST['usuario_id'])) {
    $usuario_id = (int)$_POST['usuario_id'];
    try {
        $pdo->beginTransaction();
        
        $permisos_json = [];
        foreach ($permisos_disponibles as $permiso) {
            $nombre = $permiso['nombre_permiso'];
            $permisos_json[$nombre] = isset($_POST['permiso_' . $nombre]) ? 1 : 0;
        }
        
        $json_string = json_encode($permisos_json);
        
        $stmt = $pdo->prepare("UPDATE usuario SET permisos = :permisos WHERE id_usuario = :id");
        $stmt->execute([':permisos' => $json_string, ':id' => $usuario_id]);
        
        $pdo->commit();
        $mensaje = "Permisos actualizados correctamente.";
        
        // Recargar datos
        $permisos_actuales = obtener_todos_permisos($usuario_id);
        $stmt = $pdo->prepare("SELECT id_usuario, usuario, rol FROM usuario WHERE id_usuario = :id");
        $stmt->execute([':id' => $usuario_id]);
        $usuario_seleccionado = $stmt->fetch();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al guardar permisos: " . $e->getMessage();
    }
}

// ============================================================
// CREAR NUEVO USUARIO (ALTA)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_usuario'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rol = $_POST['rol'] ?? 'cliente';
    $pass = $_POST['contrasena'] ?? '';
    $pass2 = $_POST['confirmar_pass'] ?? '';
    
    $errores_crear = [];
    
    if (empty($nombre) || strlen($nombre) < 2) $errores_crear[] = "Nombre inválido";
    if (empty($apellido) || strlen($apellido) < 2) $errores_crear[] = "Apellido inválido";
    if (empty($usuario) || strlen($usuario) < 4) $errores_crear[] = "Usuario inválido (mínimo 4 caracteres)";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores_crear[] = "Email inválido";
    if (strlen($pass) < 8) $errores_crear[] = "Contraseña: mínimo 8 caracteres";
    if (!preg_match('/[A-Z]/', $pass)) $errores_crear[] = "Contraseña: falta una mayúscula";
    if (!preg_match('/[0-9]/', $pass)) $errores_crear[] = "Contraseña: falta un número";
    if ($pass !== $pass2) $errores_crear[] = "Las contraseñas no coinciden";
    
    if (empty($errores_crear)) {
        try {
            $pdo->beginTransaction();
            
            $chk = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE usuario = :u OR email = :e");
            $chk->execute([':u' => $usuario, ':e' => $email]);
            if ($chk->fetchColumn() > 0) {
                throw new Exception("El usuario o email ya existe");
            }
            
            $stmt = $pdo->prepare("INSERT INTO persona (nombre, apellido, email) VALUES (:nombre, :apellido, :email)");
            $stmt->execute([':nombre' => $nombre, ':apellido' => $apellido, ':email' => $email]);
            $id_persona = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO cliente (id_persona, fecha_inicio, estado_cliente) VALUES (:id_persona, CURDATE(), 'activo')");
            $stmt->execute([':id_persona' => $id_persona]);
            $id_cliente = $pdo->lastInsertId();
            
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO usuario (id_cliente, usuario, email, contrasena, estado, rol, email_verificado) VALUES (:id_cliente, :usuario, :email, :contrasena, 'activo', :rol, 1)");
            $stmt->execute([':id_cliente' => $id_cliente, ':usuario' => $usuario, ':email' => $email, ':contrasena' => $hash, ':rol' => $rol]);
            
            $pdo->commit();
            $mensaje = "✅ Usuario creado correctamente.";
            header("Refresh:0");
            
        } catch (Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            $error = "❌ " . $e->getMessage();
        }
    } else {
        $error = implode(", ", $errores_crear);
    }
}

// ============================================================
// CAMBIAR ROL DE USUARIO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_rol'])) {
    $id_usuario = (int)$_POST['id_usuario'];
    $nuevo_rol = $_POST['nuevo_rol'];
    
    $stmt = $pdo->prepare("UPDATE usuario SET rol = :rol WHERE id_usuario = :id");
    if ($stmt->execute([':rol' => $nuevo_rol, ':id' => $id_usuario])) {
        $mensaje = "Rol actualizado correctamente.";
        header("Refresh:0");
    } else {
        $error = "Error al actualizar rol.";
    }
}

// ============================================================
// CAMBIAR ESTADO (ACTIVAR/DESACTIVAR)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    $id_usuario = (int)$_POST['id_usuario'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    try {
        $stmt = $pdo->prepare("UPDATE usuario SET estado = :estado WHERE id_usuario = :id");
        $stmt->execute([':estado' => $nuevo_estado, ':id' => $id_usuario]);
        $mensaje = "Estado actualizado correctamente.";
        header("Refresh:0");
    } catch (PDOException $e) {
        $error = "Error al actualizar estado.";
    }
}

// ============================================================
// LISTAR USUARIOS
// ============================================================
$usuarios = $pdo->query("
    SELECT u.id_usuario, u.usuario, u.email, u.rol, u.estado,
           p.nombre, p.apellido
    FROM usuario u
    LEFT JOIN cliente c ON c.id_cliente = u.id_cliente
    LEFT JOIN persona p ON p.id_persona = c.id_persona
    ORDER BY u.id_usuario
")->fetchAll();

// ============================================================
// LISTAR EMPLEADOS
// ============================================================
$empleados = $pdo->query("
    SELECT e.id_empleado, e.especialidad,
           p.nombre, p.apellido, p.telefono, p.email,
           u.id_usuario, u.usuario, u.estado
    FROM empleado e
    JOIN persona p ON p.id_persona = e.id_persona
    JOIN usuario u ON u.id_usuario = e.id_usuario
    ORDER BY e.id_empleado
")->fetchAll();

// Obtener pestaña activa
$tab_activa = $_GET['tab'] ?? 'usuarios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SalonHub - Administración Central</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--borde);
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: var(--texto-suave);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border-radius: 10px 10px 0 0;
        }
        .tab-btn:hover {
            color: var(--loki-dorado);
            background: rgba(201, 168, 76, 0.1);
        }
        .tab-btn.active {
            color: var(--loki-dorado);
            border-bottom: 3px solid var(--loki-dorado);
        }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .tabla-responsive { overflow-x: auto; }
        .tabla-datos { width: 100%; border-collapse: collapse; background: var(--loki-gris); border-radius: 10px; }
        .tabla-datos th, .tabla-datos td { padding: 12px; text-align: left; border-bottom: 1px solid var(--borde); }
        .tabla-datos th { background: rgba(201, 168, 76, 0.1); color: var(--loki-dorado); }
        .btn-accion { padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.8rem; margin: 2px; }
        .btn-rol { background: #3498db; color: white; }
        .btn-activo { background: #2ecc71; color: white; }
        .btn-inactivo { background: #e74c3c; color: white; }
        .btn-permisos { background: var(--loki-dorado); color: var(--loki-negro); }
        .btn-crear { background: var(--loki-verde); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .formulario-crear { background: var(--loki-gris); padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid var(--borde); }
        .estado-activo { color: #2ecc71; font-weight: bold; }
        .estado-inactivo { color: #e74c3c; font-weight: bold; }
        .grilla-empleados { display: grid; gap: 15px; margin-top: 20px; }
        .empleado-card { background: var(--loki-gris); border-radius: 10px; padding: 20px; border-left: 4px solid var(--loki-verde); }
        .empleado-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; }
        .empleado-nombre { font-size: 1.2rem; font-weight: bold; color: var(--loki-verde-claro); }
        .fila-campos { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .campo-grupo { display: flex; flex-direction: column; }
        .campo-grupo input, .campo-grupo select { padding: 10px; border-radius: 8px; border: 1px solid var(--borde); background: var(--loki-negro); color: white; }
        .grilla-permisos { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; margin: 20px 0; }
        .permiso-item { background: rgba(255,255,255,0.03); padding: 12px; border-radius: 8px; display: flex; gap: 12px; border: 1px solid var(--borde); }
        .selector-usuario-permisos { margin-bottom: 20px; padding: 15px; background: rgba(201, 168, 76, 0.1); border-radius: 10px; }
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
    <h2>Centro de Administración</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab-btn <?= $tab_activa === 'usuarios' ? 'active' : '' ?>" data-tab="usuarios">Usuarios</button>
        <button class="tab-btn <?= $tab_activa === 'empleados' ? 'active' : '' ?>" data-tab="empleados">Empleados</button>
        <button class="tab-btn <?= $tab_activa === 'permisos' ? 'active' : '' ?>" data-tab="permisos">Permisos</button>
    </div>
    
    <!-- PESTAÑA USUARIOS -->
    <div id="tab-usuarios" class="tab-pane <?= $tab_activa === 'usuarios' ? 'active' : '' ?>">
        <div class="formulario-crear">
            <h3>➕ Crear Nuevo Usuario</h3>
            <form method="POST">
                <div class="fila-campos">
                    <div class="campo-grupo"><label>Nombre *</label><input type="text" name="nombre" required></div>
                    <div class="campo-grupo"><label>Apellido *</label><input type="text" name="apellido" required></div>
                </div>
                <div class="fila-campos">
                    <div class="campo-grupo"><label>Usuario *</label><input type="text" name="usuario" required></div>
                    <div class="campo-grupo"><label>Email *</label><input type="email" name="email" required></div>
                </div>
                <div class="fila-campos">
                    <div class="campo-grupo">
                        <label>Rol *</label>
                        <select name="rol">
                            <option value="cliente">Cliente</option>
                            <option value="empleado">Empleado</option>
                            <option value="administrador">Administrador</option>
                        </select>
                    </div>
                    <div class="campo-grupo"><label>Contraseña *</label><input type="password" name="contrasena" required></div>
                </div>
                <div class="fila-campos">
                    <div class="campo-grupo"><label>Confirmar Contraseña *</label><input type="password" name="confirmar_pass" required></div>
                </div>
                <button type="submit" name="crear_usuario" class="btn-crear">Crear Usuario</button>
            </form>
        </div>
        
        <div class="tabla-responsive">
            <table class="tabla-datos">
                <thead><tr><th>ID</th><th>Usuario</th><th>Email</th><th>Nombre</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($usuarios as $user): ?>
                    <tr>
                        <td><?= $user['id_usuario'] ?></td>
                        <td><?= htmlspecialchars($user['usuario']) ?></td>
                        <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')) ?></td>
                        <td><span class="badge-<?= $user['rol'] ?>"><?= ucfirst($user['rol']) ?></span></td>
                        <td class="<?= $user['estado'] === 'activo' ? 'estado-activo' : 'estado-inactivo' ?>"><?= ucfirst($user['estado']) ?></td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="id_usuario" value="<?= $user['id_usuario'] ?>">
                                <input type="hidden" name="nuevo_rol" value="<?= $user['rol'] === 'cliente' ? 'empleado' : ($user['rol'] === 'empleado' ? 'administrador' : 'cliente') ?>">
                                <button type="submit" name="cambiar_rol" class="btn-accion btn-rol">Rol</button>
                            </form>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="id_usuario" value="<?= $user['id_usuario'] ?>">
                                <input type="hidden" name="nuevo_estado" value="<?= $user['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                <button type="submit" name="cambiar_estado" class="btn-accion <?= $user['estado'] === 'activo' ? 'btn-inactivo' : 'btn-activo' ?>"><?= $user['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <a href="?tab=permisos&permisos_id=<?= $user['id_usuario'] ?>" class="btn-accion btn-permisos">Permisos</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- PESTAÑA EMPLEADOS -->
    <div id="tab-empleados" class="tab-pane <?= $tab_activa === 'empleados' ? 'active' : '' ?>">
        <div class="grilla-empleados">
            <?php if (empty($empleados)): ?>
                <div class="alerta alerta-info">No hay empleados registrados.</div>
            <?php else: ?>
                <?php foreach ($empleados as $emp): ?>
                <div class="empleado-card">
                    <div class="empleado-header">
                        <span class="empleado-nombre"><?= htmlspecialchars($emp['nombre'] . ' ' . $emp['apellido']) ?></span>
                        <div>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="id_usuario" value="<?= $emp['id_usuario'] ?>">
                                <input type="hidden" name="nuevo_estado" value="<?= $emp['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                <button type="submit" name="cambiar_estado" class="btn-accion" style="background: <?= $emp['estado'] === 'activo' ? '#e74c3c' : '#2ecc71' ?>; color:white;"><?= $emp['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <a href="?tab=permisos&permisos_id=<?= $emp['id_usuario'] ?>" class="btn-accion btn-permisos">Permisos</a>
                        </div>
                    </div>
                    <div>Usuario: <?= htmlspecialchars($emp['usuario']) ?></div>
                    <div>Email: <?= htmlspecialchars($emp['email'] ?? 'No registrado') ?></div>
                    <div>Especialidad: <?= htmlspecialchars($emp['especialidad'] ?? 'General') ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- PESTAÑA PERMISOS -->
    <div id="tab-permisos" class="tab-pane <?= $tab_activa === 'permisos' ? 'active' : '' ?>">
        <?php if ($usuario_seleccionado): ?>
            <h3>Permisos de: <?= htmlspecialchars($usuario_seleccionado['usuario']) ?> (<?= ucfirst($usuario_seleccionado['rol']) ?>)</h3>
            <form method="POST">
                <input type="hidden" name="usuario_id" value="<?= $usuario_seleccionado['id_usuario'] ?>">
                <div class="grilla-permisos">
                    <?php foreach ($permisos_disponibles as $permiso): ?>
                    <div class="permiso-item">
                        <input type="checkbox" name="permiso_<?= $permiso['nombre_permiso'] ?>" id="perm_<?= $permiso['nombre_permiso'] ?>" <?= ($permisos_actuales[$permiso['nombre_permiso']] ?? false) ? 'checked' : '' ?>>
                        <label for="perm_<?= $permiso['nombre_permiso'] ?>">
                            <strong><?= str_replace('_', ' ', ucfirst($permiso['nombre_permiso'])) ?></strong><br>
                            <small><?= htmlspecialchars($permiso['descripcion'] ?? 'Sin descripción') ?></small>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="guardar_permisos" class="btn btn-primario">💾 Guardar Permisos</button>
            </form>
        <?php else: ?>
            <div class="selector-usuario-permisos">
                <h3>Seleccionar Usuario</h3>
                <select onchange="window.location.href='?tab=permisos&permisos_id='+this.value">
                    <option value="">-- Seleccione un usuario --</option>
                    <?php foreach ($usuarios as $user): ?>
                        <option value="<?= $user['id_usuario'] ?>"><?= htmlspecialchars($user['usuario']) ?> (<?= ucfirst($user['rol']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabName = this.getAttribute('data-tab');
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        if (tabName !== 'permisos') url.searchParams.delete('permisos_id');
        window.history.pushState({}, '', url);
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.getElementById(`tab-${tabName}`).classList.add('active');
    });
});
</script>
</body>
</html>
