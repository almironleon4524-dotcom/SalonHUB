<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
/**
 * SalonHub - Recuperar Contraseña
 * Envía un código de verificación al email del usuario
 */

session_start();
require_once __DIR__ . '/conexion.php';

$mensaje = '';
$error = '';
$email_enviado = false;

// Procesar solicitud de recuperación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recuperar'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Ingresá un email válido.";
    } else {
        try {
            $pdo = conectar();
            
            // Verificar si el email existe
            $stmt = $pdo->prepare("SELECT id_usuario, usuario FROM usuario WHERE email = :email AND estado = 'activo'");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                // Generar código de 6 dígitos
                $codigo = sprintf("%06d", mt_rand(0, 999999));
                $expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Guardar código en la base de datos
                $stmt = $pdo->prepare("UPDATE usuario SET verification_token = :token, reset_token_expira = :expiracion WHERE email = :email");
                $stmt->execute([
                    ':token' => $codigo,
                    ':expiracion' => $expiracion,
                    ':email' => $email
                ]);
                
                // Enviar email con el código
                $asunto = 'Recuperá tu contraseña - SalonHub';
                $mensaje_html = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; background: #0d1117; padding: 20px; }
                        .container { max-width: 500px; margin: 0 auto; background: #1a1f2e; border-radius: 10px; padding: 30px; border: 1px solid #c9a84c; }
                        .codigo { font-size: 32px; font-weight: bold; text-align: center; padding: 20px; background: #0d1117; border-radius: 8px; letter-spacing: 5px; color: #c9a84c; }
                        .btn { display: inline-block; background: #c9a84c; color: #0d1117; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2 style='color:#c9a84c;'>SalonHub</h2>
                        <p>Hola <strong>" . htmlspecialchars($usuario['usuario']) . "</strong>,</p>
                        <p>Recibimos una solicitud para restablecer tu contraseña.</p>
                        <p>Usá el siguiente código para continuar:</p>
                        <div class='codigo'>{$codigo}</div>
                        <p style='margin-top: 20px;'>Este código es válido por <strong>15 minutos</strong>.</p>
                        <p>Si no solicitaste esto, ignorá este mensaje.</p>
                    </div>
                </body>
                </html>
                ";
                
                if (enviar_email($email, $asunto, $mensaje_html)) {
                    $_SESSION['reset_email'] = $email;
                    $email_enviado = true;
                    $mensaje = "Se envió un código de verificación a tu email. Tenés 15 minutos para usarlo.";
                } else {
                    $error = "Error al enviar el email. Intentá nuevamente.";
                }
            } else {
                // No revelamos si el email existe o no por seguridad
                $mensaje = "Si el email está registrado, recibirás un código para recuperar tu contraseña.";
                $email_enviado = true;
            }
            
        } catch (PDOException $e) {
            error_log("Error recuperar password: " . $e->getMessage());
            $error = "Error en el sistema. Intentá más tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* =================================================== */
        /* ESTILOS GENERALES (igual que login.php)            */
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
        
        .recuperar-container {
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
        
        .link {
            color: #8b949e;
            text-decoration: none;
            transition: color 0.3s;
            font-size: 0.9rem;
        }
        
        .link:hover {
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
        
        .success-actions .link {
            display: inline-block;
            margin-top: 16px;
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
        }
    </style>
</head>
<body>
<div class="recuperar-container">
    <div class="card">
        <div class="card-header">
            <h1><i class="fas fa-cut"></i> SalonHub</h1>
            <p><i class="fas fa-key"></i> Recuperar contraseña</p>
        </div>
        
        <?php if ($email_enviado): ?>
            <div class="alerta alerta-exito">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($mensaje) ?>
            </div>
            <div class="success-actions">
                <a href="restablecer_password.php" class="btn-primario">
                    <i class="fas fa-arrow-right"></i> Ir a restablecer contraseña
                </a>
                <br>
                <a href="login.php" class="link">
                    <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                </a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alerta alerta-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="campo-grupo">
                    <label for="email"><i class="fas fa-envelope"></i> Email registrado</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="tu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <small><i class="fas fa-info-circle"></i> Te enviaremos un código de 6 dígitos.</small>
                </div>
                
                <button type="submit" name="recuperar" class="btn-primario btn-bloque">
                    <i class="fas fa-paper-plane"></i> Enviar código de recuperación
                </button>
                
                <div class="text-center">
                    <a href="login.php" class="link">
                        <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>