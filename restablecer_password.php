<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/conexion.php';

$mensaje = '';
$error = '';
$codigo_valido = false;
$email = $_SESSION['reset_email'] ?? '';

// Verificar si hay email en sesión
if (empty($email)) {
    header('Location: recuperar_password.php');
    exit;
}

// Procesar verificación de código
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verificar_codigo'])) {
    $codigo = trim($_POST['codigo'] ?? '');
    
    if (empty($codigo) || strlen($codigo) !== 6) {
        $error = "Ingresá el código de 6 dígitos.";
    } else {
        try {
            $pdo = conectar();
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuario WHERE email = :email AND verification_token = :token AND reset_token_expira > NOW()");
            $stmt->execute([':email' => $email, ':token' => $codigo]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                $_SESSION['reset_token'] = $codigo;
                $_SESSION['reset_id_usuario'] = $usuario['id_usuario'];
                $codigo_valido = true;
                $mensaje = "✅ Código verificado. Ahora podés cambiar tu contraseña.";
            } else {
                $error = "❌ Código inválido o expirado. Solicita uno nuevo.";
            }
        } catch (PDOException $e) {
            $error = "Error al verificar el código.";
        }
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $nueva_pass = $_POST['nueva_password'] ?? '';
    $confirmar_pass = $_POST['confirmar_password'] ?? '';
    $codigo = $_SESSION['reset_token'] ?? '';
    $id_usuario = $_SESSION['reset_id_usuario'] ?? 0;
    
    $errores = [];
    
    if (strlen($nueva_pass) < 8) {
        $errores[] = "La contraseña debe tener mínimo 8 caracteres.";
    }
    if (!preg_match('/[A-Z]/', $nueva_pass)) {
        $errores[] = "La contraseña debe tener al menos una mayúscula.";
    }
    if (!preg_match('/[0-9]/', $nueva_pass)) {
        $errores[] = "La contraseña debe tener al menos un número.";
    }
    if ($nueva_pass !== $confirmar_pass) {
        $errores[] = "Las contraseñas no coinciden.";
    }
    
    if (empty($errores)) {
        try {
            $pdo = conectar();
            
            // Verificar nuevamente que el código sea válido
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuario WHERE id_usuario = :id AND verification_token = :token AND reset_token_expira > NOW()");
            $stmt->execute([':id' => $id_usuario, ':token' => $codigo]);
            
            if ($stmt->fetch()) {
                $hash = password_hash($nueva_pass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE usuario SET contrasena = :pass, verification_token = NULL, reset_token_expira = NULL WHERE id_usuario = :id");
                $stmt->execute([':pass' => $hash, ':id' => $id_usuario]);
                
                // Limpiar sesión
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_token']);
                unset($_SESSION['reset_id_usuario']);
                
                $mensaje = "✅ Contraseña actualizada correctamente.";
                echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 3000);</script>";
            } else {
                $error = "❌ Código expirado. Solicita uno nuevo.";
            }
        } catch (PDOException $e) {
            $error = "Error al actualizar la contraseña.";
        }
    } else {
        $error = implode("<br>", $errores);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer Contraseña - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        body { background: var(--loki-negro); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .restablecer-container { max-width: 450px; width: 100%; margin: 20px; }
        .card { background: var(--loki-gris); border-radius: 16px; padding: 30px; border: 1px solid var(--borde); }
        .codigo-input { font-size: 2rem; text-align: center; letter-spacing: 10px; font-weight: bold; }
        .password-strength { height: 4px; background: var(--borde); border-radius: 2px; margin-top: 8px; overflow: hidden; }
        .password-strength-fill { width: 0%; height: 100%; transition: width 0.3s; }
    </style>
</head>
<body>
<div class="restablecer-container">
    <div class="card">
        <div style="text-align: center; margin-bottom: 20px;">
            <h1 style="color: var(--loki-dorado);">✂ SalonHub</h1>
            <p style="color: var(--texto-suave);">Restablecer contraseña</p>
        </div>
        
        <?php if ($mensaje && !$error): ?>
            <div class="alerta alerta-exito"><?= $mensaje ?></div>
            <?php if (strpos($mensaje, 'actualizada') !== false): ?>
                <p style="text-align: center; margin-top: 15px;">
                    <a href="login.php" class="btn btn-primario">← Ir al inicio de sesión</a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alerta alerta-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if (!$codigo_valido && !isset($_POST['cambiar_password'])): ?>
            <!-- Paso 1: Ingresar código -->
            <form method="POST">
                <div class="campo-grupo">
                    <label for="codigo">Código de verificación</label>
                    <input type="text" id="codigo" name="codigo" required 
                           class="codigo-input" maxlength="6" placeholder="000000"
                           pattern="[0-9]{6}" inputmode="numeric">
                    <small style="color: var(--texto-suave);">Ingresá el código de 6 dígitos que recibiste por email.</small>
                </div>
                
                <button type="submit" name="verificar_codigo" class="btn btn-primario btn-bloque" style="margin-top: 20px;">
                    ✅ Verificar código
                </button>
                
                <p style="text-align: center; margin-top: 15px;">
                    <a href="recuperar_password.php" class="link">← Reenviar código o volver</a>
                </p>
            </form>
        <?php elseif ($codigo_valido || isset($_SESSION['reset_token'])): ?>
            <!-- Paso 2: Cambiar contraseña -->
            <form method="POST">
                <div class="campo-grupo">
                    <label for="nueva_password">Nueva contraseña</label>
                    <input type="password" id="nueva_password" name="nueva_password" required 
                           placeholder="Mín. 8 caracteres, 1 mayúscula, 1 número">
                    <div class="password-strength">
                        <div class="password-strength-fill" id="strengthFill"></div>
                    </div>
                    <small id="strengthText" style="color: var(--texto-suave);"></small>
                </div>
                
                <div class="campo-grupo">
                    <label for="confirmar_password">Confirmar contraseña</label>
                    <input type="password" id="confirmar_password" name="confirmar_password" required>
                </div>
                
                <button type="submit" name="cambiar_password" class="btn btn-primario btn-bloque" style="margin-top: 20px;">
                    🔐 Restablecer contraseña
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Medidor de fortaleza de contraseña
const passInput = document.getElementById('nueva_password');
if (passInput) {
    passInput.addEventListener('input', function() {
        const val = this.value;
        let puntaje = 0;
        if (val.length >= 8) puntaje++;
        if (/[A-Z]/.test(val)) puntaje++;
        if (/[0-9]/.test(val)) puntaje++;
        if (/[^a-zA-Z0-9]/.test(val)) puntaje++;
        
        const fill = document.getElementById('strengthFill');
        const text = document.getElementById('strengthText');
        
        const niveles = [
            { color: '#e74c3c', label: 'Muy débil' },
            { color: '#e67e22', label: 'Débil' },
            { color: '#f1c40f', label: 'Regular' },
            { color: '#2ecc71', label: 'Fuerte' }
        ];
        const nivel = niveles[Math.min(puntaje, 3)];
        fill.style.width = ((puntaje / 4) * 100) + '%';
        fill.style.backgroundColor = nivel.color;
        text.innerHTML = val.length > 0 ? nivel.label : '';
    });
}
</script>
</body>
</html>
