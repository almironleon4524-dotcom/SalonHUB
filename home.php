<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ============================================================
//  SalonHub - Home (Con TABS: Página Principal | Panel)
// ============================================================

session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();

$rol        = $_SESSION['rol']     ?? 'cliente';
$usuario    = $_SESSION['usuario'] ?? 'Usuario';
$bienvenido = isset($_GET['bienvenido']);

// Cargar todos los permisos del usuario actual
$mis_permisos = obtener_todos_permisos((int)$_SESSION['id_usuario']);

// Obtener servicios destacados
$pdo = conectar();
$servicios_destacados = $pdo->query("SELECT id_servicio, nombre, descripcion, duracion, precio FROM servicios WHERE estado = 'activo' LIMIT 6")->fetchAll();

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
    <style>
        /* =================================================== */
        /* ESTILOS DE TABS (PESTAÑAS)                         */
        /* =================================================== */
        
        .tabs-container {
            margin-bottom: 30px;
        }
        
        .tabs-buttons {
            display: flex;
            gap: 0;
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
        
        .tab-btn:hover {
            color: var(--loki-dorado);
            background: rgba(201, 168, 76, 0.05);
        }
        
        .tab-btn.activo {
            color: var(--loki-dorado);
            background: rgba(201, 168, 76, 0.1);
        }
        
        .tab-btn.activo::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--loki-dorado), var(--loki-dorado-claro));
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }
        
        .tab-content.activo {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* =================================================== */
        /* ESTILOS SECCIÓN LOCAL (PÁGINA PRINCIPAL)           */
        /* =================================================== */
        
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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
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
        
        .servicio-card:hover {
            transform: translateY(-5px);
            border-color: var(--loki-dorado);
        }
        
        .servicio-nombre {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--loki-dorado);
            margin-bottom: 8px;
        }
        
        .servicio-desc {
            font-size: 0.85rem;
            color: var(--texto-suave);
            margin-bottom: 12px;
        }
        
        .servicio-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--borde);
        }
        
        .servicio-duracion { font-size: 0.8rem; color: var(--texto-suave); }
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
        .contacto-icono { font-size: 2.5rem; }
        .contacto-item h4 { color: var(--loki-dorado); font-size: 1rem; }
        .contacto-item p, .contacto-item a { color: var(--texto-suave); text-decoration: none; }
        .contacto-item a:hover { color: var(--loki-dorado); }
        
        /* =================================================== */
        /* ESTILOS SECCIÓN PANEL (HERRAMIENTAS)               */
        /* =================================================== */
        
        .panel-header {
            margin-bottom: 30px;
        }
        
        .panel-header h2 {
            font-size: 1.5rem;
            border-bottom: 2px solid var(--borde);
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
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
            top: 0;
            left: 0;
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
            font-size: 1.8rem;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(201, 168, 76, 0.1);
            border-radius: 12px;
            margin-bottom: 12px;
        }
        
        .tarjeta-empleado .tarjeta-icono { background: rgba(15, 92, 69, 0.2); }
        .tarjeta-admin .tarjeta-icono { background: rgba(201, 168, 76, 0.15); }
        
        .tarjeta-panel h3 {
            font-size: 1rem;
            color: var(--texto);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .tarjeta-panel p {
            font-size: 0.8rem;
            color: var(--texto-suave);
            margin-bottom: 16px;
            line-height: 1.4;
        }
        
        .tarjeta-panel .btn {
            font-size: 0.8rem;
            padding: 8px 16px;
            display: inline-block;
            width: fit-content;
        }
        
        .btn-admin {
            background: linear-gradient(135deg, var(--loki-dorado), #b8943a);
            color: var(--loki-negro);
            font-weight: 600;
        }
        
        .btn-empleado { background: var(--loki-verde); color: white; }
        .btn-secundario { background: rgba(255, 255, 255, 0.08); color: var(--texto); border: 1px solid var(--borde); }
        
        .alerta-bienvenida {
            background: linear-gradient(135deg, rgba(201, 168, 76, 0.15), rgba(15, 92, 69, 0.1));
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 28px;
        }
        
        @media (max-width: 768px) {
            .hero-title { font-size: 2rem; }
            .hero-section { padding: 40px 20px; }
            .info-contacto { padding: 30px 20px; }
            .tab-btn { padding: 12px 16px; font-size: 0.85rem; }
            .grilla-tarjetas { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body style="background: var(--loki-negro); min-height: 100vh;">

<header class="header-panel">
    <div class="header-contenido">
        <h1 class="logo-header">
            <a href="home.php" style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.5rem;">✂</span> SalonHub
            </a>
        </h1>
        <nav class="nav-header">
            <span class="usuario-info">
                👤 <?= htmlspecialchars($usuario) ?>
                <span class="badge-rol badge-<?= $rol ?>"><?= ucfirst($rol) ?></span>
            </span>
            <a href="perfil.php" class="btn btn-outline-sm">Mi Perfil</a>
            <a href="cerrar_sesion.php" class="btn btn-outline-sm btn-danger-outline">Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">

    <?php if ($bienvenido): ?>
        <div class="alerta-bienvenida">
            🎉 <strong>Bienvenido/a a SalonHub, <?= htmlspecialchars($usuario) ?>!</strong> El poder de la transformación te espera.
        </div>
    <?php endif; ?>

    <!-- =================================================== -->
    <!-- TABS: LOCAL | PANEL                                 -->
    <!-- =================================================== -->
    
    <div class="tabs-container">
        <div class="tabs-buttons">
            <button class="tab-btn <?= $tab_activa === 'local' ? 'activo' : '' ?>" onclick="cambiarTab('local')">
                🏠 <span>Página Principal</span>
            </button>
            <button class="tab-btn <?= $tab_activa === 'panel' ? 'activo' : '' ?>" onclick="cambiarTab('panel')">
                🛠️ <span>Panel de Herramientas</span>
            </button>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- TAB 1: PÁGINA PRINCIPAL (LOCAL)                     -->
    <!-- =================================================== -->
    
    <div id="tab-local" class="tab-content <?= $tab_activa === 'local' ? 'activo' : '' ?>">
        
        <!-- Hero / Bienvenida -->
        <div class="hero-section">
            <div class="hero-title">SalonHub</div>
            <div class="hero-subtitle">El poder de la transformación. Cortes, color, manicuría y más en un solo lugar.</div>
            <?php if ($mis_permisos['reservar_citas'] ?? false): ?>
                <a href="nueva_cita.php" class="btn-hero">📅 Reservar tu turno ahora</a>
            <?php endif; ?>
        </div>

        <!-- Servicios Destacados -->
        <h2 style="margin-bottom: 20px;">✨ Servicios Destacados</h2>
        <div class="servicios-grid">
            <?php foreach ($servicios_destacados as $servicio): ?>
            <div class="servicio-card">
                <div class="servicio-nombre"><?= htmlspecialchars($servicio['nombre']) ?></div>
                <div class="servicio-desc"><?= htmlspecialchars($servicio['descripcion'] ?? 'Descubre este servicio exclusivo en SalonHub') ?></div>
                <div class="servicio-info">
                    <span class="servicio-duracion">⏱️ <?= htmlspecialchars($servicio['duracion']) ?></span>
                    <span class="servicio-precio">$<?= number_format($servicio['precio'], 0, ',', '.') ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Información de Contacto y Ubicación -->
        <div class="info-contacto">
            <div class="contacto-item">
                <div class="contacto-icono">📍</div>
                <h4>Ubicación</h4>
                <p>Av. 25 de Mayo 1355<br>Formosa Capital</p>
            </div>
            <div class="contacto-item">
                <div class="contacto-icono">📞</div>
                <h4>Teléfono</h4>
                <p><a href="tel:+54 9 370 486-3574">+54 9 3704 486-3574</a></p>
            </div>
            <div class="contacto-item">
                <div class="contacto-icono">✉️</div>
                <h4>Email</h4>
                <p><a href="mailto:almironleon4524@gmail.com">contacto@salonhub.com</a></p>
            </div>
            <div class="contacto-item">
                <div class="contacto-icono">🕐</div>
                <h4>Horarios</h4>
                <p>Lun a Vie: 9:00 - 20:00<br>Sáb: 10:00 - 18:00</p>
            </div>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- TAB 2: PANEL DE HERRAMIENTAS                         -->
    <!-- =================================================== -->
    
    <div id="tab-panel" class="tab-content <?= $tab_activa === 'panel' ? 'activo' : '' ?>">
        
        <div class="panel-header">
            <h2>🛠️ Panel de Herramientas</h2>
        </div>
        
        <div class="grilla-tarjetas">

            <!-- Tarjetas para TODOS los usuarios -->
            <div class="tarjeta-panel">
                <div class="tarjeta-icono">👤</div>
                <h3>Mi Perfil</h3>
                <p>Ver y editar tus datos personales, cambiar contraseña.</p>
                <a href="perfil.php" class="btn btn-primario">Ir a Mi Perfil</a>
            </div>

            <div class="tarjeta-panel">
                <div class="tarjeta-icono">📅</div>
                <h3>Mis Citas</h3>
                <p>Revisá tus citas programadas y su estado actual.</p>
                <a href="mis_citas.php" class="btn btn-primario">Ver mis citas</a>
            </div>

            <div class="tarjeta-panel">
                <div class="tarjeta-icono">➕</div>
                <h3>Reservar Turno</h3>
                <p>Agendá un nuevo turno en el salón fácilmente.</p>
                <a href="nueva_cita.php" class="btn btn-secundario">Reservar</a>
            </div>

            <!-- Tarjeta para EMPLEADOS -->
            <?php if ($mis_permisos['acceso_panel_empleado'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-empleado">
                <div class="tarjeta-icono">🗓️</div>
                <h3>Mis Citas</h3>
                <p>Ver y gestionar las citas que te asignaron.</p>
                <a href="panel_empleado.php" class="btn btn-empleado">Ver Mis Citas</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta para ADMIN: Gestión de Citas -->
            <?php if ($mis_permisos['gestion_citas'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono">📋</div>
                <h3>Gestionar Citas</h3>
                <p>Ver todas las citas, asignar empleados y estados.</p>
                <a href="panel_admin_citas.php" class="btn btn-admin">Admin Citas</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta ÚNICA para ADMIN: GESTIONAR USUARIOS (incluye clientes, empleados, permisos) -->
            <?php if ($mis_permisos['gestion_usuarios'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono">👥</div>
                <h3>Gestionar Usuarios</h3>
                <p>Administrar clientes, empleados y permisos especiales.</p>
                <a href="abm.php" class="btn btn-admin">ABM Usuarios</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta ÚNICA para ADMIN: DASHBOARD (incluye reportes y métricas) -->
            <?php if (($mis_permisos['acceso_panel_admin'] ?? false) || ($mis_permisos['ver_reportes'] ?? false)): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono">📊</div>
                <h3>Dashboard</h3>
                <p>Métricas, estadísticas y reportes del sistema.</p>
                <a href="admin_dashboard.php" class="btn btn-admin">Ver Dashboard</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta para ADMIN: Gestionar Servicios -->
            <?php if ($mis_permisos['gestion_servicios'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono">💇</div>
                <h3>Servicios</h3>
                <p>Crear, editar y eliminar servicios del salón.</p>
                <a href="admin_servicios.php" class="btn btn-admin">Gestionar Servicios</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta para ADMIN: Gestionar Categorías -->
            <?php if ($mis_permisos['gestion_categorias'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono">📁</div>
                <h3>Categorías</h3>
                <p>Administrar categorías de servicios.</p>
                <a href="admin_categorias.php" class="btn btn-admin">Gestionar Categorías</a>
            </div>
            <?php endif; ?>

            <!-- Tarjeta para ADMIN: Configuración -->
            <?php if ($mis_permisos['gestion_configuracion'] ?? false): ?>
            <div class="tarjeta-panel tarjeta-admin">
                <div class="tarjeta-icono">⚙️</div>
                <h3>Configuración</h3>
                <p>Ajustes generales del sistema.</p>
                <a href="admin_configuracion.php" class="btn btn-admin">Configuración</a>
            </div>
            <?php endif; ?>

        </div>
        
        <!-- Mensaje si no hay permisos adicionales -->
        <?php if (empty($mis_permisos['acceso_panel_empleado']) && empty($mis_permisos['gestion_usuarios']) && empty($mis_permisos['acceso_panel_admin'])): ?>
        <div style="text-align: center; padding: 40px; color: var(--texto-suave);">
            <p>Pronto más funciones disponibles para tu cuenta</p>
        </div>
        <?php endif; ?>
        
    </div>

</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — <?= htmlspecialchars($usuario) ?> • <?= ucfirst($rol) ?></p>
    <p style="font-size:0.75rem;margin-top:4px;">El poder de la transformación</p>
</footer>

<script>
function cambiarTab(tab) {
    // Actualizar URL sin recargar
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);
    
    // Cambiar clases de los botones
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('activo');
    });
    
    // Cambiar clases de los contenidos
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('activo');
    });
    
    // Activar el tab seleccionado
    if (tab === 'local') {
        document.querySelector('.tab-btn:first-child').classList.add('activo');
        document.getElementById('tab-local').classList.add('activo');
    } else {
        document.querySelector('.tab-btn:last-child').classList.add('activo');
        document.getElementById('tab-panel').classList.add('activo');
    }
}
</script>

</body>
</html>
