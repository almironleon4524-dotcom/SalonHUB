<?php



/*SalonHub - Verificar Cuenta*/



session_start();
require_once __DIR__ . '/conexion.php';

if (empty($_SESSION['verificar_email'])) {
    header('Location: login.php');
    exit;
}

$email = $_SESSION['verificar_email'];
$error = '';
$exito = false;
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    
    $usuario = verificar_codigo($email, $codigo);
    
    if ($usuario) {
        
        // Marcar email como verificado
        $pdo = conectar();
        $stmt = $pdo->prepare("UPDATE usuario SET email_verificado = 1, verification_token = NULL, reset_token_expira = NULL WHERE id_usuario = :id");
        $stmt->execute([':id' => $usuario['id_usuario']]);
        
        unset($_SESSION['verificar_email']);
        $exito = true;
    } else {
        $error = 'Código inválido o expirado.';
    }
}

// Reenviar código
if (isset($_GET['resend'])) {
    try {
        $pdo = conectar();
        $stmt = $pdo->prepare("SELECT usuario FROM usuario WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $codigo = generar_codigo_verificacion();
            guardar_codigo_verificacion($email, $codigo);
            
            $asunto = 'Verifica tu cuenta - SalonHub';
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
                    <h2 style='color:#c9a84c;'>SalonHub</h2>
                    <p>Hola <strong>" . htmlspecialchars($user['usuario']) . "</strong>,</p>
                    <p>Tu código de verificación es:</p>
                    <div class='codigo'>{$codigo}</div>
                    <p>Válido por 15 minutos.</p>
                </div>
            </body>
            </html>
            ";
            
            enviar_email($email, $asunto, $mensaje_html);
            $mensaje = "Nuevo código enviado a tu email.";
        }
    } catch (Exception $e) {
        error_log("Error al reenviar código: " . $e->getMessage());
        $error = "Error al reenviar el código.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Verificar Cuenta</title>
    <link rel="stylesheet" href="css/estilos.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
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
        
        .contenedor-verificar {
            max-width: 450px;
            width: 100%;
            margin: 20px auto;
        }
        
        .tarjeta-auth {
            background: #161b22;
            border-radius: 16px;
            padding: 32px;
            border: 1px solid #30363d;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .logo-auth {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .logo-auth h1 {
            color: #c9a84c;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .logo-auth h1 i {
            margin-right: 10px;
        }
        
        .logo-auth p {
            color: #8b949e;
            font-size: 0.95rem;
            margin-top: 4px;
        }
        
        .alerta {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 16px;
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
        
        .alerta-info {
            background: rgba(88, 166, 255, 0.08);
            border: 1px solid #30363d;
            color: #8b949e;
        }
        
        .alerta-info i {
            color: #58a6ff;
            font-size: 1.2rem;
        }
        
        .alerta-info strong {
            color: #e6edf3;
        }
        
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
        
        .codigo-input {
            font-size: 2rem;
            text-align: center;
            letter-spacing: 10px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
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
        
        .link-olvido {
            color: #8b949e;
            text-decoration: none;
            transition: color 0.3s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .link-olvido:hover {
            color: #c9a84c;
        }
        
        .text-center {
            text-align: center;
            margin-top: 20px;
        }
        
        .success-actions {
            text-align: center;
            margin-top: 20px;
        }
        
        .success-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        @media (max-width: 480px) {
            .tarjeta-auth {
                padding: 24px 20px;
            }
            
            .logo-auth h1 {
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
<div class="contenedor-verificar">
    <div class="tarjeta-auth">
        <div class="logo-auth">
            <h1><i class="fas fa-cut"></i> SalonHub</h1>
            <p><i class="fas fa-envelope"></i> Verifica tu cuenta</p>
        </div>
        
        <?php if ($exito): ?>
            <div class="alerta alerta-exito">
                <i class="fas fa-check-circle"></i>
                Cuenta verificada correctamente.
            </div>
            <div class="success-actions">
                <a href="login.php" class="btn-primario">
                    <i class="fas fa-sign-in-alt"></i> Iniciar sesión
                </a>
            </div>
        <?php else: ?>
            <?php if (isset($mensaje)): ?>
                <div class="alerta alerta-exito">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alerta alerta-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="alerta alerta-info">
                <i class="fas fa-info-circle"></i>
                Enviamos un código de verificación a <strong><?= htmlspecialchars($email) ?></strong>
            </div>
            
            <form method="POST">
                <div class="campo-grupo">
                    <label for="codigo"><i class="fas fa-qrcode"></i> Código de 6 dígitos</label>
                    <input type="text" id="codigo" name="codigo" 
                           class="codigo-input"
                           placeholder="000000"
                           maxlength="6"
                           pattern="\d{6}"
                           required>
                </div>
                
                <button type="submit" class="btn-primario btn-bloque">
                    <i class="fas fa-check"></i> Verificar cuenta
                </button>
            </form>
            
            <div class="text-center">
                <a href="verificar_cuenta.php?resend=1" class="link-olvido">
                    <i class="fas fa-paper-plane"></i> Reenviar código
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>