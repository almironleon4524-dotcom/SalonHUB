<?php



/*SalonHub - Restablecer Contraseña*/



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
                $mensaje = "Código verificado. Ahora podés cambiar tu contraseña.";
            } else {
                $error = "Código inválido o expirado. Solicita uno nuevo.";
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
                
                $mensaje = "Contraseña actualizada correctamente.";
                echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 3000);</script>";
            } else {
                $error = "Código expirado. Solicita uno nuevo.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* =================================================== */
        /* ESTILOS GENERALES (igual que recuperar_password)   */
        /* =================================================== */
        
        body {
            background: #0d1117;
            color: #e6edf3;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .restablecer-container {
            max-width: 450px;
            width: 100%;
            margin: 20px;
        }
        
        .card {
            background: #161b22;
            border-radius: 16px;
            padding: 32px;
            border: 1px solid #30363d;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .card-header {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .card-header h1 {
            color: #c9a84c;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .card-header h1 i {
            margin-right: 10px;
        }
        
        .card-header p {
            color: #8b949e;
            font-size: 0.95rem;
            margin-top: 4px;
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
            font-size: 1.2rem;
        }
        
        .alerta-error {
            background: rgba(248, 81, 73, 0.15);
            border: 1px solid #da3633;
            color: #f85149;
        }
        
        .alerta-error i {
            color: #f85149;
            font-size: 1.2rem;
        }
        
        .text-center {
            text-align: center;
            margin-top: 16px;
        }
        
        .text-center .link {
            display: inline-block;
        }
        
        .link {
            color: #8b949e;
            text-decoration: none;
            transition: color 0.3s;
            font-size: 0.9rem;
        }
        
        .link:hover {
            color: #c9a84c;
        }
        
        /* =================================================== */
        /* FORMULARIO                                         */
        /* =================================================== */
        
        .campo-grupo {
            margin-bottom: 16px;
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
        
        .campo-grupo small {
            display: block;
            color: #8b949e;
            font-size: 0.75rem;
            margin-top: 4px;
        }
        
        /* =================================================== */
        /* CÓDIGO INPUT                                       */
        /* =================================================== */
        
        .codigo-input {
            font-size: 2rem;
            text-align: center;
            letter-spacing: 10px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        /* =================================================== */
        /* FORTALEZA DE CONTRASEÑA                            */
        /* =================================================== */
        
        .password-strength {
            height: 4px;
            background: #30363d;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-fill {
            width: 0%;
            height: 100%;
            transition: width 0.3s, background 0.3s;
            border-radius: 2px;
        }
        
        /* =================================================== */
        /* BOTONES                                            */
        /* =================================================== */
        
        .btn-bloque {
            width: 100%;
            justify-content: center;
        }
        
        .btn-primario {
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
            padding: 12px 24px;
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
        
        /* =================================================== */
        /* SUCCESS                                            */
        /* =================================================== */
        
        .success-actions {
            text-align: center;
            margin-top: 20px;
        }
        
        .success-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        /* =================================================== */
        /* RESPONSIVE                                          */
        /* =================================================== */
        
        @media (max-width: 480px) {
            .card {
                padding: 24px 20px;
            }
            
            .card-header h1 {
                font-size: 1.5rem;
            }
            
            .codigo-input {
                font-size: 1.5rem;
                letter-spacing: 6px;
            }
        }
    </style>
</head>
<body>
<div class="restablecer-container">
    <div class="card">
        <div class="card-header">
            <h1><i class="fas fa-cut"></i> SalonHub</h1>
            <p><i class="fas fa-key"></i> Restablecer contraseña</p>
        </div>
        
        <?php if ($mensaje && !$error): ?>
            <div class="alerta alerta-exito">
                <i class="fas fa-check-circle"></i>
                <?= $mensaje ?>
            </div>
            <?php if (strpos($mensaje, 'actualizada') !== false): ?>
                <div class="success-actions">
                    <a href="login.php" class="btn-primario">
                        <i class="fas fa-arrow-left"></i> Ir al inicio de sesión
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alerta alerta-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$codigo_valido && !isset($_POST['cambiar_password']) && !isset($_SESSION['reset_token'])): ?>
            <!-- Paso 1: Ingresar código -->
            <form method="POST">
                <div class="campo-grupo">
                    <label for="codigo"><i class="fas fa-qrcode"></i> Código de verificación</label>
                    <input type="text" id="codigo" name="codigo" required 
                           class="codigo-input" maxlength="6" placeholder="000000"
                           pattern="[0-9]{6}" inputmode="numeric">
                    <small><i class="fas fa-info-circle"></i> Ingresá el código de 6 dígitos que recibiste por email.</small>
                </div>
                
                <button type="submit" name="verificar_codigo" class="btn-primario btn-bloque">
                    <i class="fas fa-check"></i> Verificar código
                </button>
                
                <div class="text-center">
                    <a href="recuperar_password.php" class="link">
                        <i class="fas fa-arrow-left"></i> Reenviar código o volver
                    </a>
                </div>
            </form>
        <?php elseif ($codigo_valido || isset($_SESSION['reset_token'])): ?>
            <!-- Paso 2: Cambiar contraseña -->
            <form method="POST">
                <div class="campo-grupo">
                    <label for="nueva_password"><i class="fas fa-lock"></i> Nueva contraseña</label>
                    <input type="password" id="nueva_password" name="nueva_password" required 
                           placeholder="Mín. 8 caracteres, 1 mayúscula, 1 número">
                    <div class="password-strength">
                        <div class="password-strength-fill" id="strengthFill"></div>
                    </div>
                    <small id="strengthText" style="color: #8b949e;"></small>
                </div>
                
                <div class="campo-grupo">
                    <label for="confirmar_password"><i class="fas fa-check-circle"></i> Confirmar contraseña</label>
                    <input type="password" id="confirmar_password" name="confirmar_password" required>
                </div>
                
                <button type="submit" name="cambiar_password" class="btn-primario btn-bloque">
                    <i class="fas fa-save"></i> Restablecer contraseña
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
            { color: '#2ecc71', label: 'Fuerte' },
            { color: '#27ae60', label: 'Muy fuerte' }
        ];
        const nivel = niveles[Math.min(puntaje, 4)];
        fill.style.width = ((puntaje / 4) * 100) + '%';
        fill.style.backgroundColor = nivel.color;
        text.innerHTML = val.length > 0 ? '<i class="fas fa-info-circle"></i> ' + nivel.label : '';
    });
}
</script>
</body>
</html>