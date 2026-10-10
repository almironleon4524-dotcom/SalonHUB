<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);



//  SalonHub - ABM Usuarios y Permisos

//  Módulo de Administración Central



session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('gestion_usuarios');

$pdo = conectar();
$mensaje = '';
$error = '';



// OBTENER DATOS PARA PERMISOS



$usuario_seleccionado = null;
$permisos_actuales = [];

if (isset($_GET['permisos_id']) && is_numeric($_GET['permisos_id'])) {
    $id_permiso = (int)$_GET['permisos_id'];
    $stmt = $pdo->prepare("SELECT id_usuario, usuario, rol FROM usuario WHERE id_usuario = :id");
    $stmt->execute([':id' => $id_permiso]);
    $usuario_seleccionado = $stmt->fetch();
    if ($usuario_seleccionado) {
        // Usar la nueva función que obtiene permisos de JSON
        $permisos_actuales = obtener_todos_permisos($id_permiso);
    }
}



// GUARDAR PERMISOS (VERSIÓN ACTUALIZADA)



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_permisos']) && isset($_POST['usuario_id'])) {
    $usuario_id = (int)$_POST['usuario_id'];
    
    $todos_los_permisos = obtener_lista_permisos();
    $permisos_json = [];
    
    foreach ($todos_los_permisos as $permiso) {
        $permisos_json[$permiso] = isset($_POST['permiso_' . $permiso]) ? 1 : 0;
    }
    
    if (guardar_permisos_personalizados($usuario_id, $permisos_json)) {
        $mensaje = "✅ Permisos actualizados correctamente.";
        $permisos_actuales = obtener_todos_permisos($usuario_id);
        $stmt = $pdo->prepare("SELECT id_usuario, usuario, rol FROM usuario WHERE id_usuario = :id");
        $stmt->execute([':id' => $usuario_id]);
        $usuario_seleccionado = $stmt->fetch();
    } else {
        $error = "❌ Error al guardar los permisos.";
    }
}



// CREAR NUEVO USUARIO (ALTA)



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
            
            // Verificar si usuario o email ya existen
            $chk = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE usuario = :u OR email = :e");
            $chk->execute([':u' => $usuario, ':e' => $email]);
            if ($chk->fetchColumn() > 0) {
                throw new Exception("El usuario o email ya existe");
            }
            
            // Crear persona
            $stmt = $pdo->prepare("INSERT INTO persona (nombre, apellido, email) VALUES (:nombre, :apellido, :email)");
            $stmt->execute([':nombre' => $nombre, ':apellido' => $apellido, ':email' => $email]);
            $id_persona = $pdo->lastInsertId();
            
            // Crear cliente
            $stmt = $pdo->prepare("INSERT INTO cliente (id_persona, fecha_inicio, estado_cliente) VALUES (:id_persona, CURDATE(), 'activo')");
            $stmt->execute([':id_persona' => $id_persona]);
            $id_cliente = $pdo->lastInsertId();
            
            // Crear usuario
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



// CAMBIAR ROL DE USUARIO



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_rol'])) {
    $id_usuario = (int)$_POST['id_usuario'];
    $nuevo_rol = $_POST['nuevo_rol'];
    
    $stmt = $pdo->prepare("UPDATE usuario SET rol = :rol WHERE id_usuario = :id");
    if ($stmt->execute([':rol' => $nuevo_rol, ':id' => $id_usuario])) {
        $mensaje = "✅ Rol actualizado correctamente.";
        header("Refresh:0");
    } else {
        $error = "❌ Error al actualizar rol.";
    }
}



// CAMBIAR ESTADO (ACTIVAR/DESACTIVAR)



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    $id_usuario = (int)$_POST['id_usuario'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    try {
        $stmt = $pdo->prepare("UPDATE usuario SET estado = :estado WHERE id_usuario = :id");
        $stmt->execute([':estado' => $nuevo_estado, ':id' => $id_usuario]);
        $mensaje = "✅ Estado actualizado correctamente.";
        header("Refresh:0");
    } catch (PDOException $e) {
        $error = "❌ Error al actualizar estado.";
    }
}



// LISTAR USUARIOS



$usuarios = $pdo->query("
    SELECT u.id_usuario, u.usuario, u.email, u.rol, u.estado,
           p.nombre, p.apellido
    FROM usuario u
    LEFT JOIN cliente c ON c.id_cliente = u.id_cliente
    LEFT JOIN persona p ON p.id_persona = c.id_persona
    ORDER BY u.id_usuario
")->fetchAll();



// LISTAR EMPLEADOS



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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Administración Central</title>
    <link rel="stylesheet" href="css/estilos.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>



        /* ESTILO GENERAL PREDETERMINADO        */


        
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
        


        /* ALERTAS                                            */


        
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
        
        .alerta-info {
            background: rgba(88, 166, 255, 0.15);
            border: 1px solid #58a6ff;
            color: #58a6ff;
        }
        


        /* TABS                                               */


        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 30px;
            border-bottom: 2px solid #30363d;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: #8b949e;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border-radius: 10px 10px 0 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn i {
            color: #8b949e;
            transition: all 0.3s;
        }
        
        .tab-btn:hover {
            color: #c9a84c;
            background: rgba(201, 168, 76, 0.05);
        }
        
        .tab-btn:hover i {
            color: #c9a84c;
        }
        
        .tab-btn.active {
            color: #c9a84c;
            border-bottom: 3px solid #c9a84c;
        }
        
        .tab-btn.active i {
            color: #c9a84c;
        }
        
        .tab-pane {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        


        /* FORMULARIO CREAR USUARIO                           */


        
        .formulario-crear {
            background: #161b22;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #30363d;
        }
        
        .formulario-crear h3 {
            color: #c9a84c;
            margin-bottom: 16px;
            font-size: 1.1rem;
        }
        
        .fila-campos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
        
        .campo-grupo input,
        .campo-grupo select {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #30363d;
            background: #0d1117;
            color: #e6edf3;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .campo-grupo input:focus,
        .campo-grupo select:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.15);
        }
        
        .btn-crear {
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
            padding: 12px 28px;
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
        
        .btn-crear:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
        }
        


        /* TABLAS                                             */


        
        .tabla-responsive {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #30363d;
        }
        
        .tabla-datos {
            width: 100%;
            border-collapse: collapse;
            background: #161b22;
        }
        
        .tabla-datos th,
        .tabla-datos td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #30363d;
        }
        
        .tabla-datos th {
            background: rgba(201, 168, 76, 0.08);
            color: #c9a84c;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tabla-datos tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .tabla-datos td {
            color: #e6edf3;
        }
        


        /* BADGES                                             */


        
        .badge-rol {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-administrador {
            background: #c9a84c;
            color: #0d1117;
        }
        
        .badge-empleado {
            background: #238636;
            color: #fff;
        }
        
        .badge-cliente {
            background: #4a5568;
            color: #fff;
        }
        

        
        /* ACCIONES                                           */

        
        
        .btn-accion {
            padding: 5px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.75rem;
            margin: 2px;
            transition: all 0.2s;
            font-weight: 600;
        }
        
        .btn-accion:hover {
            transform: scale(1.05);
        }
        
        .btn-rol {
            background: #3498db;
            color: white;
        }
        
        .btn-rol:hover {
            background: #2980b9;
        }
        
        .btn-activo {
            background: #2ecc71;
            color: white;
        }
        
        .btn-activo:hover {
            background: #27ae60;
        }
        
        .btn-inactivo {
            background: #e74c3c;
            color: white;
        }
        
        .btn-inactivo:hover {
            background: #c0392b;
        }
        
        .btn-permisos {
            background: #c9a84c;
            color: #0d1117;
        }
        
        .btn-permisos:hover {
            background: #b8943a;
        }
        
        .estado-activo {
            color: #2ecc71;
            font-weight: bold;
        }
        
        .estado-inactivo {
            color: #e74c3c;
            font-weight: bold;
        }
        

        
        /* EMPLEADOS CARD                                     */

        
        
        .grilla-empleados {
            display: grid;
            gap: 16px;
            margin-top: 20px;
        }
        
        .empleado-card {
            background: #161b22;
            border-radius: 12px;
            padding: 20px 24px;
            border-left: 4px solid #238636;
            border: 1px solid #30363d;
            transition: all 0.3s;
        }
        
        .empleado-card:hover {
            border-color: #c9a84c;
            transform: translateX(4px);
        }
        
        .empleado-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .empleado-nombre {
            font-size: 1.2rem;
            font-weight: bold;
            color: #3fb950;
        }
        
        .empleado-card > div {
            color: #8b949e;
            font-size: 0.9rem;
            margin-top: 4px;
        }
        

        
        /* PERMISOS                                           */

        
        
        .selector-usuario-permisos {
            margin-bottom: 20px;
            padding: 20px;
            background: rgba(201, 168, 76, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(201, 168, 76, 0.2);
        }
        
        .selector-usuario-permisos h3 {
            color: #c9a84c;
            margin-bottom: 12px;
        }
        
        .selector-usuario-permisos select {
            padding: 10px 16px;
            border-radius: 8px;
            border: 1px solid #30363d;
            background: #0d1117;
            color: #e6edf3;
            font-size: 1rem;
            min-width: 250px;
        }
        
        .grilla-permisos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
            margin: 20px 0;
        }
        
        .permiso-item {
            background: rgba(255, 255, 255, 0.02);
            padding: 12px 16px;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid #30363d;
            transition: all 0.3s;
        }
        
        .permiso-item:hover {
            border-color: rgba(201, 168, 76, 0.3);
            background: rgba(255, 255, 255, 0.04);
        }
        
        .permiso-item input[type="checkbox"] {
            margin-top: 3px;
            accent-color: #c9a84c;
            width: 18px;
            height: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .permiso-item label {
            cursor: pointer;
            flex: 1;
        }
        
        .permiso-item label strong {
            color: #e6edf3;
            display: block;
            font-size: 0.9rem;
        }
        
        .permiso-item label small {
            color: #8b949e;
            font-size: 0.75rem;
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
        

        
        /* RESPONSIVE                                          */

        

        @media (max-width: 768px) {
            .fila-campos {
                grid-template-columns: 1fr;
            }
            
            .grilla-permisos {
                grid-template-columns: 1fr;
            }
            
            .tabla-datos {
                font-size: 0.85rem;
            }
            
            .tabla-datos th,
            .tabla-datos td {
                padding: 8px 10px;
            }
            
            .empleado-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .contenedor-panel h2 {
                font-size: 1.4rem;
            }
            
            .header-contenido {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .nav-header {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .tab-btn {
                padding: 10px 16px;
                font-size: 0.85rem;
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
    <h2><i class="fas fa-user-cog"></i> Centro de Administración</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    

    
    <!-- TABS                                               -->

    
    
    <div class="tabs">
        <button class="tab-btn <?= $tab_activa === 'usuarios' ? 'active' : '' ?>" data-tab="usuarios">
            <i class="fas fa-users"></i> Usuarios
        </button>
        <button class="tab-btn <?= $tab_activa === 'empleados' ? 'active' : '' ?>" data-tab="empleados">
            <i class="fas fa-user-tie"></i> Empleados
        </button>
        <button class="tab-btn <?= $tab_activa === 'permisos' ? 'active' : '' ?>" data-tab="permisos">
            <i class="fas fa-lock"></i> Permisos
        </button>
    </div>
    

    
    <!-- PESTAÑA USUARIOS                                    -->

    
    
    <div id="tab-usuarios" class="tab-pane <?= $tab_activa === 'usuarios' ? 'active' : '' ?>">
        <div class="formulario-crear">
            <h3><i class="fas fa-user-plus"></i> Crear Nuevo Usuario</h3>
            <form method="POST">
                <div class="fila-campos">
                    <div class="campo-grupo">
                        <label>Nombre <span style="color: #e74c3c;">*</span></label>
                        <input type="text" name="nombre" required placeholder="Ej: Juan">
                    </div>
                    <div class="campo-grupo">
                        <label>Apellido <span style="color: #e74c3c;">*</span></label>
                        <input type="text" name="apellido" required placeholder="Ej: Pérez">
                    </div>
                </div>
                <div class="fila-campos">
                    <div class="campo-grupo">
                        <label>Usuario <span style="color: #e74c3c;">*</span></label>
                        <input type="text" name="usuario" required placeholder="Ej: juanperez">
                    </div>
                    <div class="campo-grupo">
                        <label>Email <span style="color: #e74c3c;">*</span></label>
                        <input type="email" name="email" required placeholder="Ej: juan@email.com">
                    </div>
                </div>
                <div class="fila-campos">
                    <div class="campo-grupo">
                        <label>Rol <span style="color: #e74c3c;">*</span></label>
                        <select name="rol">
                            <option value="cliente">Cliente</option>
                            <option value="empleado">Empleado</option>
                            <option value="administrador">Administrador</option>
                        </select>
                    </div>
                    <div class="campo-grupo">
                        <label>Contraseña <span style="color: #e74c3c;">*</span></label>
                        <input type="password" name="contrasena" required minlength="8" placeholder="Mínimo 8 caracteres">
                    </div>
                </div>
                <div class="fila-campos">
                    <div class="campo-grupo">
                        <label>Confirmar Contraseña <span style="color: #e74c3c;">*</span></label>
                        <input type="password" name="confirmar_pass" required>
                    </div>
                </div>
                <button type="submit" name="crear_usuario" class="btn-crear">
                    <i class="fas fa-plus-circle"></i> Crear Usuario
                </button>
            </form>
        </div>
        
        <div class="tabla-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $user): ?>
                    <tr>
                        <td><?= $user['id_usuario'] ?></td>
                        <td><strong><?= htmlspecialchars($user['usuario']) ?></strong></td>
                        <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')) ?></td>
                        <td><span class="badge-rol badge-<?= $user['rol'] ?>"><?= ucfirst($user['rol']) ?></span></td>
                        <td class="<?= $user['estado'] === 'activo' ? 'estado-activo' : 'estado-inactivo' ?>">
                            <?= ucfirst($user['estado']) ?>
                        </td>
                        <td>
                            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="id_usuario" value="<?= $user['id_usuario'] ?>">
                                    <input type="hidden" name="nuevo_rol" value="<?= $user['rol'] === 'cliente' ? 'empleado' : ($user['rol'] === 'empleado' ? 'administrador' : 'cliente') ?>">
                                    <button type="submit" name="cambiar_rol" class="btn-accion btn-rol" title="Cambiar rol">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="id_usuario" value="<?= $user['id_usuario'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?= $user['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                    <button type="submit" name="cambiar_estado" class="btn-accion <?= $user['estado'] === 'activo' ? 'btn-inactivo' : 'btn-activo' ?>" title="<?= $user['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>">
                                        <i class="fas <?= $user['estado'] === 'activo' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                    </button>
                                </form>
                                <a href="?tab=permisos&permisos_id=<?= $user['id_usuario'] ?>" class="btn-accion btn-permisos" title="Gestionar permisos">
                                    <i class="fas fa-lock"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- =================================================== -->
    <!-- PESTAÑA EMPLEADOS                                   -->
    <!-- =================================================== -->
    
    <div id="tab-empleados" class="tab-pane <?= $tab_activa === 'empleados' ? 'active' : '' ?>">
        <div class="grilla-empleados">
            <?php if (empty($empleados)): ?>
                <div class="alerta alerta-info">
                    <i class="fas fa-info-circle"></i> No hay empleados registrados.
                </div>
            <?php else: ?>
                <?php foreach ($empleados as $emp): ?>
                <div class="empleado-card">
                    <div class="empleado-header">
                        <span class="empleado-nombre">
                            <i class="fas fa-user-tie"></i> <?= htmlspecialchars($emp['nombre'] . ' ' . $emp['apellido']) ?>
                        </span>
                        <div style="display: flex; gap: 6px;">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="id_usuario" value="<?= $emp['id_usuario'] ?>">
                                <input type="hidden" name="nuevo_estado" value="<?= $emp['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                <button type="submit" name="cambiar_estado" class="btn-accion" style="background: <?= $emp['estado'] === 'activo' ? '#e74c3c' : '#2ecc71' ?>; color:white;">
                                    <i class="fas <?= $emp['estado'] === 'activo' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                    <?= $emp['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                            <a href="?tab=permisos&permisos_id=<?= $emp['id_usuario'] ?>" class="btn-accion btn-permisos">
                                <i class="fas fa-lock"></i> Permisos
                            </a>
                        </div>
                    </div>
                    <div><i class="fas fa-user"></i> Usuario: <?= htmlspecialchars($emp['usuario']) ?></div>
                    <div><i class="fas fa-envelope"></i> Email: <?= htmlspecialchars($emp['email'] ?? 'No registrado') ?></div>
                    <div><i class="fas fa-tools"></i> Especialidad: <?= htmlspecialchars($emp['especialidad'] ?? 'General') ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    

    
    <!-- PESTAÑA PERMISOS                                    -->

    
    
    <div id="tab-permisos" class="tab-pane <?= $tab_activa === 'permisos' ? 'active' : '' ?>">
        <?php if ($usuario_seleccionado): ?>
            <h3 style="color: #c9a84c;">
                <i class="fas fa-user"></i> Permisos de: 
                <strong><?= htmlspecialchars($usuario_seleccionado['usuario']) ?></strong> 
                (<span class="badge-rol badge-<?= $usuario_seleccionado['rol'] ?>"><?= ucfirst($usuario_seleccionado['rol']) ?></span>)
            </h3>
            
            <form method="POST">
                <input type="hidden" name="usuario_id" value="<?= $usuario_seleccionado['id_usuario'] ?>">
                <div class="grilla-permisos">
                    <?php 
                    
                    // Usar la lista de permisos desde el código
                    $lista_permisos = obtener_lista_permisos();
                    foreach ($lista_permisos as $permiso): 
                        $esta_marcado = isset($permisos_actuales[$permiso]) && $permisos_actuales[$permiso];
                        $descripcion = str_replace('_', ' ', ucfirst($permiso));
                    ?>
                    <div class="permiso-item">
                        <input type="checkbox" 
                               name="permiso_<?= $permiso ?>" 
                               id="perm_<?= $permiso ?>"
                               <?= $esta_marcado ? 'checked' : '' ?>>
                        <label for="perm_<?= $permiso ?>">
                            <strong><?= $descripcion ?></strong>
                            <small><?= $esta_marcado ? '✅ Concedido' : '❌ Denegado' ?></small>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="guardar_permisos" class="btn-primario">
                    <i class="fas fa-save"></i> Guardar Permisos
                </button>
            </form>
            
            <div style="margin-top: 20px; padding: 15px; background: rgba(201, 168, 76, 0.05); border-radius: 10px; border: 1px solid rgba(201, 168, 76, 0.15);">
                <small style="color: #8b949e;">
                    <i class="fas fa-info-circle"></i> 
                    Los permisos marcados son los que tiene actualmente el usuario. 
                    Los cambios se guardarán en su perfil de forma individual.
                </small>
            </div>
            
        <?php else: ?>
            <div class="selector-usuario-permisos">
                <h3><i class="fas fa-user-search"></i> Seleccionar Usuario</h3>
                <p style="color: #8b949e; margin-bottom: 12px;">Selecciona un usuario para ver y editar sus permisos personalizados.</p>
                <select onchange="window.location.href='?tab=permisos&permisos_id='+this.value">
                    <option value="">-- Seleccione un usuario --</option>
                    <?php foreach ($usuarios as $user): ?>
                        <option value="<?= $user['id_usuario'] ?>">
                            <?= htmlspecialchars($user['usuario']) ?> (<?= ucfirst($user['rol']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
    
</main>

<footer style="text-align: center; padding: 30px 20px 20px; border-top: 1px solid #30363d; margin-top: 40px; color: #8b949e;">
    <p>&copy; <?= date('Y') ?> <span style="color: #c9a84c; font-weight: 600;">SalonHub</span> — Administración Central</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

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