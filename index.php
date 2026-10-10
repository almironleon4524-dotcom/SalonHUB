<?php
// SalonHub - Index

session_start();
require_once __DIR__ . '/conexion.php';

// ==========================================
// VERIFICAR COOKIE "RECORDARME"
// ==========================================
if (!isset($_SESSION['id_usuario']) && isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
    try {
        $pdo = conectar();
        $stmt = $pdo->prepare("
            SELECT id_usuario, usuario, email, rol, id_cliente, id_empleado, estado 
            FROM usuario 
            WHERE id_usuario = :id AND remember_token = :token AND estado = 'activo'
        ");
        $stmt->execute([
            ':id' => (int)$_COOKIE['remember_user'],
            ':token' => $_COOKIE['remember_token']
        ]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['usuario'] = $usuario['usuario'] ?? 'Usuario';
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['rol'] = $usuario['rol'];
            $_SESSION['id_cliente'] = $usuario['id_cliente'];
            $_SESSION['id_empleado'] = $usuario['id_empleado'];
            
            // Actualizar último acceso
            $upd = $pdo->prepare("UPDATE usuario SET ultimo_acceso = NOW() WHERE id_usuario = :id");
            $upd->execute([':id' => $usuario['id_usuario']]);
        }
    } catch (PDOException $e) {
        error_log("Error al verificar cookie recordarme: " . $e->getMessage());
    }
}

// ==========================================
// REDIRIGIR SEGÚN SESIÓN
// ==========================================
if (isset($_SESSION['id_usuario'])) {
    $rol = $_SESSION['rol'] ?? 'cliente';
    
    if ($rol === 'administrador') {
        header('Location: admin_dashboard.php');
    } elseif ($rol === 'empleado') {
        header('Location: panel_empleado.php');
    } else {
        header('Location: home.php');
    }
    exit;
}

// Si no hay sesión, ir al login
header('Location: login.php');
exit;
?>