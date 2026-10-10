<?php
/**
 * SalonHub - Sistema de Gestión para Salones de Estética
 * Archivo: conexion.php
 * Descripción: Configuración de base de datos y funciones de seguridad
 * Versión: 2.1 - Actualizado con nuevo email SMTP
 */

// ============================================================
//  CARGAR DEPENDENCIAS
// ============================================================
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============================================================
//  CONFIGURACIÓN MULTI-ENTORNO
// ============================================================

// Detectar entorno automáticamente
$es_local = (
    $_SERVER['SERVER_NAME'] === 'localhost' || 
    $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
    strpos($_SERVER['DOCUMENT_ROOT'], '/opt/lampp/') !== false ||
    strpos($_SERVER['DOCUMENT_ROOT'], 'C:\\xampp\\') !== false
);

if ($es_local) {
    // === ENTORNO LOCAL (LAMPP/XAMPP) ===
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'salonhub');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_PORT', '3306');
    define('DB_CHARSET', 'utf8mb4');
    define('ENTORNO', 'local');
} else {
    // === ENTORNO PRODUCCIÓN (InfinityFree) ===
    define('DB_HOST', 'sql105.infinityfree.com');
    define('DB_NAME', 'if0_42028327_salonhub');
    define('DB_USER', 'if0_42028327');
    define('DB_PASS', 'l303lcr4ck');
    define('DB_PORT', '3306');
    define('DB_CHARSET', 'utf8mb4');
    define('ENTORNO', 'produccion');
}

// ============================================================
//  CONEXIÓN A LA BASE DE DATOS
// ============================================================

/**
 * Obtiene una conexión PDO a la base de datos
 * @return PDO
 * @throws PDOException
 */
function conectar(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;port=%s;charset=%s",
            DB_HOST,
            DB_NAME,
            DB_PORT,
            DB_CHARSET
        );
        
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
            
            // Configurar zona horaria
            $pdo->exec("SET time_zone = '-03:00'");
            
            // En producción, ocultar errores al usuario
            if (ENTORNO === 'produccion') {
                ini_set('display_errors', 0);
                ini_set('log_errors', 1);
                error_reporting(E_ALL);
            }
            
        } catch (PDOException $e) {
            // Registrar error en log
            error_log(sprintf(
                "[SalonHub] Error de conexión DB: %s - %s",
                $e->getCode(),
                $e->getMessage()
            ));
            
            // Mostrar mensaje según entorno
            if (ENTORNO === 'local') {
                die(sprintf(
                    '<h2>Error de conexión a la base de datos</h2>
                    <p><strong>Código:</strong> %s</p>
                    <p><strong>Mensaje:</strong> %s</p>
                    <p><strong>Host:</strong> %s</p>
                    <p><strong>Base de datos:</strong> %s</p>
                    <p><small>Verifique que MySQL esté ejecutándose y las credenciales sean correctas.</small></p>',
                    $e->getCode(),
                    $e->getMessage(),
                    DB_HOST,
                    DB_NAME
                ));
            } else {
                // En producción, mensaje genérico
                die(
                    '<h2>Servicio no disponible</h2>
                    <p>Lo sentimos, estamos experimentando problemas técnicos. Por favor, intente más tarde.</p>
                    <p><small>Si el problema persiste, contacte al administrador del sistema.</small></p>'
                );
            }
        }
    }
    
    return $pdo;
}

// ============================================================
//  FUNCIONES DE CORREO ELECTRÓNICO
// ============================================================

/**
 * Envía un correo electrónico usando PHPMailer
 * 
 * @param string $destinatario Email del destinatario
 * @param string $asunto Asunto del correo
 * @param string $mensaje_html Contenido HTML del mensaje
 * @param array $adjuntos (opcional) Lista de rutas de archivos adjuntos
 * @return bool True si se envió correctamente, False en caso contrario
 */
function enviar_email(
    string $destinatario, 
    string $asunto, 
    string $mensaje_html, 
    array $adjuntos = []
): bool {
    // Validar email
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        error_log("Email inválido: " . $destinatario);
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'salonhub26@gmail.com';
        $mail->Password   = 'bkjj vwst hasq qjth';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 30;
        
        // Para entornos locales, desactivar verificación SSL
        if (ENTORNO === 'local') {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }
        
        // Remitente y destinatarios
        $mail->setFrom('salonhub26@gmail.com', 'SalonHub');
        $mail->addAddress($destinatario);
        $mail->addReplyTo('salonhub26@gmail.com', 'Soporte SalonHub');
        
        // Adjuntos
        foreach ($adjuntos as $ruta) {
            if (file_exists($ruta)) {
                $mail->addAttachment($ruta);
            }
        }
        
        // Contenido del mensaje
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $asunto;
        $mail->Body    = $mensaje_html;
        $mail->AltBody = strip_tags($mensaje_html);
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log(sprintf(
            "[SalonHub] Error PHPMailer: %s - %s",
            $mail->ErrorInfo,
            $e->getMessage()
        ));
        return false;
    }
}

// ============================================================
//  FUNCIONES DE SEGURIDAD Y VERIFICACIÓN
// ============================================================

/**
 * Genera un código de verificación de 6 dígitos
 * @return string Código de 6 dígitos
 */
function generar_codigo_verificacion(): string {
    return sprintf('%06d', random_int(0, 999999));
}

/**
 * Guarda el código de verificación en la base de datos
 * 
 * @param string $email Email del usuario
 * @param string $codigo Código de verificación
 * @return bool True si se guardó correctamente
 */
function guardar_codigo_verificacion(string $email, string $codigo): bool {
    $pdo = conectar();
    $expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    $stmt = $pdo->prepare("
        UPDATE usuario 
        SET verification_token = :token, 
            reset_token_expira = :expiracion 
        WHERE email = :email
    ");
    
    return $stmt->execute([
        ':token' => $codigo,
        ':expiracion' => $expiracion,
        ':email' => $email
    ]);
}

/**
 * Verifica si un código de verificación es válido
 * 
 * @param string $email Email del usuario
 * @param string $codigo Código a verificar
 * @return array|false Datos del usuario si es válido, False en caso contrario
 */
function verificar_codigo(string $email, string $codigo) {
    $pdo = conectar();
    
    $stmt = $pdo->prepare("
        SELECT id_usuario, email 
        FROM usuario 
        WHERE email = :email 
        AND verification_token = :token 
        AND reset_token_expira > NOW()
        AND estado = 'activo'
    ");
    
    $stmt->execute([
        ':email' => $email,
        ':token' => $codigo
    ]);
    
    return $stmt->fetch();
}

/**
 * Limpia los códigos de verificación expirados
 * @return bool True si se ejecutó correctamente
 */
function limpiar_codigos_expirados(): bool {
    $pdo = conectar();
    
    $stmt = $pdo->prepare("
        UPDATE usuario 
        SET verification_token = NULL, 
            reset_token_expira = NULL 
        WHERE reset_token_expira < NOW()
    ");
    
    return $stmt->execute();
}

/**
 * Genera un token seguro para sesiones
 * @return string Token aleatorio
 */
function generar_token_seguro(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Verifica si un email ya está registrado
 * @param string $email Email a verificar
 * @return bool True si existe, False si no
 */
function email_existe(string $email): bool {
    $pdo = conectar();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email = :email");
    $stmt->execute([':email' => $email]);
    return $stmt->fetchColumn() > 0;
}

// ============================================================
//  FUNCIONES DE AUDITORÍA
// ============================================================

/**
 * Registra una acción en la bitácora del sistema
 * 
 * @param int $usuario_id ID del usuario que realiza la acción
 * @param string $accion Tipo de acción realizada
 * @param string $detalle Descripción detallada de la acción
 * @return bool True si se registró correctamente
 */
function registrar_bitacora(int $usuario_id, string $accion, string $detalle): bool {
    try {
        $pdo = conectar();
        
        // Crear tabla si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS bitacora (
                id_bitacora INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT,
                accion VARCHAR(50),
                detalle TEXT,
                ip VARCHAR(45),
                user_agent TEXT,
                fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_usuario_accion (usuario_id, accion),
                INDEX idx_fecha (fecha_hora)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (usuario_id, accion, detalle, ip, user_agent) 
            VALUES (:usuario_id, :accion, :detalle, :ip, :user_agent)
        ");
        
        return $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':accion' => $accion,
            ':detalle' => $detalle,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
    } catch (PDOException $e) {
        error_log("Error en bitácora: " . $e->getMessage());
        return false;
    }
}

// ============================================================
//  FUNCIONES DE SEGURIDAD ADICIONALES
// ============================================================

/**
 * Sanitiza una cadena para prevenir XSS
 * @param string $input Texto a sanitizar
 * @return string Texto sanitizado
 */
function sanitizar(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validación básica de teléfono argentino
 * @param string $telefono Número de teléfono
 * @return bool True si es válido
 */
function validar_telefono(string $telefono): bool {
    return (bool) preg_match('/^(?:\+54|0)?[1-9]\d{7,10}$/', $telefono);
}

/**
 * Validación de CUIL/CUIT argentino
 * @param string $cuil CUIL/CUIT a validar
 * @return bool True si es válido
 */
function validar_cuil(string $cuil): bool {
    $cuil = preg_replace('/[-\s]/', '', $cuil);
    
    if (!preg_match('/^\d{11}$/', $cuil)) {
        return false;
    }
    
    $coeficientes = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    
    for ($i = 0; $i < 10; $i++) {
        $suma += intval($cuil[$i]) * $coeficientes[$i];
    }
    
    $resto = $suma % 11;
    $digito_verificador = $resto === 0 ? 0 : 11 - $resto;
    
    return intval($cuil[10]) === $digito_verificador;
}

// ============================================================
//  LIMPIEZA AL FINALIZAR
// ============================================================

// Limpiar códigos expirados automáticamente (1% de las peticiones)
if (mt_rand(1, 100) === 1) {
    limpiar_codigos_expirados();
}