<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================
//  SalonHub - Login y Registro
// ============================================================

session_start();

if (!empty($_SESSION['id_usuario'])) {
    header('Location: home.php');
    exit;
}

require_once __DIR__ . '/conexion.php';

$errores = [];
$errores_reg = [];

// ── PROCESAR LOGIN ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'login') {
    
    $usuario_input = trim($_POST['usuario'] ?? '');
    $contrasena    = $_POST['contrasena'] ?? '';

    if (empty($usuario_input)) {
        $errores[] = 'Ingresá tu usuario o email.';
    }
    if (empty($contrasena)) {
        $errores[] = 'Ingresá tu contraseña.';
    }

    if (empty($errores)) {
        try {
            $pdo = conectar();
            $stmt = $pdo->prepare("
                SELECT id_usuario, usuario, email, contrasena, estado, rol, id_cliente, id_empleado, email_verificado
                FROM usuario
                WHERE (usuario = :usuario_input OR email = :email_input)
                LIMIT 1
            ");
            $stmt->execute([
                ':usuario_input' => $usuario_input,
                ':email_input' => $usuario_input
            ]);
            $user = $stmt->fetch();

            if (!$user) {
                $errores[] = 'Credenciales incorrectas.';
            } elseif ($user['estado'] !== 'activo') {
                $errores[] = 'Cuenta desactivada.';
            } elseif ($user['email_verificado'] == 0 && !empty($user['email'])) {
                // Si el email no está verificado, redirigir a verificación
                $_SESSION['verificar_email'] = $user['email'];
                header('Location: verificar_cuenta.php');
                exit;
            } elseif (!password_verify($contrasena, $user['contrasena'])) {
                $errores[] = 'Credenciales incorrectas.';
            } else {
                session_regenerate_id(true);
                $_SESSION['id_usuario']  = $user['id_usuario'];
                $_SESSION['usuario']     = $user['usuario'];
                $_SESSION['email']       = $user['email'];
                $_SESSION['rol']         = $user['rol'];
                $_SESSION['id_cliente']  = $user['id_cliente'];
                $_SESSION['id_empleado'] = $user['id_empleado'];

                $upd = $pdo->prepare("UPDATE usuario SET ultimo_acceso = NOW() WHERE id_usuario = :id");
                $upd->execute([':id' => $user['id_usuario']]);

                header('Location: home.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $errores[] = 'Error al iniciar sesión. Intente nuevamente.';
        }
    }
}

// ── PROCESAR REGISTRO ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registro') {
    
    $nombre    = trim($_POST['nombre']    ?? '');
    $apellido  = trim($_POST['apellido']  ?? '');
    $usuario   = trim($_POST['usuario_reg'] ?? '');
    $email     = trim($_POST['email_reg']   ?? '');
    $pass      = $_POST['contrasena_reg']     ?? '';
    $pass2     = $_POST['confirmar_pass'] ?? '';

    if (empty($nombre) || strlen($nombre) < 2) {
        $errores_reg['nombre'] = 'Mínimo 2 caracteres.';
    }
    if (empty($apellido) || strlen($apellido) < 2) {
        $errores_reg['apellido'] = 'Mínimo 2 caracteres.';
    }
    if (empty($usuario) || strlen($usuario) < 4) {
        $errores_reg['usuario'] = 'Mínimo 4 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9._]+$/', $usuario)) {
        $errores_reg['usuario'] = 'Caracteres no válidos.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores_reg['email'] = 'Email inválido.';
    }
    if (strlen($pass) < 8) {
        $errores_reg['contrasena'] = 'Mínimo 8 caracteres.';
    } elseif (!preg_match('/[A-Z]/', $pass)) {
        $errores_reg['contrasena'] = 'Falta una mayúscula.';
    } elseif (!preg_match('/[0-9]/', $pass)) {
        $errores_reg['contrasena'] = 'Falta un número.';
    }
    if ($pass !== $pass2) {
        $errores_reg['confirmar_pass'] = 'No coinciden.';
    }

    // Verificar si ya existe usuario o email
    if (empty($errores_reg)) {
        try {
            $pdo = conectar();

            $chkUser = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE usuario = :u");
            $chkUser->execute([':u' => $usuario]);
            if ((int)$chkUser->fetchColumn() > 0) {
                $errores_reg['usuario'] = 'Usuario en uso.';
            }

            $chkEmail = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email = :e");
            $chkEmail->execute([':e' => $email]);
            if ((int)$chkEmail->fetchColumn() > 0) {
                $errores_reg['email'] = 'Email registrado.';
            }

        } catch (PDOException $e) {
            $errores_reg['general'] = 'Error al verificar.';
        }
    }

    // Crear la cuenta
    if (empty($errores_reg)) {
        try {
            $pdo = conectar();
            $pdo->beginTransaction();

            // Insertar en persona
            $stmtPersona = $pdo->prepare("INSERT INTO persona (nombre, apellido, email) VALUES (:nombre, :apellido, :email)");
            $stmtPersona->execute([':nombre' => $nombre, ':apellido' => $apellido, ':email' => $email]);
            $id_persona = (int)$pdo->lastInsertId();

            // Insertar en cliente
            $stmtCliente = $pdo->prepare("INSERT INTO cliente (id_persona, fecha_inicio, estado_cliente) VALUES (:id_persona, CURDATE(), 'activo')");
            $stmtCliente->execute([':id_persona' => $id_persona]);
            $id_cliente = (int)$pdo->lastInsertId();

            // Insertar en usuario
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmtUser = $pdo->prepare("INSERT INTO usuario (id_cliente, usuario, email, contrasena, estado, rol, email_verificado) VALUES (:id_cliente, :usuario, :email, :contrasena, 'activo', 'cliente', 0)");
            $stmtUser->execute([':id_cliente' => $id_cliente, ':usuario' => $usuario, ':email' => $email, ':contrasena' => $hash]);
            $id_usuario = (int)$pdo->lastInsertId();

            $pdo->commit();

            // Generar y guardar código de verificación
            $codigo = generar_codigo_verificacion();
            guardar_codigo_verificacion($email, $codigo);
            
            // Enviar email de verificación
            $asunto = '🎉 Verifica tu cuenta - SalonHub';
            $mensaje_html = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background: #0d1117; padding: 20px; }
                    .container { max-width: 500px; margin: 0 auto; background: #1a1f2e; border-radius: 10px; padding: 30px; border: 1px solid #c9a84c; }
                    .codigo { font-size: 32px; font-weight: bold; text-align: center; padding: 20px; background: #0d1117; border-radius: 8px; letter-spacing: 5px; color: #c9a84c; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2 style='color:#c9a84c;'>✂ SalonHub</h2>
                    <p>Hola <strong>{$usuario}</strong>,</p>
                    <p>¡Bienvenido! Usá el siguiente código para verificar tu cuenta:</p>
                    <div class='codigo'>{$codigo}</div>
                    <p>Válido por 15 minutos.</p>
                </div>
            </body>
            </html>
            ";
            
            enviar_email($email, $asunto, $mensaje_html);
            
            // Guardar email en sesión y redirigir
            $_SESSION['verificar_email'] = $email;
            header('Location: verificar_cuenta.php');
            exit;

        } catch (PDOException $e) {
            if (isset($pdo)) $pdo->rollBack();
            error_log("Error registro PDO: " . $e->getMessage());
            $errores_reg['general'] = 'Error al crear cuenta: ' . $e->getMessage();
        }
    }
}

// Determinar qué tab mostrar
$tabActiva = 'login';
if (!empty($errores_reg) || (isset($_POST['accion']) && $_POST['accion'] === 'registro')) {
    $tabActiva = 'registro';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub | Acceso</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .formulario-auth { display: none; }
        .formulario-auth.activo { display: block; }
    </style>
</head>
<body class="pagina-auth">

<div class="particulas-fondo" id="particulasFondo"></div>

<div class="contenedor-auth">
    <div class="tarjeta-auth">

        <div class="logo-auth">
            <h1>SalonHub</h1>
            <p>El poder de la transformación</p>
        </div>

        <div class="auth-tabs">
            <button class="auth-tab<?= $tabActiva === 'login' ? ' activo' : '' ?>" onclick="cambiarTab('login')">Acceder</button>
            <button class="auth-tab<?= $tabActiva === 'registro' ? ' activo' : '' ?>" onclick="cambiarTab('registro')">Registrarse</button>
        </div>

        <!-- FORMULARIO LOGIN -->
        <div class="formulario-auth<?= $tabActiva === 'login' ? ' activo' : '' ?>" id="formLogin">
            
            <?php if (!empty($errores)): ?>
                <div class="alerta alerta-error">
                    <?php foreach ($errores as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['exp'])): ?>
                <div class="alerta alerta-info">
                    <p>Sesión expirada. Iniciá sesión nuevamente.</p>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" novalidate>
                <input type="hidden" name="accion" value="login">

                <div class="campo-grupo">
                    <label for="usuario">Usuario o Email</label>
                    <input type="text" id="usuario" name="usuario"
                           value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                           placeholder="Tu usuario o email"
                           autocomplete="username">
                </div>

                <div class="campo-grupo">
                    <label for="contrasena">Contraseña</label>
                    <div class="input-con-icono">
                        <input type="password" id="contrasena" name="contrasena"
                               placeholder="Tu contraseña"
                               autocomplete="current-password">
                        <button type="button" class="btn-ver-pass" onclick="togglePass('contrasena', this)">&#128065;</button>
                    </div>
                </div>

                <a href="recuperar_password.php" class="link-olvido">¿Olvidaste tu contraseña?</a>

                <button type="submit" class="btn btn-primario btn-bloque">
                    Iniciar Sesión
                </button>
            </form>
        </div>

        <!-- FORMULARIO REGISTRO -->
        <div class="formulario-auth<?= $tabActiva === 'registro' ? ' activo' : '' ?>" id="formRegistro">
            
            <?php if (!empty($errores_reg['general'])): ?>
                <div class="alerta alerta-error">
                    <p><?= htmlspecialchars($errores_reg['general']) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" novalidate id="formRegistroForm">
                <input type="hidden" name="accion" value="registro">

                <div class="fila-campos">
                    <div class="campo-grupo">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre"
                               value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                               placeholder="Tu nombre">
                        <span class="error-campo"><?= htmlspecialchars($errores_reg['nombre'] ?? '') ?></span>
                    </div>
                    <div class="campo-grupo">
                        <label for="apellido">Apellido *</label>
                        <input type="text" id="apellido" name="apellido"
                               value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>"
                               placeholder="Tu apellido">
                        <span class="error-campo"><?= htmlspecialchars($errores_reg['apellido'] ?? '') ?></span>
                    </div>
                </div>

                <div class="campo-grupo">
                    <label for="usuario_reg">Usuario *</label>
                    <input type="text" id="usuario_reg" name="usuario_reg"
                           value="<?= htmlspecialchars($_POST['usuario_reg'] ?? '') ?>"
                           placeholder="Mínimo 4 caracteres"
                           autocomplete="username">
                    <span class="error-campo"><?= htmlspecialchars($errores_reg['usuario'] ?? '') ?></span>
                    <span class="indicador-ajax" id="ajax-usuario"></span>
                </div>

                <div class="campo-grupo">
                    <label for="email_reg">Email *</label>
                    <input type="email" id="email_reg" name="email_reg"
                           value="<?= htmlspecialchars($_POST['email_reg'] ?? '') ?>"
                           placeholder="tu@email.com"
                           autocomplete="email">
                    <span class="error-campo"><?= htmlspecialchars($errores_reg['email'] ?? '') ?></span>
                    <span class="indicador-ajax" id="ajax-email"></span>
                </div>

                <div class="campo-grupo">
                    <label for="contrasena_reg">Contraseña *</label>
                    <div class="input-con-icono">
                        <input type="password" id="contrasena_reg" name="contrasena_reg"
                               placeholder="Mín. 8 caracteres, 1 mayúscula, 1 número"
                               autocomplete="new-password">
                        <button type="button" class="btn-ver-pass" onclick="togglePass('contrasena_reg', this)">&#128065;</button>
                    </div>
                    <span class="error-campo"><?= htmlspecialchars($errores_reg['contrasena'] ?? '') ?></span>
                    <div class="barra-fortaleza"><div class="barra-fill" id="barraFill"></div></div>
                    <small id="textoFortaleza"></small>
                </div>

                <div class="campo-grupo">
                    <label for="confirmar_pass">Confirmar *</label>
                    <div class="input-con-icono">
                        <input type="password" id="confirmar_pass" name="confirmar_pass"
                               placeholder="Repetí tu contraseña"
                               autocomplete="new-password">
                        <button type="button" class="btn-ver-pass" onclick="togglePass('confirmar_pass', this)">&#128065;</button>
                    </div>
                    <span class="error-campo"><?= htmlspecialchars($errores_reg['confirmar_pass'] ?? '') ?></span>
                </div>

                <button type="submit" class="btn btn-primario btn-bloque">
                    Crear Cuenta
                </button>
            </form>
        </div>

    </div>
</div>

<script>
(function() {
    const fondo = document.getElementById('particulasFondo');
    if (!fondo) return;
    
    const cantidadParticulas = 60;
    
    for (let i = 0; i < cantidadParticulas; i++) {
        const particula = document.createElement('div');
        particula.classList.add('particula');
        
        const tamanio = Math.random() * 6 + 2;
        const izquierda = Math.random() * 100;
        const duracion = Math.random() * 20 + 12;
        const retraso = Math.random() * 15;
        
        particula.style.width = tamanio + 'px';
        particula.style.height = tamanio + 'px';
        particula.style.left = izquierda + '%';
        particula.style.animationDuration = duracion + 's';
        particula.style.animationDelay = retraso + 's';
        
        fondo.appendChild(particula);
    }
})();

function cambiarTab(tab) {
    const tabs = document.querySelectorAll('.auth-tab');
    const forms = document.querySelectorAll('.formulario-auth');
    
    tabs.forEach(t => t.classList.remove('activo'));
    forms.forEach(f => f.classList.remove('activo'));
    
    if (tab === 'login') {
        tabs[0].classList.add('activo');
        document.getElementById('formLogin').classList.add('activo');
        history.pushState(null, '', 'login.php');
    } else {
        tabs[1].classList.add('activo');
        document.getElementById('formRegistro').classList.add('activo');
        history.pushState(null, '', 'login.php?modo=registro');
    }
}

function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '&#128064;';
    } else {
        input.type = 'password';
        btn.innerHTML = '&#128065;';
    }
}

let timerUsuario = null;
const inputUsuarioReg = document.getElementById('usuario_reg');
if (inputUsuarioReg) {
    inputUsuarioReg.addEventListener('input', function() {
        clearTimeout(timerUsuario);
        const val = this.value.trim();
        const indicador = document.getElementById('ajax-usuario');
        if (!indicador) return;

        if (val.length < 4) {
            indicador.textContent = '';
            return;
        }

        indicador.textContent = 'Verificando...';
        indicador.className = 'indicador-ajax cargando';

        timerUsuario = setTimeout(() => {
            const fd = new FormData();
            fd.append('usuario', val);

            fetch('ajax/verificar_usuario.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    indicador.textContent = data.mensaje;
                    indicador.className = 'indicador-ajax ' + (data.disponible ? 'ok' : 'error');
                })
                .catch(() => {
                    indicador.textContent = 'Error al verificar.';
                    indicador.className = 'indicador-ajax error';
                });
        }, 500);
    });
}

let timerEmail = null;
const inputEmailReg = document.getElementById('email_reg');
if (inputEmailReg) {
    inputEmailReg.addEventListener('input', function() {
        clearTimeout(timerEmail);
        const val = this.value.trim();
        const indicador = document.getElementById('ajax-email');
        if (!indicador) return;

        if (!val.includes('@')) {
            indicador.textContent = '';
            return;
        }

        indicador.textContent = 'Verificando...';
        indicador.className = 'indicador-ajax cargando';

        timerEmail = setTimeout(() => {
            const fd = new FormData();
            fd.append('email', val);

            fetch('ajax/verificar_email.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    indicador.textContent = data.mensaje;
                    indicador.className = 'indicador-ajax ' + (data.disponible ? 'ok' : 'error');
                })
                .catch(() => {
                    indicador.textContent = 'Error al verificar.';
                    indicador.className = 'indicador-ajax error';
                });
        }, 500);
    });
}

const inputPassReg = document.getElementById('contrasena_reg');
if (inputPassReg) {
    inputPassReg.addEventListener('input', function() {
        const val = this.value;
        let puntaje = 0;
        if (val.length >= 8) puntaje++;
        if (/[A-Z]/.test(val)) puntaje++;
        if (/[0-9]/.test(val)) puntaje++;
        if (/[^a-zA-Z0-9]/.test(val)) puntaje++;

        const fill = document.getElementById('barraFill');
        const texto = document.getElementById('textoFortaleza');
        if (!fill || !texto) return;

        const niveles = [
            { color: '#e74c3c', label: 'Muy débil' },
            { color: '#e67e22', label: 'Débil' },
            { color: '#f1c40f', label: 'Regular' },
            { color: '#2ecc71', label: 'Fuerte' },
            { color: '#27ae60', label: 'Muy fuerte' },
        ];
        const nivel = niveles[puntaje] || niveles[0];
        fill.style.width = ((puntaje / 4) * 100) + '%';
        fill.style.background = nivel.color;
        texto.innerHTML = val.length > 0 ? nivel.label : '';
    });
}

const formRegistro = document.getElementById('formRegistroForm');
if (formRegistro) {
    formRegistro.addEventListener('submit', function(e) {
        let valido = true;
        let mensaje = '';

        const nombre = document.getElementById('nombre')?.value.trim() || '';
        const apellido = document.getElementById('apellido')?.value.trim() || '';
        const usuario = document.getElementById('usuario_reg')?.value.trim() || '';
        const email = document.getElementById('email_reg')?.value.trim() || '';
        const pass = document.getElementById('contrasena_reg')?.value || '';
        const pass2 = document.getElementById('confirmar_pass')?.value || '';

        if (nombre.length < 2) { mensaje = 'Nombre: mínimo 2 caracteres.'; valido = false; }
        else if (apellido.length < 2) { mensaje = 'Apellido: mínimo 2 caracteres.'; valido = false; }
        else if (usuario.length < 4) { mensaje = 'Usuario: mínimo 4 caracteres.'; valido = false; }
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { mensaje = 'Email inválido.'; valido = false; }
        else if (pass.length < 8) { mensaje = 'Contraseña: mínimo 8 caracteres.'; valido = false; }
        else if (!/[A-Z]/.test(pass)) { mensaje = 'Contraseña: falta una mayúscula.'; valido = false; }
        else if (!/[0-9]/.test(pass)) { mensaje = 'Contraseña: falta un número.'; valido = false; }
        else if (pass !== pass2) { mensaje = 'Las contraseñas no coinciden.'; valido = false; }

        if (!valido) {
            e.preventDefault();
            alert(mensaje);
        }
    });
}
</script>

</body>
</html>
