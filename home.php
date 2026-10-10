<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Home

session_start();
require_once __DIR__ . '/permisos_helper.php';
require_once __DIR__ . '/notificaciones_helper.php';

// Verificar si está logueado
$logueado = esta_logueado();
$rol = $_SESSION['rol'] ?? 'invitado';
$usuario = $_SESSION['usuario'] ?? 'Invitado';
$bienvenido = isset($_GET['bienvenido']);

// Cargar permisos solo si está logueado
$mis_permisos = $logueado ? obtener_todos_permisos((int)$_SESSION['id_usuario']) : [];

// Limpieza automática de notificaciones viejas (1 vez por día)
if ($logueado) {
    ejecutar_limpieza_notificaciones();
}

// Obtener servicios destacados
$pdo = conectar();
$servicios_destacados = $pdo->query("SELECT id_servicio, nombre, descripcion, duracion, precio FROM servicios WHERE estado = 'activo' LIMIT 6")->fetchAll();

// Contador de notificaciones no leídas (por usuario + por rol)
$notificaciones_no_leidas = 0;
$prioridad_maxima = 'baja';

if ($logueado) {
    try {
        $id_usuario_actual = $_SESSION['id_usuario'];
        $rol_actual = $_SESSION['rol'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM notificaciones 
            WHERE leido = 0 
              AND (id_usuario = ? OR (id_usuario IS NULL AND rol_destino = ?))
        ");
        $stmt->execute([$id_usuario_actual, $rol_actual]);
        $notificaciones_no_leidas = (int)$stmt->fetchColumn();
        
        // Obtener prioridad más alta para el color del badge
        if ($notificaciones_no_leidas > 0) {
            $stmt = $pdo->prepare("
                SELECT prioridad 
                FROM notificaciones 
                WHERE leido = 0 
                  AND (id_usuario = ? OR (id_usuario IS NULL AND rol_destino = ?))
                ORDER BY FIELD(prioridad, 'critica', 'alta', 'media', 'baja')
                LIMIT 1
            ");
            $stmt->execute([$id_usuario_actual, $rol_actual]);
            $prioridad_maxima = $stmt->fetchColumn() ?: 'baja';
        }
    } catch (PDOException $e) {
        $notificaciones_no_leidas = 0;
        $prioridad_maxima = 'baja';
    }
}

// Tab activa (por defecto 'local')
$tab_activa = $_GET['tab'] ?? 'local';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub | Inicio</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Estilos de Tabs */
        .tabs-container { margin-bottom: 30px; }
        .tabs-buttons {
            display: flex;
            border-bottom: 2px solid var(--borde);
            background: rgba(26, 31, 46, 0.5);
            border-radius: 12px 12px 0 0;
            overflow: hidden;
        }
        .tab-btn {
            flex: 1;
            padding: 16px 24px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: var(--texto-suave);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
        }
        .tab-btn i { font-size: 1.1rem; color: var(--loki-dorado); }
        .tab-btn:hover { color: var(--loki-dorado); background: rgba(201, 168, 76, 0.05); }
        .tab-btn.activo { color: var(--loki-dorado); background: rgba(201, 168, 76, 0.1); }
        .tab-btn.activo::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--loki-dorado), var(--loki-dorado-claro));
        }
        .tab-content { display: none; animation: fadeIn 0.4s ease; }
        .tab-content.activo { display: block; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Estilos Seccion Local */
        .hero-section {
            background: linear-gradient(135deg, rgba(13, 17, 23, 0.95), rgba(10, 61, 46, 0.9));
            border-radius: 20px;
            padding: 60px 40px;
            margin-bottom: 40px;
            text-align: center;
            border: 1px solid rgba(201, 168, 76, 0.3);
            position: relative;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="rgba(201,168,76,0.05)" d="M20,20 L80,20 L80,80 L20,80 Z"/><circle cx="50" cy="50" r="15" fill="rgba(201,168,76,0.03)"/></svg>');
            background-size: 60px;
            pointer-events: none;
        }
        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--loki-dorado), var(--loki-dorado-claro));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            position: relative;
        }
        .hero-subtitle {
            font-size: 1.2rem;
            color: var(--texto-suave);
            max-width: 600px;
            margin: 0 auto 30px;
            position: relative;
        }
        .btn-hero {
            background: linear-gradient(135deg, var(--loki-dorado), #b8943a);
            color: var(--loki-negro);
            padding: 14px 32px;
            border-radius: 40px;
            font-weight: 700;
            display: inline-block;
            transition: all 0.3s;
            position: relative;
        }
        .btn-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.4);
            color: var(--loki-negro);
        }
        .btn-hero i { margin-right: 8px; }
        .servicios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin: 30px 0;
        }
        .servicio-card {
            background: var(--loki-gris);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--borde);
            transition: all 0.3s;
        }
        .servicio-card:hover { transform: translateY(-5px); border-color: var(--loki-dorado); }
        .servicio-nombre { font-size: 1.2rem; font-weight: 700; color: var(--loki-dorado); margin-bottom: 8px; }
        .servicio-desc { font-size: 0.85rem; color: var(--texto-suave); margin-bottom: 12px; }
        .servicio-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--borde);
        }
        .servicio-duracion { font-size: 0.8rem; color: var(--texto-suave); }
        .servicio-duracion i { margin-right: 4px; }
        .servicio-precio { font-size: 1.2rem; font-weight: 700; color: var(--loki-verde-claro); }
        .info-contacto {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            background: var(--loki-gris);
            border-radius: 20px;
            padding: 40px;
            margin: 40px 0;
            text-align: center;
        }
        .contacto-item { display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .contacto-icono { font-size: 2rem; color: var(--loki-dorado); }
        .contacto-item h4 { color: var(--loki-dorado); font-size: 1rem; }
        .contacto-item p, .contacto-item a { color: var(--texto-suave); text-decoration: none; }
        .contacto-item a:hover { color: var(--loki-dorado); }
        
        /* Estilos Seccion Panel */
        .panel-header { margin-bottom: 30px; }
        .panel-header h2 {
            font-size: 1.5rem;
            border-bottom: 2px solid var(--borde);
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--texto);
        }
        .panel-header h2 i { color: var(--loki-dorado); }
        .grilla-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }
        .tarjeta-panel {
            background: var(--loki-gris);
            border-radius: 14px;
            padding: 24px 20px;
            border: 1px solid var(--borde);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .tarjeta-panel::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 3px;
        }
        .tarjeta-panel:not(.tarjeta-empleado):not(.tarjeta-admin)::before {
            background: linear-gradient(90deg, var(--loki-dorado), var(--loki-dorado-claro));
        }
        .tarjeta-empleado::before {
            background: linear-gradient(90deg, var(--loki-verde), var(--loki-verde-claro));
        }
        .tarjeta-admin::before {
            background: linear-gradient(90deg, var(--loki-dorado), #f0d060);
        }
        .tarjeta-panel:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(201, 168, 76, 0.5);
        }
        .tarjeta-icono {
            font-size: 1.4rem;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(201, 168, 76, 0.1);
            border-radius: 12px;
            margin-bottom: 12px;
            color: var(--loki-dorado);
        }
        .tarjeta-empleado .tarjeta-icono { background: rgba(15, 92, 69, 0.2); color: var(--loki-verde-claro); }
        .tarjeta-admin .tarjeta-icono { background: rgba(201, 168, 76, 0.15); color: var(--loki-dorado); }
        .tarjeta-panel h3 { font-size: 1rem; color: var(--texto); font-weight: 600; margin-bottom: 8px; }
        .tarjeta-panel p { font-size: 0.8rem; color: var(--texto-suave); margin-bottom: 16px; line-height: 1.4; }
        .tarjeta-panel .btn {
            font-size: 0.8rem;
            padding: 8px 16px;
            display: inline-block;
            width: fit-content;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-primario {
            background: rgba(201, 168, 76, 0.15);
            color: var(--loki-dorado);
            border: 1px solid rgba(201, 168, 76, 0.2);
        }
        .btn-primario:hover { background: var(--loki-dorado); color: var(--loki-negro); }
        .btn-admin {
            background: linear-gradient(135deg, var(--loki-dorado), #b8943a);
            color: var(--loki-negro);
            font-weight: 600;
        }
        .btn-empleado { background: var(--loki-verde); color: white; }
        .btn-secundario { background: rgba(255, 255, 255, 0.08); color: var(--texto); border: 1px solid var(--borde); }
        .btn-secundario:hover { background: rgba(255, 255, 255, 0.15); }
        .alerta-bienvenida {
            background: linear-gradient(135deg, rgba(201, 168, 76, 0.15), rgba(15, 92, 69, 0.1));
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 28px;
        }
        
        /* Estilos Tablas Maestras - Acordeon */
        .tablas-maestras-container { margin-top: 30px; grid-column: 1 / -1; }
        .tablas-maestras-container .tarjeta-panel {
            background: rgba(201, 168, 76, 0.05);
            border-color: rgba(201, 168, 76, 0.3);
        }
        .accordion-header {
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: rgba(201, 168, 76, 0.08);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .accordion-header:hover { background: rgba(201, 168, 76, 0.15); }
        .accordion-header .titulo { display: flex; align-items: center; gap: 12px; }
        .accordion-header .titulo i { color: var(--loki-dorado); }
        .accordion-header .icono-chevron { transition: transform 0.3s ease; color: var(--loki-dorado); }
        .accordion-header .icono-chevron.rotado { transform: rotate(180deg); }
        .accordion-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
        }
        .accordion-body.abierto { max-height: 2000px; }
        .accordion-body .contenido { padding: 16px 4px 4px 4px; }
        .tablas-maestras-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 8px;
        }
        .tabla-maestra-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--borde);
            border-radius: 10px;
            padding: 14px 16px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .tabla-maestra-card:hover {
            border-color: var(--loki-dorado);
            background: rgba(201, 168, 76, 0.08);
            transform: translateX(4px);
        }
        .tabla-maestra-card .icono {
            font-size: 1.1rem;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(201, 168, 76, 0.1);
            border-radius: 8px;
            flex-shrink: 0;
            color: var(--loki-dorado);
        }
        .tabla-maestra-card .info { flex: 1; min-width: 0; }
        .tabla-maestra-card .info h4 {
            font-size: 0.85rem;
            color: var(--texto);
            font-weight: 600;
            margin-bottom: 2px;
        }
        .tabla-maestra-card .info small { font-size: 0.65rem; color: var(--texto-suave); }
        .tabla-maestra-card .btn-sm {
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
            background: rgba(201, 168, 76, 0.15);
            color: var(--loki-dorado);
            border: 1px solid rgba(201, 168, 76, 0.2);
            transition: all 0.3s;
            text-decoration: none;
            white-space: nowrap;
        }
        .tabla-maestra-card .btn-sm:hover { background: var(--loki-dorado); color: var(--loki-negro); }
        
        /* Header y Footer */
        .header-panel {
            background: var(--loki-gris);
            border-bottom: 1px solid var(--borde);
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-contenido {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 12px 0;
        }
        .logo-header a {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--loki-dorado);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-header a i { font-size: 1.8rem; }
        .nav-header { display: flex; align-items: center; gap: 16px; }
        .usuario-info {
            color: var(--texto-suave);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .usuario-info i { font-size: 1.1rem; color: var(--loki-dorado); }
        .badge-rol {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-admin { background: var(--loki-dorado); color: var(--loki-negro); }
        .badge-empleado { background: var(--loki-verde); color: white; }
        .badge-cliente { background: #4a5568; color: white; }
        .badge-invitado { background: #6b7280; color: white; }
        .btn-outline-sm {
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.3s;
            background: transparent;
            border: 1px solid var(--borde);
            color: var(--texto-suave);
        }
        .btn-outline-sm:hover { border-color: var(--loki-dorado); color: var(--loki-dorado); }
        .btn-danger-outline:hover { border-color: #e74c3c; color: #e74c3c; }
        .footer-panel {
            text-align: center;
            padding: 30px 20px 20px;
            border-top: 1px solid var(--borde);
            margin-top: 40px;
            color: var(--texto-suave);
        }
        .footer-panel span { color: var(--loki-dorado); font-weight: 600; }
        
        /* Estilos Campanita de Notificaciones */
        .notificaciones-container { position: relative; }
        .btn-notificaciones {
            background: transparent;
            border: 1px solid var(--borde);
            color: var(--texto-suave);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.3s;
            position: relative;
        }
        .btn-notificaciones:hover {
            border-color: var(--loki-dorado);
            color: var(--loki-dorado);
            background: rgba(201, 168, 76, 0.1);
        }
        .badge-notificaciones {
            position: absolute;
            top: -5px;
            right: -5px;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }

        /* Colores por prioridad */
        .badge-notificaciones.badge-critica {
            background: #f85149;
            animation: pulse-badge 1.5s infinite;
        }
        .badge-notificaciones.badge-alta { background: #d29922; }
        .badge-notificaciones.badge-media { background: #58a6ff; }
        .badge-notificaciones.badge-baja { background: #6b7280; }

        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(248, 81, 73, 0.7); }
            50% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(248, 81, 73, 0); }
        }

        .panel-notificaciones {
            position: absolute;
            top: 50px;
            right: 0;
            width: 380px;
            max-height: 550px;
            background: var(--loki-gris);
            border: 1px solid var(--borde);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            display: none;
            z-index: 1000;
            overflow: hidden;
        }
        .panel-notificaciones.abierto { display: block; animation: slideDown 0.3s ease; }
        .panel-notificaciones .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid var(--borde);
            background: rgba(201, 168, 76, 0.05);
            margin-bottom: 0;
        }
        .panel-notificaciones .panel-header h4 { color: var(--loki-dorado); font-size: 0.9rem; margin: 0; }
        .btn-marcar-todas {
            background: transparent;
            border: none;
            color: var(--texto-suave);
            font-size: 0.75rem;
            cursor: pointer;
            transition: color 0.3s;
        }
        .btn-marcar-todas:hover { color: var(--loki-dorado); }
        .panel-notificaciones .panel-body { max-height: 400px; overflow-y: auto; }
        .notificacion-item {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            transition: background 0.3s;
            cursor: pointer;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .notificacion-item:hover { background: rgba(255, 255, 255, 0.03); }
        .notificacion-item.no-leida {
            background: rgba(201, 168, 76, 0.05);
            border-left: 3px solid var(--loki-dorado);
        }
        .notificacion-item .icono {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(201, 168, 76, 0.15);
            color: var(--loki-dorado);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        .notificacion-item .contenido { flex: 1; min-width: 0; }
        .notificacion-item .titulo-notif {
            color: var(--loki-dorado);
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .notificacion-item .mensaje { color: var(--texto); font-size: 0.85rem; margin-bottom: 4px; line-height: 1.3; }
        .notificacion-item .fecha { color: var(--texto-suave); font-size: 0.7rem; }
        .sin-notificaciones { text-align: center; padding: 40px 20px; color: var(--texto-suave); }
        .sin-notificaciones i { font-size: 2rem; display: block; margin-bottom: 10px; color: var(--borde); }

        /* Footer del panel de notificaciones */
        .panel-footer-notif {
            padding: 12px 16px;
            border-top: 1px solid var(--borde);
            background: rgba(201, 168, 76, 0.03);
            text-align: center;
        }
        .btn-ver-todas {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--loki-dorado);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s;
            padding: 6px 12px;
            border-radius: 6px;
        }
        .btn-ver-todas:hover {
            background: rgba(201, 168, 76, 0.1);
            color: var(--loki-dorado-claro);
        }
        
        @media (max-width: 768px) {
            .hero-title { font-size: 2rem; }
            .hero-section { padding: 40px 20px; }
            .info-contacto { padding: 30px 20px; }
            .tab-btn { padding: 12px 16px; font-size: 0.85rem; }
            .tab-btn span { display: none; }
            .grilla-tarjetas { grid-template-columns: 1fr; }
            .tablas-maestras-grid { grid-template-columns: 1fr; }
            .header-contenido { flex-wrap: wrap; gap: 10px; }
            .nav-header { flex-wrap: wrap; gap: 8px; }
            .usuario-info { font-size: 0.8rem; }
            .panel-notificaciones { width: calc(100vw - 40px); right: -10px; }
        }
    </style>
</head>
<body>

<header class="header-panel">
    <div class="header-contenido">
        <h1 class="logo-header">
            <a href="home.php">
                <i class="fas fa-cut"></i> SalonHub
            </a>
        </h1>
        <nav class="nav-header">
            <?php if ($logueado): ?>
                <!-- Campanita de notificaciones -->
                <div class="notificaciones-container">
                    <button class="btn-notificaciones" onclick="toggleNotificaciones()" id="btnCampanita">
                        <i class="fas fa-bell"></i>
                        <span class="badge-notificaciones badge-<?= $prioridad_maxima ?>" id="badgeNotificaciones" style="<?= $notificaciones_no_leidas > 0 ? '' : 'display:none;' ?>">
                            <?= $notificaciones_no_leidas > 99 ? '99+' : $notificaciones_no_leidas ?>
                        </span>
                    </button>
                    <div class="panel-notificaciones" id="panelNotificaciones">
                        <div class="panel-header">
                            <h4><i class="fas fa-bell"></i> Notificaciones</h4>
                            <button onclick="marcarTodasLeidas()" class="btn-marcar-todas">
                                <i class="fas fa-check-double"></i> Marcar todas
                            </button>
                        </div>
                        <div class="panel-body" id="listaNotificaciones">
                            <div class="sin-notificaciones">
                                <i class="fas fa-inbox"></i>
                                <p>Cargando...</p>
                            </div>
                        </div>
                        <div class="panel-footer-notif">
                            <a href="notificaciones.php" class="btn-ver-todas">
                                <i class="fas fa-list"></i> Ver todas las notificaciones
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Info de usuario -->
                <span class="usuario-info">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($usuario) ?>
                    <span class="badge-rol badge-<?= $rol ?>"><?= ucfirst($rol) ?></span>
                </span>
                <a href="perfil.php" class="btn-outline-sm"><i class="fas fa-user"></i> Mi Perfil</a>
                <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
            <?php else: ?>
                <!-- Invitado -->
                <span class="usuario-info">
                    <i class="fas fa-user-secret"></i> Invitado
                    <span class="badge-rol badge-invitado">Invitado</span>
                </span>
                <a href="login.php" class="btn-outline-sm" style="border-color: #c9a84c; color: #c9a84c;">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </a>
                <a href="login.php?modo=registro" class="btn-outline-sm" style="background: #c9a84c; color: #0d1117; border-color: #c9a84c;">
                    <i class="fas fa-user-plus"></i> Registrarse
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="contenedor-panel" style="max-width: 1400px; margin: 0 auto; padding: 20px;">

    <?php if ($bienvenido && $logueado): ?>
        <div class="alerta-bienvenida">
            <i class="fas fa-gem" style="color: var(--loki-dorado);"></i>
            <strong>Bienvenido/a a SalonHub, <?= htmlspecialchars($usuario) ?>!</strong> El poder de la transformación te espera.
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs-buttons">
            <button class="tab-btn <?= $tab_activa === 'local' ? 'activo' : '' ?>" onclick="cambiarTab('local')">
                <i class="fas fa-home"></i> <span>Página Principal</span>
            </button>
            <?php if ($logueado): ?>
            <button class="tab-btn <?= $tab_activa === 'panel' ? 'activo' : '' ?>" onclick="cambiarTab('panel')">
                <i class="fas fa-tools"></i> <span>Panel de Herramientas</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 1: Pagina Principal -->
    <div id="tab-local" class="tab-content <?= $tab_activa === 'local' ? 'activo' : '' ?>">
        
        <div class="hero-section">
            <div class="hero-title">SalonHub</div>
            <div class="hero-subtitle">El poder de la transformación. Cortes, color, manicuría y más en un solo lugar.</div>
            <?php if ($logueado && ($mis_permisos['reservar_citas'] ?? false)): ?>
                <a href="nueva_cita.php" class="btn-hero"><i class="fas fa-calendar-plus"></i> Reservar tu turno ahora</a>
            <?php else: ?>
                <a href="login.php" class="btn-hero" onclick="return confirmarAccion('reservar un turno')">
                    <i class="fas fa-calendar-plus"></i> Reservar tu turno ahora
                </a>
            <?php endif; ?>
        </div>

        <h2 style="margin-bottom: 20px; color: var(--loki-dorado);"><i class="fas fa-star"></i> Servicios Destacados</h2>
        <div class="servicios-grid">
            <?php foreach ($servicios_destacados as $servicio): ?>
            <div class="servicio-card">
                <div class="servicio-nombre"><?= htmlspecialchars($servicio['nombre']) ?></div>
                <div class="servicio-desc"><?= htmlspecialchars($servicio['descripcion'] ?? 'Descubre este servicio exclusivo en SalonHub') ?></div>
                <div class="servicio-info">
                    <span class="servicio-duracion"><i class="far fa-clock"></i> <?= htmlspecialchars($servicio['duracion']) ?></span>
                    <span class="servicio-precio">$<?= number_format($servicio['precio'], 0, ',', '.') ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="info-contacto">
            <div class="contacto-item">
                <div class="contacto-icono"><i class="fas fa-map-marker-alt"></i></div>
                <h4>Ubicación</h4>
                <p>Av. 25 de Mayo 1355<br>Formosa Capital</p>
            </div>
            <div class="contacto-item">
                <div class="contacto-icono"><i class="fas fa-phone"></i></div>
                <h4>Teléfono</h4>
                <p><a href="tel:+54 9 370 486-3574">+54 9 3704 486-3574</a></p>
            </div>
            <div class="contacto-item">
                <div class="contacto-icono"><i class="fas fa-envelope"></i></div>
                <h4>Email</h4>
                <p><a href="mailto:almironleon4524@gmail.com">contacto@salonhub.com</a></p>
            </div>
            <div class="contacto-item">
                <div class="contacto-icono"><i class="far fa-clock"></i></div>
                <h4>Horarios</h4>
                <p>Lun a Vie: 9:00 - 20:00<br>Sáb: 10:00 - 18:00</p>
            </div>
        </div>
    </div>

    <!-- Tab 2: Panel de Herramientas (Solo logueados) -->
    <?php if ($logueado): ?>
    <div id="tab-panel" class="tab-content <?= $tab_activa === 'panel' ? 'activo' : '' ?>">
        
        <div class="panel-header">
            <h2><i class="fas fa-tools"></i> Panel de Herramientas</h2>
        </div>
        
        <div class="grilla-tarjetas">

            <!-- Tarjetas para todos los usuarios -->
            <div class="tarjeta-panel">
                <div class="tarjeta-icono"><i class="fas fa-user"></i></div>
                <h3>Mi Perfil</h3>
                <p>Ver y editar tus datos personales, cambiar contraseña.</p>
                <a href="perfil.php" class="btn btn-primario"><i class="fas fa-arrow-right"></i> Ir a Mi Perfil</a>
            </div>

            <div class="tarjeta-panel">
                <div class="tarjeta-icono"><i class="fas fa-calendar-alt"></i></div>
                <h3>Mis Citas</h3>
                <p>Revisá tus citas programadas y su estado actual.</p>
                <a href="mis_citas.php" class="btn btn-primario"><i class="fas fa-arrow-right"></i> Ver mis citas</a>
            </div>

            <div class="tarjeta-panel">
                <div class="tarjeta-icono"><i class="fas fa-plus-circle"></i></div>
                <h3>Reservar Turno</h3>
                <p>Agendá un nuevo turno en el salón fácilmente.</p>
                <a href="nueva_cita.php" class="btn btn-secundario"><i class="fas fa-calendar-plus"></i> Reservar</a>
            </div>

            <div class="tarjeta-panel">
                <div class="tarjeta-icono"><i class="fas fa-bell"></i></div>
                <h3>Notificaciones</h3>
                <p>Ver todas tus notificaciones y gestionar preferencias.</p>
                <a href="notificaciones.php" class="btn btn-primario"><i class="fas fa-arrow-right"></i> Ver Notificaciones</a>
            </div>

            <!-- Tarjeta para empleados -->
            <?php if ($mis_permisos['acceso_panel_empleado'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-empleado">
                <div class="tarjeta-icono"><i class="fas fa-clipboard-list"></i></div>
                <h3>Mis Citas Asignadas</h3>
                <p>Ver y gestionar las citas que te asignaron.</p>
                <a href="panel_empleado.php" class="btn btn-empleado"><i class="fas fa-arrow-right"></i> Ver Mis Citas</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta para admin: Gestionar Citas -->
            <?php if ($mis_permisos['gestion_citas'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono"><i class="fas fa-calendar-check"></i></div>
                <h3>Gestionar Citas</h3>
                <p>Ver todas las citas, asignar empleados y estados.</p>
                <a href="panel_admin_citas.php" class="btn btn-admin"><i class="fas fa-arrow-right"></i> Admin Citas</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta para admin: Gestionar Usuarios -->
            <?php if ($mis_permisos['gestion_usuarios'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono"><i class="fas fa-user-cog"></i></div>
                <h3>Gestionar Usuarios</h3>
                <p>Administrar clientes, empleados y permisos especiales.</p>
                <a href="abm.php" class="btn btn-admin"><i class="fas fa-arrow-right"></i> ABM Usuarios</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta para admin: Dashboard -->
            <?php if (($mis_permisos['acceso_panel_admin'] ?? false) || ($mis_permisos['ver_reportes'] ?? false)): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono"><i class="fas fa-chart-bar"></i></div>
                <h3>Dashboard</h3>
                <p>Métricas, estadísticas y reportes del sistema.</p>
                <a href="admin_dashboard.php" class="btn btn-admin"><i class="fas fa-arrow-right"></i> Ver Dashboard</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta para admin: Cierre de Caja -->
            <?php if ($mis_permisos['cerrar_caja'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono"><i class="fas fa-cash-register"></i></div>
                <h3>Cierre de Caja</h3>
                <p>Resumen de pagos y cierre del día.</p>
                <a href="admin_cierre_caja.php" class="btn btn-admin"><i class="fas fa-arrow-right"></i> Cerrar Caja</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta para admin: Configuracion -->
            <?php if ($mis_permisos['gestion_configuracion'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono"><i class="fas fa-cog"></i></div>
                <h3>Configuración</h3>
                <p>Ajustes generales del sistema.</p>
                <a href="admin_configuracion.php" class="btn btn-admin"><i class="fas fa-arrow-right"></i> Configuración</a>
            </div>
            <?php endif; ?>

            <!-- Seccion Tablas Maestras (Acordeon) -->
            <?php if ($mis_permisos['gestion_categorias'] ?? false): ?>
            <div class="tablas-maestras-container">
                <div class="tarjeta-panel tarjeta-admin">
                    
                    <!-- Encabezado del Acordeon -->
                    <div class="accordion-header" onclick="toggleAccordion()">
                        <div class="titulo">
                            <i class="fas fa-database"></i>
                            <span style="font-weight: 600; color: var(--loki-dorado);">Tablas Maestras</span>
                            <span style="font-size: 0.6rem; background: rgba(201, 168, 76, 0.15); padding: 2px 12px; border-radius: 20px; color: var(--loki-dorado);">Módulo 2</span>
                        </div>
                        <div class="icono-chevron" id="chevronIcon">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    
                    <!-- Cuerpo del Acordeon -->
                    <div class="accordion-body" id="accordionBody">
                        <div class="contenido">
                            <p style="margin-bottom: 12px; font-size: 0.85rem; color: var(--texto-suave);">
                                <i class="fas fa-info-circle"></i> Gestión de datos maestros del sistema: categorías, productos, servicios, marcas, ubicaciones y más.
                            </p>
                            
                            <div class="tablas-maestras-grid">
                                
                                <!-- Categorias -->
                                <?php if ($mis_permisos['gestion_categorias'] ?? false): ?>
                                <div class="tabla-maestra-card">
                                    <div class="icono"><i class="fas fa-tags"></i></div>
                                    <div class="info">
                                        <h4>Categorías</h4>
                                        <small>Gestión de categorías</small>
                                    </div>
                                    <a href="admin_categorias.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Servicios -->
                                <?php if ($mis_permisos['gestion_servicios'] ?? false): ?>
                                <div class="tabla-maestra-card">
                                    <div class="icono"><i class="fas fa-cut"></i></div>
                                    <div class="info">
                                        <h4>Servicios</h4>
                                        <small>Servicios del salón</small>
                                    </div>
                                    <a href="admin_servicios.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Productos -->
                                <?php if ($mis_permisos['gestion_productos'] ?? false): ?>
                                <div class="tabla-maestra-card">
                                    <div class="icono"><i class="fas fa-box"></i></div>
                                    <div class="info">
                                        <h4>Productos</h4>
                                        <small>Inventario y stock</small>
                                    </div>
                                    <a href="admin_productos.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Proveedores -->
                                <?php if ($mis_permisos['gestion_proveedores'] ?? false): ?>
                                <div class="tabla-maestra-card">
                                    <div class="icono"><i class="fas fa-truck"></i></div>
                                    <div class="info">
                                        <h4>Proveedores</h4>
                                        <small>Gestión de proveedores</small>
                                    </div>
                                    <a href="admin_proveedores.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Clientes -->
                                <?php if ($mis_permisos['gestion_clientes'] ?? false): ?>
                                <div class="tabla-maestra-card">
                                    <div class="icono"><i class="fas fa-users"></i></div>
                                    <div class="info">
                                        <h4>Clientes</h4>
                                        <small>Base de clientes</small>
                                    </div>
                                    <a href="admin_clientes.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Marcas -->
                                <?php if ($mis_permisos['gestion_productos'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(46, 160, 67, 0.3); background: rgba(46, 160, 67, 0.03);">
                                    <div class="icono" style="background: rgba(46, 160, 67, 0.15); color: #3fb950;"><i class="fas fa-copyright"></i></div>
                                    <div class="info">
                                        <h4>Marcas</h4>
                                        <small>Gestión de marcas</small>
                                    </div>
                                    <a href="admin_marcas.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Provincias -->
                                <?php if ($mis_permisos['gestion_productos'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(88, 166, 255, 0.3); background: rgba(88, 166, 255, 0.03);">
                                    <div class="icono" style="background: rgba(88, 166, 255, 0.15); color: #58a6ff;"><i class="fas fa-map-marker-alt"></i></div>
                                    <div class="info">
                                        <h4>Provincias</h4>
                                        <small>Gestión de provincias</small>
                                    </div>
                                    <a href="admin_provincias.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Localidades -->
                                <?php if ($mis_permisos['gestion_productos'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(88, 166, 255, 0.3); background: rgba(88, 166, 255, 0.03);">
                                    <div class="icono" style="background: rgba(88, 166, 255, 0.15); color: #58a6ff;"><i class="fas fa-city"></i></div>
                                    <div class="info">
                                        <h4>Localidades</h4>
                                        <small>Gestión de localidades</small>
                                    </div>
                                    <a href="admin_localidades.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Tipos de Documento -->
                                <?php if ($mis_permisos['gestion_productos'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(210, 153, 34, 0.3); background: rgba(210, 153, 34, 0.03);">
                                    <div class="icono" style="background: rgba(210, 153, 34, 0.15); color: #d29922;"><i class="fas fa-id-card"></i></div>
                                    <div class="info">
                                        <h4>Tipos Documento</h4>
                                        <small>Gestión de tipos de documento</small>
                                    </div>
                                    <a href="admin_tipos_documento.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Unidades de Medida -->
                                <?php if ($mis_permisos['gestion_productos'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(210, 153, 34, 0.3); background: rgba(210, 153, 34, 0.03);">
                                    <div class="icono" style="background: rgba(210, 153, 34, 0.15); color: #d29922;"><i class="fas fa-ruler"></i></div>
                                    <div class="info">
                                        <h4>Unidades Medida</h4>
                                        <small>Gestión de unidades de medida</small>
                                    </div>
                                    <a href="admin_unidades_medida.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Categorias de Gastos -->
                                <?php if ($mis_permisos['gestion_configuracion'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(201, 168, 76, 0.3); background: rgba(201, 168, 76, 0.03);">
                                    <div class="icono" style="background: rgba(201, 168, 76, 0.15);"><i class="fas fa-coins"></i></div>
                                    <div class="info">
                                        <h4>Categorías Gastos</h4>
                                        <small>Gestión de gastos</small>
                                    </div>
                                    <a href="admin_categorias_gastos.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Condiciones de IVA -->
                                <?php if ($mis_permisos['gestion_configuracion'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(201, 168, 76, 0.3); background: rgba(201, 168, 76, 0.03);">
                                    <div class="icono" style="background: rgba(201, 168, 76, 0.15);"><i class="fas fa-percent"></i></div>
                                    <div class="info">
                                        <h4>Condiciones IVA</h4>
                                        <small>Gestión de IVA</small>
                                    </div>
                                    <a href="admin_condicion_iva.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Estados de Cita -->
                                <?php if ($mis_permisos['gestion_configuracion'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(201, 168, 76, 0.3); background: rgba(201, 168, 76, 0.03);">
                                    <div class="icono" style="background: rgba(201, 168, 76, 0.15);"><i class="fas fa-tasks"></i></div>
                                    <div class="info">
                                        <h4>Estados Cita</h4>
                                        <small>Gestión de estados</small>
                                    </div>
                                    <a href="admin_estados_cita.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Metodos de Pago -->
                                <?php if ($mis_permisos['gestion_configuracion'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(201, 168, 76, 0.3); background: rgba(201, 168, 76, 0.03);">
                                    <div class="icono" style="background: rgba(201, 168, 76, 0.15);"><i class="fas fa-credit-card"></i></div>
                                    <div class="info">
                                        <h4>Métodos Pago</h4>
                                        <small>Gestión de pagos</small>
                                    </div>
                                    <a href="admin_metodo_pago.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Promociones -->
                                <?php if ($mis_permisos['gestion_servicios'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(255, 123, 114, 0.3); background: rgba(255, 123, 114, 0.03);">
                                    <div class="icono" style="background: rgba(255, 123, 114, 0.15); color: #ff7b72;"><i class="fas fa-tags"></i></div>
                                    <div class="info">
                                        <h4>Promociones</h4>
                                        <small>Gestión de promociones</small>
                                    </div>
                                    <a href="admin_promociones.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>

                                <!-- Auditorias -->
                                <?php if ($mis_permisos['gestion_configuracion'] ?? false): ?>
                                <div class="tabla-maestra-card" style="border-color: rgba(163, 113, 247, 0.3); background: rgba(163, 113, 247, 0.03);">
                                    <div class="icono" style="background: rgba(163, 113, 247, 0.15); color: #a371f7;"><i class="fas fa-history"></i></div>
                                    <div class="info">
                                        <h4>Auditorías</h4>
                                        <small>Historial de cambios</small>
                                    </div>
                                    <a href="admin_auditorias.php" class="btn-sm"><i class="fas fa-arrow-right"></i></a>
                                </div>
                                <?php endif; ?>
                                
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            <?php endif; ?>

        </div> 
        
        <?php if (empty($mis_permisos['acceso_panel_empleado']) && empty($mis_permisos['gestion_usuarios']) && empty($mis_permisos['acceso_panel_admin'])): ?>
        <div style="text-align: center; padding: 40px; color: var(--texto-suave);">
            <i class="fas fa-info-circle" style="font-size: 2rem; display: block; margin-bottom: 12px;"></i>
            <p>Pronto más funciones disponibles para tu cuenta</p>
        </div>
        <?php endif; ?>
        
    </div>
    <?php endif; ?>

</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — <?= htmlspecialchars($usuario) ?> • <?= ucfirst($rol) ?></p>
    <p style="font-size:0.75rem;margin-top:4px;color: var(--texto-suave);">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

<script>
function cambiarTab(tab) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);
    
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('activo'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('activo'));
    
    if (tab === 'local') {
        document.querySelector('.tab-btn:first-child').classList.add('activo');
        document.getElementById('tab-local').classList.add('activo');
    } else {
        document.querySelector('.tab-btn:last-child').classList.add('activo');
        document.getElementById('tab-panel').classList.add('activo');
    }
}

function toggleAccordion() {
    const body = document.getElementById('accordionBody');
    const chevron = document.getElementById('chevronIcon');
    body.classList.toggle('abierto');
    chevron.classList.toggle('rotado');
}

function confirmarAccion(accion) {
    return confirm('Para ' + accion + ' necesitás iniciar sesión o registrarte.\n\n¿Querés ir al login ahora?');
}

// ============================================================
//  SISTEMA DE NOTIFICACIONES (CAMPANITA)
// ============================================================

let notificacionesAbiertas = false;

function toggleNotificaciones() {
    const panel = document.getElementById('panelNotificaciones');
    if (!panel) return;
    
    notificacionesAbiertas = !notificacionesAbiertas;
    panel.classList.toggle('abierto', notificacionesAbiertas);
    
    if (notificacionesAbiertas) {
        cargarNotificaciones();
    }
}

function cargarNotificaciones() {
    fetch('ajax/obtener_notificaciones.php')
        .then(r => r.json())
        .then(data => {
            const lista = document.getElementById('listaNotificaciones');
            const badge = document.getElementById('badgeNotificaciones');
            
            if (!lista) return;
            
            if (data.notificaciones && data.notificaciones.length > 0) {
                lista.innerHTML = data.notificaciones.map(n => `
                    <div class="notificacion-item ${n.leido == 0 ? 'no-leida' : ''}" 
                         onclick="marcarLeida(${n.id_notificacion}, '${n.url_accion || ''}')">
                        <div class="icono" style="${getColorPrioridad(n.prioridad)}">
                            <i class="fas ${obtenerIconoNotificacion(n.tipo)}"></i>
                        </div>
                        <div class="contenido">
                            <div class="titulo-notif">${n.titulo || 'Notificación'}</div>
                            <div class="mensaje">${n.mensaje}</div>
                            <div class="fecha">${formatearFecha(n.created_at)}</div>
                        </div>
                    </div>
                `).join('');
                
                // Actualizar badge con color por prioridad
                if (data.no_leidas > 0) {
                    badge.textContent = data.no_leidas > 99 ? '99+' : data.no_leidas;
                    badge.style.display = 'flex';
                    
                    // Cambiar color según prioridad máxima
                    badge.className = 'badge-notificaciones';
                    if (data.prioridad_maxima) {
                        badge.classList.add('badge-' + data.prioridad_maxima);
                    }
                } else {
                    badge.style.display = 'none';
                }
            } else {
                lista.innerHTML = `
                    <div class="sin-notificaciones">
                        <i class="fas fa-inbox"></i>
                        <p>No tenés notificaciones</p>
                    </div>
                `;
                badge.style.display = 'none';
            }
        })
        .catch(err => console.error('Error al cargar notificaciones:', err));
}

// Helper para color según prioridad
function getColorPrioridad(prioridad) {
    const colores = {
        'critica': 'background: rgba(248,81,73,0.2); color: #f85149;',
        'alta': 'background: rgba(210,153,34,0.2); color: #d29922;',
        'media': 'background: rgba(88,166,255,0.2); color: #58a6ff;',
        'baja': 'background: rgba(107,114,128,0.2); color: #8b949e;'
    };
    return colores[prioridad] || colores['media'];
}

function obtenerIconoNotificacion(tipo) {
    const iconos = {
        'nueva_cita': 'fa-calendar-plus',
        'cita_creada': 'fa-calendar-plus',
        'cita_confirmada': 'fa-check-circle',
        'cita_cancelada': 'fa-times-circle',
        'cita_reagendada': 'fa-calendar-day',
        'cita_recordatorio': 'fa-clock',
        'cita_completada': 'fa-check',
        'no_asistio': 'fa-user-slash',
        'stock_bajo': 'fa-exclamation-triangle',
        'stock_agotado': 'fa-times-circle',
        'stock_repuesto': 'fa-check-circle',
        'producto_creado': 'fa-box',
        'producto_editado': 'fa-box-open',
        'producto_eliminado': 'fa-box',
        'producto_roto': 'fa-broken-chain',
        'producto_vencido': 'fa-calendar-times',
        'producto_por_vencer': 'fa-clock',
        'venta_realizada': 'fa-shopping-cart',
        'venta_cancelada': 'fa-ban',
        'pago_registrado': 'fa-money-bill',
        'pago_pendiente': 'fa-clock',
        'diferencia_caja': 'fa-cash-register',
        'cierre_exitoso': 'fa-check-circle',
        'nuevo_cliente': 'fa-user-plus',
        'cliente_inactivo': 'fa-user-clock',
        'cumpleanos': 'fa-birthday-cake',
        'nuevo_usuario': 'fa-user-plus',
        'cambio_rol': 'fa-user-shield',
        'cuenta_bloqueada': 'fa-user-lock',
        'backup_fallido': 'fa-database',
        'backup_completado': 'fa-database',
        'error_sistema': 'fa-exclamation-triangle',
        'promocion': 'fa-tag',
        'feriado': 'fa-calendar-times',
        'cambio_horario': 'fa-clock'
    };
    return iconos[tipo] || 'fa-bell';
}

function formatearFecha(fecha) {
    const date = new Date(fecha);
    const ahora = new Date();
    const diff = Math.floor((ahora - date) / 1000);
    
    if (diff < 60) return 'Hace un momento';
    if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} h`;
    if (diff < 604800) return `Hace ${Math.floor(diff / 86400)} días`;
    return date.toLocaleDateString('es-AR');
}

function marcarLeida(id, link) {
    fetch('ajax/marcar_notificacion_leida.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_notificacion=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            cargarNotificaciones();
            if (link) {
                window.location.href = link;
            }
        }
    })
    .catch(err => console.error('Error:', err));
}

function marcarTodasLeidas() {
    fetch('ajax/marcar_todas_leidas.php', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                cargarNotificaciones();
            }
        })
        .catch(err => console.error('Error:', err));
}

// Cerrar panel al hacer clic afuera
document.addEventListener('click', function(e) {
    const panel = document.getElementById('panelNotificaciones');
    const btn = document.getElementById('btnCampanita');
    if (panel && btn && !panel.contains(e.target) && !btn.contains(e.target)) {
        panel.classList.remove('abierto');
        notificacionesAbiertas = false;
    }
});
</script>

</body>
</html>