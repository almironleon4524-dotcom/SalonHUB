<?php
// ============================================================
//   SalonHub - Conexion y Funciones de Seguridad
// ============================================================

// Cargar PHPMailer
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Detectar automáticamente si estamos en tu Linux Mint o en internet
if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
    // Configuración para tu Linux Mint (Local)
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'salonhub');
    define('DB_USER', 'root');
    define('DB_PASS', 'salonhub2024_secure'); // Tu contraseña local
} else {
    // Configuración para InfinityFree (Producción en Internet)
    define('DB_HOST', 'sql105.infinityfree.com');
    define('DB_NAME', 'if0_42028327_salonhub');
    define('DB_USER', 'if0_42028327');
    define('DB_PASS', 'l303lcr4ck');
}

define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

function conectar(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
            
            // Si estamos en internet, ocultamos errores en pantalla para que nadie vea datos raros
            if ($_SERVER['SERVER_NAME'] != 'localhost' && $_SERVER['SERVER_NAME'] != '127.0.0.1') {
                ini_set('display_errors', 0);
                ini_set('log_errors', 1);
            }
        } catch (PDOException $e) {
            error_log("Error de conexion: " . $e->getMessage());
            
            // En local mostramos el error real, en internet un mensaje genérico de seguridad
            if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
                die("Error de conexión a la base de datos: " . $e->getMessage());
            } else {
                die("Error de conexión a la base de datos de SalonHub.");
            }
        }
    }
    return $pdo;
}

/**
 * Envía un email usando PHPMailer vía SMTP
 */
function enviar_email($destinatario, $asunto, $mensaje_html) {
    $mail = new PHPMailer(true);

    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'almironleon4524@gmail.com';  // Tu email
        $mail->Password   = 'sikt mebf bpdg pwxu';        // Tu contraseña de aplicación
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Desactivar verificación SSL para pruebas locales
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Destinatarios
        $mail->setFrom('almironleon4524@gmail.com', 'SalonHub');
        $mail->addAddress($destinatario);
        $mail->addReplyTo('soporte@salonhub.com', 'Soporte SalonHub');

        // Contenido
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $asunto;
        $mail->Body    = $mensaje_html;
        $mail->AltBody = strip_tags($mensaje_html);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Error de PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Genera un código de verificación de 6 dígitos
 */
function generar_codigo_verificacion(): string {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Guarda código de verificación en la base de datos
 */
function guardar_codigo_verificacion($email, $codigo) {
    $pdo = conectar();
    $expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    $stmt = $pdo->prepare("UPDATE usuario SET verification_token = :token, reset_token_expira = :expiracion WHERE email = :email");
    return $stmt->execute([':token' => $codigo, ':expiracion' => $expiracion, ':email' => $email]);
}

/**
 * Verifica si un código es válido
 */
function verificar_codigo($email, $codigo) {
    $pdo = conectar();
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuario WHERE email = :email AND verification_token = :token AND reset_token_expira > NOW()");
    $stmt->execute([':email' => $email, ':token' => $codigo]);
    return $stmt->fetch();
}

/**
 * Limpia códigos expirados
 */
function limpiar_codigos_expirados() {
    $pdo = conectar();
    $stmt = $pdo->prepare("UPDATE usuario SET verification_token = NULL, reset_token_expira = NULL WHERE reset_token_expira < NOW()");
    return $stmt->execute();
}
?>
