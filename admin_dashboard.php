<?php
session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('ver_reportes');

$pdo = conectar();

$total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuario")->fetchColumn();
$total_clientes = $pdo->query("SELECT COUNT(*) FROM cliente")->fetchColumn();
$total_empleados = $pdo->query("SELECT COUNT(*) FROM empleado")->fetchColumn();
$total_servicios = $pdo->query("SELECT COUNT(*) FROM servicios WHERE estado = 'activo'")->fetchColumn();
$total_citas_hoy = $pdo->query("SELECT COUNT(*) FROM cita WHERE DATE(fecha_hora) = CURDATE()")->fetchColumn();
$citas_pendientes = $pdo->query("SELECT COUNT(*) FROM cita WHERE estado_cita = 'pendiente'")->fetchColumn();
$citas_mes = $pdo->query("SELECT COUNT(*) FROM cita WHERE MONTH(fecha_hora) = MONTH(CURDATE()) AND YEAR(fecha_hora) = YEAR(CURDATE())")->fetchColumn();

// Obtener últimos usuarios con fecha de creación aproximada (usando id_usuario como referencia)
$ultimos = $pdo->query("
    SELECT u.id_usuario, u.usuario, u.email, u.rol, u.ultimo_acceso, u.fecha_baja,
           p.nombre, p.apellido
    FROM usuario u
    LEFT JOIN cliente c ON c.id_cliente = u.id_cliente
    LEFT JOIN persona p ON p.id_persona = c.id_persona
    ORDER BY u.id_usuario DESC 
    LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
        }
        .dashboard-card { 
            background: var(--loki-gris); 
            padding: 25px; 
            border-radius: 12px; 
            text-align: center; 
            border-top: 3px solid var(--loki-dorado);
            transition: transform 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .dashboard-number { 
            font-size: 2.5rem; 
            font-weight: bold; 
            color: var(--loki-dorado); 
        }
        .dashboard-label { 
            color: var(--texto-suave); 
            margin-top: 8px; 
        }
        .dashboard-section { 
            background: var(--loki-gris); 
            padding: 20px; 
            border-radius: 12px; 
            margin-top: 20px;
            border: 1px solid var(--borde);
        }
        .dashboard-section h3 {
            margin-bottom: 20px;
            color: var(--loki-dorado);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
        }
        
        /* Estilo mejorado para la tabla de usuarios */
        .tabla-usuarios {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 12px;
        }
        .tabla-usuarios thead tr {
            background: linear-gradient(135deg, rgba(201, 168, 76, 0.15), rgba(201, 168, 76, 0.05));
            border-bottom: 1px solid rgba(201, 168, 76, 0.3);
        }
        .tabla-usuarios th {
            padding: 15px 12px;
            text-align: left;
            color: var(--loki-dorado);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tabla-usuarios td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--borde);
            color: var(--texto);
            font-size: 0.9rem;
        }
        .tabla-usuarios tbody tr {
            transition: background 0.2s ease;
        }
        .tabla-usuarios tbody tr:hover {
            background: rgba(201, 168, 76, 0.05);
        }
        .tabla-usuarios tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Badges de roles */
        .badge-rol-table {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-table-cliente {
            background: rgba(52, 152, 219, 0.15);
            color: #3498db;
            border: 1px solid rgba(52, 152, 219, 0.3);
        }
        .badge-table-empleado {
            background: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }
        .badge-table-administrador {
            background: rgba(201, 168, 76, 0.15);
            color: var(--loki-dorado);
            border: 1px solid rgba(201, 168, 76, 0.3);
        }
        
        /* Fecha de registro */
        .fecha-registro {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
        }
        .fecha-nuevo {
            background: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        /* Avatar o icono de usuario */
        .usuario-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .usuario-avatar {
            width: 32px;
            height: 32px;
            background: rgba(201, 168, 76, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        .usuario-nombre {
            font-weight: 600;
        }
        .usuario-username {
            font-size: 0.7rem;
            color: var(--texto-suave);
        }
        
        /* Scroll responsivo */
        .tabla-responsive {
            overflow-x: auto;
            border-radius: 12px;
        }
        
        /* Header de la sección con contador */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .contador-usuarios {
            background: rgba(201, 168, 76, 0.1);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--loki-dorado);
        }
        
        @media (max-width: 768px) {
            .tabla-usuarios th, 
            .tabla-usuarios td {
                padding: 10px 8px;
                font-size: 0.8rem;
            }
            .usuario-avatar {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
<header class="header-panel">
    <div class="header-contenido">
        <h1 class="logo-header"><a href="home.php">✂ SalonHub</a></h1>
        <nav class="nav-header">
            <a href="home.php" class="btn btn-outline-sm">← Volver</a>
            <a href="cerrar_sesion.php" class="btn btn-outline-sm btn-danger-outline">Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2>📊 Dashboard</h2>
    
    <!-- Tarjetas de estadísticas -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="dashboard-number"><?= $total_usuarios ?></div>
            <div class="dashboard-label">Usuarios</div>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-number"><?= $total_clientes ?></div>
            <div class="dashboard-label">Clientes</div>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-number"><?= $total_empleados ?></div>
            <div class="dashboard-label">Empleados</div>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-number"><?= $total_servicios ?></div>
            <div class="dashboard-label">Servicios Activos</div>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-number"><?= $total_citas_hoy ?></div>
            <div class="dashboard-label">Citas Hoy</div>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-number"><?= $citas_pendientes ?></div>
            <div class="dashboard-label">Citas Pendientes</div>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-number"><?= $citas_mes ?></div>
            <div class="dashboard-label">Citas este Mes</div>
        </div>
    </div>
    
    <!-- Sección de últimos usuarios registrados -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3>
                📋 Últimos Usuarios Registrados
            </h3>
            <span class="contador-usuarios">
                🆕 <?= count($ultimos) ?> usuarios recientes
            </span>
        </div>
        
        <div class="tabla-responsive">
            <table class="tabla-usuarios">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Fecha Registro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimos as $u): 
                        // Determinar si es un usuario nuevo (sin último acceso o con acceso reciente)
                        $es_nuevo = !$u['ultimo_acceso'] || (strtotime($u['ultimo_acceso']) < strtotime('-7 days'));
                        $fecha_registro = $u['ultimo_acceso'] ? date('d/m/Y', strtotime($u['ultimo_acceso'])) : 'Nuevo';
                        
                        // Obtener iniciales para el avatar
                        $iniciales = '';
                        if (!empty($u['nombre']) && !empty($u['apellido'])) {
                            $iniciales = strtoupper(substr($u['nombre'], 0, 1) . substr($u['apellido'], 0, 1));
                        } else {
                            $iniciales = strtoupper(substr($u['usuario'], 0, 2));
                        }
                        
                        // Clase del badge según rol
                        $badge_class = '';
                        switch($u['rol']) {
                            case 'cliente': $badge_class = 'badge-table-cliente'; break;
                            case 'empleado': $badge_class = 'badge-table-empleado'; break;
                            case 'administrador': $badge_class = 'badge-table-administrador'; break;
                            default: $badge_class = 'badge-table-cliente';
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="usuario-info">
                                <div class="usuario-avatar">
                                    <?= htmlspecialchars($iniciales) ?>
                                </div>
                                <div>
                                    <div class="usuario-nombre">
                                        <?= htmlspecialchars($u['nombre'] ?? $u['usuario']) ?>
                                        <?= htmlspecialchars($u['apellido'] ?? '') ?>
                                    </div>
                                    <div class="usuario-username">
                                        @<?= htmlspecialchars($u['usuario']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="mailto:<?= htmlspecialchars($u['email'] ?? '') ?>" style="color: var(--texto-suave); text-decoration: none;">
                                <?= htmlspecialchars($u['email'] ?? '-') ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge-rol-table <?= $badge_class ?>">
                                <?php 
                                $icono_rol = $u['rol'] === 'cliente' ? '👤' : ($u['rol'] === 'empleado' ? '👨‍💼' : '👑');
                                echo $icono_rol . ' ' . ucfirst($u['rol']); 
                                ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($fecha_registro === 'Nuevo'): ?>
                                <span class="fecha-nuevo">
                                    🆕 Nuevo
                                </span>
                            <?php else: ?>
                                <span class="fecha-registro">
                                    📅 <?= $fecha_registro ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($ultimos)): ?>
            <div style="text-align: center; padding: 40px; color: var(--texto-suave);">
                📭 No hay usuarios registrados aún.
            </div>
        <?php endif; ?>
        
        <!-- Botón para ver todos los usuarios -->
        <div style="margin-top: 20px; text-align: right;">
            <a href="abm.php?tab=usuarios" class="btn btn-outline-sm" style="border-color: var(--loki-dorado); color: var(--loki-dorado);">
                Ver todos los usuarios →
            </a>
        </div>
    </div>
</main>
</body>
</html>
