<?php
/**
 * SalonHub - Perfil de Usuario
 * Requiere: ver_perfil | Edición requiere: editar_perfil
 */

session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('ver_perfil');

$puede_editar = tiene_permiso('editar_perfil');
$id_usuario   = (int)$_SESSION['id_usuario'];

$pdo     = conectar();
$errores = [];
$exito   = '';

// ── Cargar datos actuales ────────────────────────────────────
function cargar_usuario(PDO $pdo, int $id): array {
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.usuario, u.email, u.rol, u.estado,
               p.nombre, p.apellido, p.telefono, p.cuil
        FROM usuario u
        LEFT JOIN cliente cl ON cl.id_cliente = u.id_cliente
        LEFT JOIN persona p  ON p.id_persona  = cl.id_persona
        WHERE u.id_usuario = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: [];
}

$datos = cargar_usuario($pdo, $id_usuario);

// ── BACKEND: Procesar formulario de edición ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede_editar) {

    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    $usuario   = trim($_POST['usuario'] ?? '');
    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $pass_nuevo   = $_POST['pass_nuevo']    ?? '';
    $pass_actual  = $_POST['pass_actual']   ?? '';
    $pass_confirm = $_POST['pass_confirm']  ?? '';

    // Validaciones
    if (empty($usuario) || strlen($usuario) < 4) {
        $errores['usuario'] = 'El usuario debe tener al menos 4 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9._]+$/', $usuario)) {
        $errores['usuario'] = 'El usuario solo puede contener letras, números, puntos y guiones bajos.';
    } else {
        // Verificar que el usuario no esté en uso por OTRO usuario
        $chk = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE usuario = :u AND id_usuario != :id");
        $chk->execute([':u' => $usuario, ':id' => $id_usuario]);
        if ((int)$chk->fetchColumn() > 0) {
            $errores['usuario'] = 'Ese usuario ya está registrado.';
        }
    }

    if (empty($nombre) || strlen($nombre) < 2) {
        $errores['nombre'] = 'El nombre debe tener al menos 2 caracteres.';
    }
    if (empty($apellido) || strlen($apellido) < 2) {
        $errores['apellido'] = 'El apellido debe tener al menos 2 caracteres.';
    }
    if (!empty($telefono) && !preg_match('/^[0-9 +\-()]+$/', $telefono)) {
        $errores['telefono'] = 'Formato de teléfono inválido.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'Ingresá un email válido.';
    } else {
        // Verificar que el email no esté en uso por OTRO usuario
        $chk = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email = :e AND id_usuario != :id");
        $chk->execute([':e' => $email, ':id' => $id_usuario]);
        if ((int)$chk->fetchColumn() > 0) {
            $errores['email'] = 'Ese email ya está registrado por otro usuario.';
        }
    }

    // Cambio de contraseña (opcional)
    $cambiar_pass = false;
    if (!empty($pass_nuevo)) {
        if (empty($pass_actual)) {
            $errores['pass_actual'] = 'Ingresá tu contraseña actual para cambiarla.';
        } else {
            // Verificar contraseña actual
            $chkPass = $pdo->prepare("SELECT contrasena FROM usuario WHERE id_usuario = :id");
            $chkPass->execute([':id' => $id_usuario]);
            $hashActual = $chkPass->fetchColumn();
            if (!password_verify($pass_actual, $hashActual)) {
                $errores['pass_actual'] = 'La contraseña actual es incorrecta.';
            }
        }
        if (strlen($pass_nuevo) < 8) {
            $errores['pass_nuevo'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } elseif (!preg_match('/[A-Z]/', $pass_nuevo)) {
            $errores['pass_nuevo'] = 'Debe incluir al menos una mayúscula.';
        } elseif (!preg_match('/[0-9]/', $pass_nuevo)) {
            $errores['pass_nuevo'] = 'Debe incluir al menos un número.';
        }
        if ($pass_nuevo !== $pass_confirm) {
            $errores['pass_confirm'] = 'Las contraseñas no coinciden.';
        }
        if (empty($errores['pass_actual']) && empty($errores['pass_nuevo']) && empty($errores['pass_confirm'])) {
            $cambiar_pass = true;
        }
    }

    // Guardar si no hay errores
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // Actualizar usuario en la tabla usuario
            $updUser = $pdo->prepare("UPDATE usuario SET usuario = :usuario, email = :email WHERE id_usuario = :id");
            $updUser->execute([':usuario' => $usuario, ':email' => $email, ':id' => $id_usuario]);

            // Actualizar contraseña si corresponde
            if ($cambiar_pass) {
                $nuevoHash = password_hash($pass_nuevo, PASSWORD_BCRYPT);
                $updPass   = $pdo->prepare("UPDATE usuario SET contrasena = :hash WHERE id_usuario = :id");
                $updPass->execute([':hash' => $nuevoHash, ':id' => $id_usuario]);
            }

            // Actualizar datos en persona (a través de cliente)
            $updPersona = $pdo->prepare("
                UPDATE persona p
                INNER JOIN cliente cl ON cl.id_persona = p.id_persona
                INNER JOIN usuario u  ON u.id_cliente  = cl.id_cliente
                SET p.nombre   = :nombre,
                    p.apellido = :apellido,
                    p.telefono = :telefono,
                    p.email    = :email
                WHERE u.id_usuario = :id
            ");
            $updPersona->execute([
                ':nombre'   => $nombre,
                ':apellido' => $apellido,
                ':telefono' => $telefono,
                ':email'    => $email,
                ':id'       => $id_usuario,
            ]);

            $pdo->commit();

            // Actualizar la sesión con el nuevo usuario
            $_SESSION['usuario'] = $usuario;

            $exito = 'Perfil actualizado correctamente.';
            $datos = cargar_usuario($pdo, $id_usuario);

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Perfil update: " . $e->getMessage());
            $errores['general'] = 'Error al guardar. Intentá de nuevo.';
        }
    }
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Mi Perfil</title>
    <link rel="stylesheet" href="css/estilos.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* =================================================== */
        /* ESTILOS GENERALES (igual que los otros archivos)   */
        /* =================================================== */
        
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
            max-width: 800px;
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
        
        /* =================================================== */
        /* ALERTAS                                            */
        /* =================================================== */
        
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
        
        .alerta-exito i {
            color: #3fb950;
        }
        
        .alerta-error {
            background: rgba(248, 81, 73, 0.15);
            border: 1px solid #da3633;
            color: #f85149;
        }
        
        .alerta-error i {
            color: #f85149;
        }
        
        .alerta-info {
            background: rgba(88, 166, 255, 0.08);
            border: 1px solid #30363d;
            color: #8b949e;
        }
        
        /* =================================================== */
        /* PERFIL                                             */
        /* =================================================== */
        
        .perfil-contenedor {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 24px;
        }
        
        .tarjeta-seccion {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        
        .tarjeta-seccion h3 {
            color: #c9a84c;
            font-size: 1rem;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #30363d;
        }
        
        .tarjeta-seccion h3 i {
            margin-right: 8px;
        }
        
        .tarjeta-seccion h3 small {
            color: #8b949e;
            font-weight: 400;
            font-size: 0.75rem;
        }
        
        .campo-grupo {
            margin-bottom: 14px;
        }
        
        .campo-grupo label {
            display: block;
            color: #8b949e;
            font-size: 0.85rem;
            margin-bottom: 4px;
            font-weight: 500;
        }
        
        .campo-grupo label i {
            color: #c9a84c;
            margin-right: 6px;
            width: 18px;
        }
        
        .campo-grupo input {
            width: 100%;
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
        
        .campo-grupo input::placeholder {
            color: #484f58;
        }
        
        .campo-grupo .error-campo {
            color: #f85149;
            font-size: 0.75rem;
            display: block;
            margin-top: 4px;
        }
        
        .fila-campos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .btn-primario {
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primario:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
        }
        
        .btn-primario i {
            font-size: 1.1rem;
        }
        
        .badge-rol {
            display: inline-block;
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
            color: white;
        }
        
        .badge-cliente {
            background: #4a5568;
            color: white;
        }
        
        .perfil-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .perfil-info .item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #8b949e;
            font-size: 0.9rem;
        }
        
        .perfil-info .item i {
            color: #c9a84c;
            width: 20px;
            text-align: center;
        }
        
        .perfil-info .item strong {
            color: #e6edf3;
            font-weight: 500;
        }
        
        .perfil-info .item .valor {
            color: #e6edf3;
        }
        
        /* =================================================== */
        /* FOOTER                                             */
        /* =================================================== */
        
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
        
        /* =================================================== */
        /* RESPONSIVE                                          */
        /* =================================================== */
        
        @media (max-width: 768px) {
            .perfil-contenedor {
                grid-template-columns: 1fr;
            }
            
            .fila-campos {
                grid-template-columns: 1fr;
            }
            
            .header-contenido {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .nav-header {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .contenedor-panel h2 {
                font-size: 1.4rem;
            }
            
            .contenedor-panel {
                padding: 16px;
            }
            
            .tarjeta-seccion {
                padding: 16px;
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
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2><i class="fas fa-user-circle"></i> Mi Perfil</h2>

    <?php if ($exito): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>
    <?php if (!empty($errores['general'])): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errores['general']) ?></div>
    <?php endif; ?>

    <div class="perfil-contenedor">

        <!-- Datos de cuenta (solo lectura) -->
        <div class="tarjeta-seccion">
            <h3><i class="fas fa-info-circle"></i> Información de cuenta</h3>
            <div class="perfil-info">
                <div class="item">
                    <i class="fas fa-user"></i>
                    <strong>Usuario:</strong>
                    <span class="valor"><?= htmlspecialchars($datos['usuario'] ?? '') ?></span>
                </div>
                <div class="item">
                    <i class="fas fa-tag"></i>
                    <strong>Rol:</strong>
                    <span class="badge-rol badge-<?= $datos['rol'] ?? 'cliente' ?>"><?= ucfirst($datos['rol'] ?? '') ?></span>
                </div>
                <div class="item">
                    <i class="fas fa-circle"></i>
                    <strong>Estado:</strong>
                    <span class="valor"><?= ucfirst($datos['estado'] ?? '') ?></span>
                </div>
            </div>
        </div>

        <!-- Formulario editable -->
        <?php if ($puede_editar): ?>
        <div>
            <form id="formPerfil" method="POST" action="perfil.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="tarjeta-seccion">
                    <h3><i class="fas fa-user-edit"></i> Datos personales</h3>

                    <div class="fila-campos">
                        <div class="campo-grupo">
                            <label for="nombre"><i class="fas fa-user"></i> Nombre <span style="color: #f85149;">*</span></label>
                            <input type="text" id="nombre" name="nombre"
                                   value="<?= htmlspecialchars($datos['nombre'] ?? '') ?>"
                                   placeholder="Tu nombre">
                            <span class="error-campo" id="err-nombre">
                                <?= htmlspecialchars($errores['nombre'] ?? '') ?>
                            </span>
                        </div>
                        <div class="campo-grupo">
                            <label for="apellido"><i class="fas fa-user"></i> Apellido <span style="color: #f85149;">*</span></label>
                            <input type="text" id="apellido" name="apellido"
                                   value="<?= htmlspecialchars($datos['apellido'] ?? '') ?>"
                                   placeholder="Tu apellido">
                            <span class="error-campo" id="err-apellido">
                                <?= htmlspecialchars($errores['apellido'] ?? '') ?>
                            </span>
                        </div>
                    </div>

                    <div class="campo-grupo">
                        <label for="usuario"><i class="fas fa-user-tag"></i> Usuario <span style="color: #f85149;">*</span></label>
                        <input type="text" id="usuario" name="usuario"
                               value="<?= htmlspecialchars($datos['usuario'] ?? '') ?>"
                               placeholder="Mínimo 4 caracteres">
                        <span class="error-campo" id="err-usuario">
                            <?= htmlspecialchars($errores['usuario'] ?? '') ?>
                        </span>
                    </div>

                    <div class="campo-grupo">
                        <label for="email"><i class="fas fa-envelope"></i> Email <span style="color: #f85149;">*</span></label>
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($datos['email'] ?? '') ?>"
                               placeholder="tu@email.com">
                        <span class="error-campo" id="err-email">
                            <?= htmlspecialchars($errores['email'] ?? '') ?>
                        </span>
                    </div>

                    <div class="campo-grupo">
                        <label for="telefono"><i class="fas fa-phone"></i> Teléfono</label>
                        <input type="text" id="telefono" name="telefono"
                               value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>"
                               placeholder="+54 11 1234-5678">
                        <span class="error-campo" id="err-telefono">
                            <?= htmlspecialchars($errores['telefono'] ?? '') ?>
                        </span>
                    </div>
                </div>

                <div class="tarjeta-seccion">
                    <h3><i class="fas fa-lock"></i> Cambiar contraseña <small>(opcional)</small></h3>

                    <div class="campo-grupo">
                        <label for="pass_actual"><i class="fas fa-key"></i> Contraseña actual</label>
                        <input type="password" id="pass_actual" name="pass_actual" placeholder="Ingresá tu contraseña actual">
                        <span class="error-campo" id="err-pass_actual">
                            <?= htmlspecialchars($errores['pass_actual'] ?? '') ?>
                        </span>
                    </div>
                    <div class="campo-grupo">
                        <label for="pass_nuevo"><i class="fas fa-key"></i> Nueva contraseña</label>
                        <input type="password" id="pass_nuevo" name="pass_nuevo" placeholder="Mín. 8 caracteres, 1 mayúscula, 1 número">
                        <span class="error-campo" id="err-pass_nuevo">
                            <?= htmlspecialchars($errores['pass_nuevo'] ?? '') ?>
                        </span>
                    </div>
                    <div class="campo-grupo">
                        <label for="pass_confirm"><i class="fas fa-check-circle"></i> Confirmar nueva contraseña</label>
                        <input type="password" id="pass_confirm" name="pass_confirm" placeholder="Repetí la nueva contraseña">
                        <span class="error-campo" id="err-pass_confirm">
                            <?= htmlspecialchars($errores['pass_confirm'] ?? '') ?>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn-primario">
                    <i class="fas fa-save"></i> Guardar cambios
                </button>

            </form>
        </div>
        <?php else: ?>
            <div class="alerta alerta-info">
                <i class="fas fa-info-circle"></i> No tenés permiso para editar el perfil.
            </div>
        <?php endif; ?>

    </div>
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Mi Perfil</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

<script src="js/validaciones.js"></script>
<script>
document.getElementById('formPerfil')?.addEventListener('submit', function(e) {
    let valido = true;
    
    function limpiarErrores(ids) {
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '';
        });
    }
    
    function mostrarError(id, mensaje) {
        const el = document.getElementById(id);
        if (el) el.textContent = mensaje;
    }
    
    limpiarErrores(['err-usuario', 'err-nombre', 'err-apellido', 'err-email', 'err-telefono', 'err-pass_nuevo', 'err-pass_confirm', 'err-pass_actual']);

    const usuario   = document.getElementById('usuario').value.trim();
    const nombre   = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const email    = document.getElementById('email').value.trim();
    const pasNuevo = document.getElementById('pass_nuevo').value;
    const pasConf  = document.getElementById('pass_confirm').value;
    const pasAct   = document.getElementById('pass_actual').value;

    if (usuario.length < 4) {
        mostrarError('err-usuario', 'Mínimo 4 caracteres.');
        valido = false;
    }
    if (!/^[a-zA-Z0-9._]+$/.test(usuario)) {
        mostrarError('err-usuario', 'Caracteres no válidos.');
        valido = false;
    }
    if (nombre.length < 2) {
        mostrarError('err-nombre', 'Mínimo 2 caracteres.');
        valido = false;
    }
    if (apellido.length < 2) {
        mostrarError('err-apellido', 'Mínimo 2 caracteres.');
        valido = false;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        mostrarError('err-email', 'Email inválido.');
        valido = false;
    }
    if (pasNuevo !== '') {
        if (pasAct === '') {
            mostrarError('err-pass_actual', 'Ingresá tu contraseña actual.');
            valido = false;
        }
        if (pasNuevo.length < 8) {
            mostrarError('err-pass_nuevo', 'Mínimo 8 caracteres.');
            valido = false;
        }
        if (!/[A-Z]/.test(pasNuevo)) {
            mostrarError('err-pass_nuevo', 'Falta una mayúscula.');
            valido = false;
        }
        if (!/[0-9]/.test(pasNuevo)) {
            mostrarError('err-pass_nuevo', 'Falta un número.');
            valido = false;
        }
        if (pasNuevo !== pasConf) {
            mostrarError('err-pass_confirm', 'Las contraseñas no coinciden.');
            valido = false;
        }
    }
    if (!valido) e.preventDefault();
});
</script>

</body>
</html>