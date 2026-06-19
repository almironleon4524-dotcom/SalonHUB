<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
                $asunto = '🔐 Recuperá tu contraseña - SalonHub';
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
                        <h2 style='color:#c9a84c;'>✂ SalonHub</h2>
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
                    // Guardar email en sesión para el siguiente paso
                    $_SESSION['reset_email'] = $email;
                    $email_enviado = true;
                    $mensaje = "✅ Se envió un código de verificación a tu email. Tenés 15 minutos para usarlo.";
                } else {
                    $error = "❌ Error al enviar el email. Intentá nuevamente.";
                }
            } else {
                // No revelamos si el email existe o no por seguridad
                $mensaje = "✅ Si el email está registrado, recibirás un código para recuperar tu contraseña.";
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
    <title>Recuperar Contraseña - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        body { background: var(--loki-negro); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .recuperar-container { max-width: 450px; width: 100%; margin: 20px; }
        .card { background: var(--loki-gris); border-radius: 16px; padding: 30px; border: 1px solid var(--borde); }
        .codigo-input { font-size: 2rem; text-align: center; letter-spacing: 10px; font-weight: bold; }
    </style>
</head>
<body>
<div class="recuperar-container">
    <div class="card">
        <div style="text-align: center; margin-bottom: 20px;">
            <h1 style="color: var(--loki-dorado);">✂ SalonHub</h1>
            <p style="color: var(--texto-suave);">Recuperar contraseña</p>
        </div>
        
        <?php if ($email_enviado): ?>
            <div class="alerta alerta-exito"><?= htmlspecialchars($mensaje) ?></div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="restablecer_password.php" class="btn btn-primario">➡️ Ir a restablecer contraseña</a>
                <p style="margin-top: 15px;">
                    <a href="login.php" class="link">← Volver al inicio de sesión</a>
                </p>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="campo-grupo">
                    <label for="email">Email registrado</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="tu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <small style="color: var(--texto-suave);">Te enviaremos un código de 6 dígitos.</small>
                </div>
                
                <button type="submit" name="recuperar" class="btn btn-primario btn-bloque" style="margin-top: 20px;">
                    📧 Enviar código de recuperación
                </button>
                
                <p style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="link">← Volver al inicio de sesión</a>
                </p>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
