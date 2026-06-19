<?php
// ============================================================
//  SalonHub - Perfil de Usuario
//  Requiere: ver_perfil  |  Edición requiere: editar_perfil
// ============================================================

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

    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $pass_nuevo   = $_POST['pass_nuevo']    ?? '';
    $pass_actual  = $_POST['pass_actual']   ?? '';
    $pass_confirm = $_POST['pass_confirm']  ?? '';

    // Validaciones
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

            // Actualizar email en usuario
            $updUser = $pdo->prepare("UPDATE usuario SET email = :email WHERE id_usuario = :id");
            $updUser->execute([':email' => $email, ':id' => $id_usuario]);

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
            $exito = '✅ Perfil actualizado correctamente.';
            $datos = cargar_usuario($pdo, $id_usuario); // Refrescar datos

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
</head>
<body>

<header class="header-panel">
    <div class="header-contenido">
        <h1 class="logo-header"><a href="home.php">✂ SalonHub</a></h1>
        <nav class="nav-header">
            <a href="home.php" class="btn btn-outline-sm">← Panel</a>
            <a href="cerrar_sesion.php" class="btn btn-outline-sm btn-danger-outline">Cerrar sesión</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2>Mi Perfil</h2>

    <?php if ($exito): ?>
        <div class="alerta alerta-exito"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>
    <?php if (!empty($errores['general'])): ?>
        <div class="alerta alerta-error">⚠ <?= htmlspecialchars($errores['general']) ?></div>
    <?php endif; ?>

    <div class="perfil-contenedor">

        <!-- Datos de cuenta (solo lectura) -->
        <div class="tarjeta-seccion">
            <h3>Información de cuenta</h3>
            <p><strong>Usuario:</strong> <?= htmlspecialchars($datos['usuario'] ?? '') ?></p>
            <p><strong>Rol:</strong> <span class="badge-rol badge-<?= $datos['rol'] ?? 'cliente' ?>"><?= ucfirst($datos['rol'] ?? '') ?></span></p>
            <p><strong>Estado:</strong> <?= ucfirst($datos['estado'] ?? '') ?></p>
        </div>

        <!-- Formulario editable -->
        <?php if ($puede_editar): ?>
        <form id="formPerfil" method="POST" action="perfil.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="tarjeta-seccion">
                <h3>Datos personales</h3>

                <div class="fila-campos">
                    <div class="campo-grupo">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre"
                               value="<?= htmlspecialchars($datos['nombre'] ?? '') ?>">
                        <span class="error-campo" id="err-nombre">
                            <?= htmlspecialchars($errores['nombre'] ?? '') ?>
                        </span>
                    </div>
                    <div class="campo-grupo">
                        <label for="apellido">Apellido *</label>
                        <input type="text" id="apellido" name="apellido"
                               value="<?= htmlspecialchars($datos['apellido'] ?? '') ?>">
                        <span class="error-campo" id="err-apellido">
                            <?= htmlspecialchars($errores['apellido'] ?? '') ?>
                        </span>
                    </div>
                </div>

                <div class="campo-grupo">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($datos['email'] ?? '') ?>">
                    <span class="error-campo" id="err-email">
                        <?= htmlspecialchars($errores['email'] ?? '') ?>
                    </span>
                </div>

                <div class="campo-grupo">
                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono"
                           value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>"
                           placeholder="+54 11 1234-5678">
                    <span class="error-campo" id="err-telefono">
                        <?= htmlspecialchars($errores['telefono'] ?? '') ?>
                    </span>
                </div>
            </div><!-- /datos personales -->

            <div class="tarjeta-seccion">
                <h3>Cambiar contraseña <small>(opcional)</small></h3>

                <div class="campo-grupo">
                    <label for="pass_actual">Contraseña actual</label>
                    <input type="password" id="pass_actual" name="pass_actual" placeholder="Ingresá tu contraseña actual">
                    <span class="error-campo" id="err-pass_actual">
                        <?= htmlspecialchars($errores['pass_actual'] ?? '') ?>
                    </span>
                </div>
                <div class="campo-grupo">
                    <label for="pass_nuevo">Nueva contraseña</label>
                    <input type="password" id="pass_nuevo" name="pass_nuevo" placeholder="Mín. 8 caracteres">
                    <span class="error-campo" id="err-pass_nuevo">
                        <?= htmlspecialchars($errores['pass_nuevo'] ?? '') ?>
                    </span>
                </div>
                <div class="campo-grupo">
                    <label for="pass_confirm">Confirmar nueva contraseña</label>
                    <input type="password" id="pass_confirm" name="pass_confirm" placeholder="Repetí la nueva contraseña">
                    <span class="error-campo" id="err-pass_confirm">
                        <?= htmlspecialchars($errores['pass_confirm'] ?? '') ?>
                    </span>
                </div>
            </div>

            <button type="submit" class="btn btn-primario">Guardar cambios</button>

        </form>
        <?php else: ?>
            <div class="alerta alerta-info">No tenés permiso para editar el perfil.</div>
        <?php endif; ?>

    </div><!-- /perfil-contenedor -->
</main>

<script src="js/validaciones.js"></script>
<script>
document.getElementById('formPerfil')?.addEventListener('submit', function(e) {
    let valido = true;
    limpiarErrores(['err-nombre','err-apellido','err-email','err-telefono','err-pass_nuevo','err-pass_confirm','err-pass_actual']);

    const nombre   = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const email    = document.getElementById('email').value.trim();
    const pasNuevo = document.getElementById('pass_nuevo').value;
    const pasConf  = document.getElementById('pass_confirm').value;
    const pasAct   = document.getElementById('pass_actual').value;

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
