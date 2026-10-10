<?php
/**
 * SalonHub - Cerrar Sesión
 * Muestra pantalla de cierre y redirige al login
 */

session_start();

// ============================================================
// 1. LIMPIAR VARIABLES DE SESIÓN
// ============================================================
$_SESSION = [];

// ============================================================
// 2. ELIMINAR COOKIE DE SESIÓN
// ============================================================
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// ============================================================
// 3. DESTRUIR LA SESIÓN
// ============================================================
session_destroy();

// ============================================================
// 4. MOSTRAR PANTALLA DE CIERRE
// ============================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrando sesión - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* =================================================== */
        /* ESTILOS GENERALES                                  */
        /* =================================================== */
        
        body {
            background: #0d1117;
            color: #e6edf3;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .logout-container {
            text-align: center;
            max-width: 420px;
            width: 100%;
            padding: 50px 40px;
            background: #161b22;
            border-radius: 16px;
            border: 1px solid #30363d;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .logout-icon {
            font-size: 4rem;
            color: #c9a84c;
            display: block;
            margin-bottom: 16px;
        }
        
        .logout-icon i {
            animation: fadeOut 1.5s ease-in-out infinite;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        .logout-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #e6edf3;
            margin-bottom: 8px;
        }
        
        .logout-title span {
            color: #c9a84c;
        }
        
        .logout-subtitle {
            color: #8b949e;
            font-size: 1rem;
            margin-bottom: 30px;
        }
        
        .spinner-container {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 24px;
        }
        
        .spinner-dot {
            width: 12px;
            height: 12px;
            background: #c9a84c;
            border-radius: 50%;
            animation: bounce 1.2s ease-in-out infinite;
        }
        
        .spinner-dot:nth-child(1) { animation-delay: 0s; }
        .spinner-dot:nth-child(2) { animation-delay: 0.2s; }
        .spinner-dot:nth-child(3) { animation-delay: 0.4s; }
        .spinner-dot:nth-child(4) { animation-delay: 0.6s; }
        .spinner-dot:nth-child(5) { animation-delay: 0.8s; }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); opacity: 0.4; }
            50% { transform: translateY(-12px); opacity: 1; }
        }
        
        .btn-login {
            display: inline-block;
            padding: 10px 32px;
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
        }
        
        .btn-login i {
            margin-right: 8px;
        }
        
        .logout-footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #30363d;
            color: #8b949e;
            font-size: 0.75rem;
        }
        
        .logout-footer span {
            color: #c9a84c;
            font-weight: 600;
        }
        
        .hidden {
            display: none;
        }
    </style>
    <!-- Redirigir después de 3 segundos -->
    <meta http-equiv="refresh" content="3;url=login.php?logout=1">
</head>
<body>
    <div class="logout-container">
        <span class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </span>
        
        <h1 class="logout-title"><span>SalonHub</span></h1>
        <p class="logout-subtitle">Cerrando sesión...</p>
        
        <div class="spinner-container">
            <div class="spinner-dot"></div>
            <div class="spinner-dot"></div>
            <div class="spinner-dot"></div>
            <div class="spinner-dot"></div>
            <div class="spinner-dot"></div>
        </div>
        
        <a href="login.php" class="btn-login">
            <i class="fas fa-arrow-right"></i> Ir al login
        </a>
        
        <div class="logout-footer">
            &copy; <?= date('Y') ?> <span>SalonHub</span> — El poder de la transformación
        </div>
    </div>
</body>
</html>
<?php
exit;