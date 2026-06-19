<?php
session_start();
require_once __DIR__ . '/conexion.php';

if (empty($_SESSION['verificar_email'])) {
    header('Location: login.php');
    exit;
}

$email = $_SESSION['verificar_email'];
$error = '';
$exito = false;

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
            
            $asunto = '📧 Verifica tu cuenta - SalonHub';
            $mensaje_html = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2 style='color: #c9a84c;'>✂ SalonHub</h2>
                <p>Tu código de verificación es:</p>
                <h1 style='background: #1a1f2e; padding: 15px; text-align: center; letter-spacing: 5px;'>{$codigo}</h1>
                <p>Válido por 15 minutos.</p>
            </body>
            </html>
            ";
            
            enviar_email($email, $asunto, $mensaje_html);
            $mensaje = "📨 Nuevo código enviado a tu email.";
        }
    } catch (Exception $e) {
        $error = "Error al reenviar el código.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SalonHub - Verificar Cuenta</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="pagina-auth">
<div class="contenedor-verificar" style="max-width: 450px; margin: 50px auto;">
    <div class="tarjeta-auth">
        <div class="logo-auth">
            <h1>SalonHub</h1>
            <p>Verifica tu cuenta</p>
        </div>
        
        <?php if ($exito): ?>
            <div class="alerta alerta-exito">
                ✅ ¡Cuenta verificada correctamente!
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="login.php" class="btn btn-primario">Iniciar sesión</a>
            </div>
        <?php else: ?>
            <?php if (isset($mensaje)): ?>
                <div class="alerta alerta-exito"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="alerta alerta-info">
                📧 Enviamos un código de verificación a <strong><?= htmlspecialchars($email) ?></strong>
            </div>
            
            <form method="POST">
                <div class="campo-grupo">
                    <label for="codigo">Código de 6 dígitos</label>
                    <input type="text" id="codigo" name="codigo" 
                           class="codigo-input"
                           placeholder="000000"
                           maxlength="6"
                           pattern="\d{6}"
                           required>
                </div>
                
                <button type="submit" class="btn btn-primario btn-bloque">
                    Verificar cuenta
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="verificar_cuenta.php?resend=1" class="link-olvido">📨 Reenviar código</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
