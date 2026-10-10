<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Panel de Empleado

session_start();
require_once __DIR__ . '/permisos_helper.php';
require_once __DIR__ . '/notificaciones_helper.php';

requerir_login();
requerir_permiso('acceso_panel_empleado');

$pdo = conectar();
$id_empleado = $_SESSION['id_empleado'] ?? 0;
$mensaje = '';
$error = '';

// Si el usuario no tiene id_empleado, buscarlo
if (!$id_empleado) {
    $stmt = $pdo->prepare("SELECT id_empleado FROM empleado WHERE id_usuario = :id_usuario");
    $stmt->execute([':id_usuario' => $_SESSION['id_usuario']]);
    $emp = $stmt->fetch();
    if ($emp) {
        $id_empleado = $emp['id_empleado'];
        $_SESSION['id_empleado'] = $id_empleado;
    } else {
        $error = "No se encontró información de empleado para tu cuenta.";
    }
}

// Cambiar estado de una cita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    $id_cita = (int)$_POST['id_cita'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    // Verificar que la cita pertenece al empleado
    $stmt = $pdo->prepare("SELECT id_cita, id_cliente, estado_cita FROM cita WHERE id_cita = :id_cita AND id_empleado = :id_empleado");
    $stmt->execute([':id_cita' => $id_cita, ':id_empleado' => $id_empleado]);
    $cita_actual = $stmt->fetch();
    
    if ($cita_actual) {
        $estado_anterior = $cita_actual['estado_cita'];
        
        $stmt = $pdo->prepare("UPDATE cita SET estado_cita = :estado WHERE id_cita = :id");
        if ($stmt->execute([':estado' => $nuevo_estado, ':id' => $id_cita])) {
            $mensaje = "Estado de la cita actualizado correctamente.";
            
            // Notificar al cliente si cambió a confirmada o completada
            if ($estado_anterior !== $nuevo_estado && in_array($nuevo_estado, ['confirmada', 'completada'])) {
                $stmt_cli = $pdo->prepare("
                    SELECT u.id_usuario 
                    FROM cliente c 
                    LEFT JOIN usuario u ON u.id_cliente = c.id_cliente 
                    WHERE c.id_cliente = ?
                ");
                $stmt_cli->execute([$cita_actual['id_cliente']]);
                $id_usuario_cliente = $stmt_cli->fetchColumn();
                
                $stmt_fecha = $pdo->prepare("SELECT fecha_hora FROM cita WHERE id_cita = ?");
                $stmt_fecha->execute([$id_cita]);
                $fecha_formateada = date('d/m/Y H:i', strtotime($stmt_fecha->fetchColumn()));
                
                if ($id_usuario_cliente) {
                    if ($nuevo_estado === 'confirmada') {
                        notificar_cliente(
                            $id_usuario_cliente,
                            'cita_confirmada',
                            'Cita Confirmada',
                            "Tu cita del {$fecha_formateada} fue confirmada.",
                            [
                                'prioridad' => 'media',
                                'modulo' => 'citas',
                                'url_accion' => "mis_citas.php",
                                'id_referencia' => $id_cita,
                                'tabla_referencia' => 'cita'
                            ]
                        );
                    } elseif ($nuevo_estado === 'completada') {
                        notificar_cliente(
                            $id_usuario_cliente,
                            'cita_completada',
                            'Cita Completada',
                            "¡Gracias por tu visita! Tu cita del {$fecha_formateada} fue completada.",
                            [
                                'prioridad' => 'baja',
                                'modulo' => 'citas',
                                'url_accion' => "mis_citas.php",
                                'id_referencia' => $id_cita,
                                'tabla_referencia' => 'cita'
                            ]
                        );
                    }
                }
            }
        } else {
            $error = "Error al actualizar el estado.";
        }
    } else {
        $error = "No tienes permiso para modificar esta cita.";
    }
}

// Filtros
$fecha_filtro = $_GET['fecha'] ?? '';
$estado_filtro = $_GET['estado'] ?? 'todas';

// Consulta de citas del empleado
$sql = "
    SELECT 
        c.id_cita,
        c.fecha_hora,
        c.estado_cita,
        c.observaciones,
        u.id_usuario,
        u.usuario,
        u.email,
        p.nombre,
        p.apellido,
        p.telefono,
        GROUP_CONCAT(DISTINCT s.nombre SEPARATOR ', ') as servicios,
        GROUP_CONCAT(DISTINCT s.duracion SEPARATOR ', ') as duraciones
    FROM cita c
    LEFT JOIN cliente cl ON cl.id_cliente = c.id_cliente
    LEFT JOIN usuario u ON u.id_cliente = cl.id_cliente
    LEFT JOIN persona p ON p.id_persona = cl.id_persona
    LEFT JOIN detalle_cita dc ON dc.id_cita = c.id_cita
    LEFT JOIN servicios s ON s.id_servicio = dc.id_servicio
    WHERE c.id_empleado = :id_empleado
";

$params = [':id_empleado' => $id_empleado];

// Filtrar por fecha solo si se especifica
if (!empty($fecha_filtro)) {
    $sql .= " AND DATE(c.fecha_hora) = :fecha";
    $params[':fecha'] = $fecha_filtro;
}

// Filtro de estado
if ($estado_filtro !== 'todas') {
    $sql .= " AND c.estado_cita = :estado";
    $params[':estado'] = $estado_filtro;
}

$sql .= " GROUP BY c.id_cita ORDER BY c.fecha_hora DESC";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$citas = $stmt->fetchAll();

// Estadísticas generales
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado_cita = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado_cita = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
        SUM(CASE WHEN estado_cita = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN estado_cita = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
        SUM(CASE WHEN DATE(fecha_hora) = CURDATE() THEN 1 ELSE 0 END) as hoy
    FROM cita
    WHERE id_empleado = :id_empleado
");
$stats->execute([':id_empleado' => $id_empleado]);
$stats_gral = $stats->fetch();

// Obtener nombre del empleado
$stmt = $pdo->prepare("
    SELECT p.nombre, p.apellido, e.especialidad
    FROM empleado e
    JOIN persona p ON p.id_persona = e.id_persona
    WHERE e.id_empleado = :id_empleado
");
$stmt->execute([':id_empleado' => $id_empleado]);
$empleado = $stmt->fetch();

if (!$empleado) {
    $error = "No se encontró información del empleado.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Empleado - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: #0d1117;
            color: #e6edf3;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        
        .header-panel {
            background: #161b22;
            border-bottom: 1px solid #30363d;
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
            color: #c9a84c;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo-header a i {
            font-size: 1.8rem;
        }
        
        .nav-header {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .usuario-info {
            color: #8b949e;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .usuario-info i {
            font-size: 1.1rem;
            color: #c9a84c;
        }
        
        .badge-rol {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-empleado {
            background: #238636;
            color: white;
        }
        
        .btn-outline-sm {
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.3s;
            background: transparent;
            border: 1px solid #30363d;
            color: #8b949e;
        }
        
        .btn-outline-sm:hover {
            border-color: #c9a84c;
            color: #c9a84c;
        }
        
        .btn-danger-outline:hover {
            border-color: #e74c3c;
            color: #e74c3c;
        }
        
        .contenedor-panel {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .contenedor-panel h2 {
            color: #e6edf3;
            font-size: 1.8rem;
            margin-bottom: 8px;
            border-bottom: 2px solid #30363d;
            padding-bottom: 12px;
        }
        
        .contenedor-panel h2 i {
            color: #c9a84c;
            margin-right: 10px;
        }
        
        .header-empleado {
            background: rgba(46, 160, 67, 0.08);
            border: 1px solid rgba(46, 160, 67, 0.2);
            padding: 20px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 4px solid #238636;
        }
        
        .header-empleado h2 {
            color: #3fb950;
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 6px;
            font-size: 1.4rem;
        }
        
        .header-empleado h2 i {
            color: #3fb950;
        }
        
        .header-empleado .info {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 20px;
            margin-top: 4px;
        }
        
        .header-empleado .info .nombre {
            color: #e6edf3;
            font-weight: 600;
        }
        
        .header-empleado .info .especialidad {
            color: #8b949e;
            font-size: 0.9rem;
        }
        
        .header-empleado .info .especialidad i {
            color: #3fb950;
            margin-right: 4px;
        }
        
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
        
        .alerta-error {
            background: rgba(248, 81, 73, 0.15);
            border: 1px solid #da3633;
            color: #f85149;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: #161b22;
            border-radius: 10px;
            padding: 14px 16px;
            text-align: center;
            border: 1px solid #30363d;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }
        
        .stat-card .numero {
            font-size: 1.6rem;
            font-weight: 700;
        }
        
        .stat-card .etiqueta {
            font-size: 0.75rem;
            color: #8b949e;
            margin-top: 2px;
        }
        
        .stat-card .icono {
            font-size: 1.2rem;
            margin-bottom: 2px;
        }
        
        .stat-card.total .numero { color: #58a6ff; }
        .stat-card.total { border-top: 3px solid #58a6ff; }
        .stat-card.pendiente .numero { color: #d29922; }
        .stat-card.pendiente { border-top: 3px solid #d29922; }
        .stat-card.confirmada .numero { color: #3fb950; }
        .stat-card.confirmada { border-top: 3px solid #3fb950; }
        .stat-card.completada .numero { color: #58a6ff; }
        .stat-card.completada { border-top: 3px solid #58a6ff; }
        .stat-card.hoy .numero { color: #c9a84c; }
        .stat-card.hoy { border-top: 3px solid #c9a84c; }
        
        .filtros-container {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }
        
        .filtro-grupo {
            flex: 1;
            min-width: 150px;
        }
        
        .filtro-grupo label {
            display: block;
            margin-bottom: 4px;
            font-size: 0.75rem;
            color: #8b949e;
            font-weight: 500;
        }
        
        .filtro-grupo label i {
            color: #c9a84c;
            margin-right: 4px;
        }
        
        .filtro-grupo select,
        .filtro-grupo input {
            width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #30363d;
            background: #0d1117;
            color: #e6edf3;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .filtro-grupo select:focus,
        .filtro-grupo input:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.15);
        }
        
        .btn-filtro {
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-filtro-primary {
            background: #238636;
            color: white;
        }
        
        .btn-filtro-primary:hover {
            background: #2ea043;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(35, 134, 54, 0.3);
        }
        
        .btn-filtro-secondary {
            background: #30363d;
            color: #8b949e;
        }
        
        .btn-filtro-secondary:hover {
            background: #484f58;
            color: #e6edf3;
        }
        
        .filtros-acciones {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .contador-resultados {
            margin-bottom: 16px;
            color: #8b949e;
            font-size: 0.9rem;
        }
        
        .contador-resultados i {
            color: #3fb950;
            margin-right: 6px;
        }
        
        .tabla-responsive {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #30363d;
        }
        
        .tabla-citas {
            width: 100%;
            border-collapse: collapse;
            background: #161b22;
        }
        
        .tabla-citas th {
            padding: 12px 16px;
            text-align: left;
            background: rgba(46, 160, 67, 0.05);
            color: #3fb950;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #30363d;
        }
        
        .tabla-citas td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            color: #e6edf3;
            font-size: 0.9rem;
            vertical-align: middle;
        }
        
        .tabla-citas tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .estado-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .estado-pendiente {
            background: rgba(210, 153, 34, 0.15);
            color: #d29922;
            border: 1px solid rgba(210, 153, 34, 0.2);
        }
        
        .estado-confirmada {
            background: rgba(46, 160, 67, 0.15);
            color: #3fb950;
            border: 1px solid rgba(46, 160, 67, 0.2);
        }
        
        .estado-completada {
            background: rgba(88, 166, 255, 0.15);
            color: #58a6ff;
            border: 1px solid rgba(88, 166, 255, 0.2);
        }
        
        .estado-cancelada {
            background: rgba(248, 81, 73, 0.15);
            color: #f85149;
            border: 1px solid rgba(248, 81, 73, 0.2);
        }
        
        .btn-accion {
            padding: 4px 10px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.7rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: white;
            font-weight: 500;
        }
        
        .btn-accion:hover {
            transform: scale(1.05);
        }
        
        .btn-ver { background: #58a6ff; }
        .btn-ver:hover { background: #3b8fd4; }
        
        .btn-estado { background: #238636; }
        .btn-estado:hover { background: #2ea043; }
        
        .fecha-cita {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .fecha-cita .fecha {
            font-size: 0.8rem;
            color: #c9a84c;
            font-weight: 600;
        }
        .fecha-cita .hora {
            font-size: 0.85rem;
            color: #e6edf3;
        }
        
        .empty-message {
            text-align: center;
            padding: 40px;
            color: #8b949e;
        }
        
        .empty-message i {
            font-size: 3rem;
            display: block;
            margin-bottom: 12px;
            color: #30363d;
        }
        
        .cita-card {
            background: #161b22;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid #30363d;
            display: none;
        }
        
        .cita-card .cita-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .cita-card .cita-header .hora {
            font-weight: 700;
            color: #c9a84c;
        }
        
        .cita-card .cita-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 16px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
        }
        
        .cita-card .cita-body .campo {
            color: #8b949e;
            font-size: 0.85rem;
        }
        
        .cita-card .cita-body .campo strong {
            color: #e6edf3;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-activo {
            display: flex;
        }
        
        .modal-content {
            background: #161b22;
            border-radius: 16px;
            padding: 28px;
            max-width: 500px;
            width: 95%;
            border: 1px solid #30363d;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-content h3 {
            color: #3fb950;
            margin-bottom: 16px;
            font-size: 1.1rem;
        }
        
        .modal-content h3 i {
            margin-right: 8px;
        }
        
        .modal-content .campo-grupo {
            margin-bottom: 16px;
        }
        
        .modal-content .campo-grupo label {
            display: block;
            color: #8b949e;
            font-size: 0.85rem;
            margin-bottom: 4px;
        }
        
        .modal-content .campo-grupo select {
            width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #30363d;
            background: #0d1117;
            color: #e6edf3;
            font-size: 0.9rem;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn-modal-secondary {
            background: #30363d;
            color: #8b949e;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-modal-secondary:hover {
            background: #484f58;
            color: #e6edf3;
        }
        
        .btn-modal-primary {
            background: #238636;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-modal-primary:hover {
            background: #2ea043;
        }
        
        .detalle-item {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }
        
        .detalle-item:last-child {
            border-bottom: none;
        }
        
        .detalle-label {
            color: #8b949e;
            width: 100px;
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        
        .detalle-value {
            color: #e6edf3;
            flex: 1;
        }
        
        .footer-panel {
            text-align: center;
            padding: 30px 20px 20px;
            border-top: 1px solid #30363d;
            margin-top: 40px;
            color: #8b949e;
        }
        
        .footer-panel span {
            color: #c9a84c;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .tabla-citas { display: none; }
            .cita-card { display: block; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filtros-container { flex-direction: column; }
            .filtro-grupo { min-width: auto; width: 100%; }
            .filtros-acciones { width: 100%; }
            .filtros-acciones .btn-filtro { flex: 1; justify-content: center; }
            .header-contenido { flex-wrap: wrap; gap: 10px; }
            .nav-header { flex-wrap: wrap; gap: 8px; }
            .contenedor-panel h2 { font-size: 1.4rem; }
            .header-empleado .info { flex-direction: column; gap: 4px; }
            .cita-card .cita-body { grid-template-columns: 1fr; }
            .modal-content { padding: 20px; margin: 10px; }
            .detalle-item { flex-direction: column; gap: 2px; padding: 8px 0; }
            .detalle-label { width: auto; font-size: 0.75rem; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
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
            <span class="usuario-info">
                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['usuario']) ?>
                <span class="badge-rol badge-empleado">Empleado</span>
            </span>
            <a href="home.php" class="btn-outline-sm"><i class="fas fa-arrow-left"></i> Panel</a>
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <div class="header-empleado">
        <h2><i class="fas fa-user-tie"></i> Panel de Empleado</h2>
        <div class="info">
            <span class="nombre"><i class="fas fa-user"></i> <?= htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido']) ?></span>
            <span class="especialidad"><i class="fas fa-tools"></i> Especialidad: <?= htmlspecialchars($empleado['especialidad'] ?? 'General') ?></span>
        </div>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card hoy">
            <div class="icono"><i class="fas fa-calendar-day"></i></div>
            <div class="numero"><?= $stats_gral['hoy'] ?? 0 ?></div>
            <div class="etiqueta">Citas Hoy</div>
        </div>
        <div class="stat-card pendiente">
            <div class="icono"><i class="fas fa-clock"></i></div>
            <div class="numero"><?= $stats_gral['pendientes'] ?? 0 ?></div>
            <div class="etiqueta">Pendientes</div>
        </div>
        <div class="stat-card confirmada">
            <div class="icono"><i class="fas fa-check-circle"></i></div>
            <div class="numero"><?= $stats_gral['confirmadas'] ?? 0 ?></div>
            <div class="etiqueta">Confirmadas</div>
        </div>
        <div class="stat-card completada">
            <div class="icono"><i class="fas fa-check-double"></i></div>
            <div class="numero"><?= $stats_gral['completadas'] ?? 0 ?></div>
            <div class="etiqueta">Completadas</div>
        </div>
    </div>
    
    <form method="GET" class="filtros-container">
        <div class="filtro-grupo">
            <label><i class="fas fa-calendar-day"></i> Fecha (opcional)</label>
            <input type="date" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>">
        </div>
        <div class="filtro-grupo">
            <label><i class="fas fa-filter"></i> Estado</label>
            <select name="estado">
                <option value="todas" <?= $estado_filtro === 'todas' ? 'selected' : '' ?>>Todas</option>
                <option value="pendiente" <?= $estado_filtro === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="confirmada" <?= $estado_filtro === 'confirmada' ? 'selected' : '' ?>>Confirmada</option>
                <option value="completada" <?= $estado_filtro === 'completada' ? 'selected' : '' ?>>Completada</option>
                <option value="cancelada" <?= $estado_filtro === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
            </select>
        </div>
        <div class="filtros-acciones">
            <button type="submit" class="btn-filtro btn-filtro-primary"><i class="fas fa-search"></i> Filtrar</button>
            <a href="panel_empleado.php" class="btn-filtro btn-filtro-secondary"><i class="fas fa-undo"></i> Limpiar</a>
        </div>
    </form>
    
    <div class="contador-resultados">
        <i class="fas fa-list"></i>
        <?= count($citas) ?> citas encontradas
        <?php if (!empty($fecha_filtro)): ?>
            <span style="color: #3fb950;">(fecha: <?= date('d/m/Y', strtotime($fecha_filtro)) ?>)</span>
        <?php endif; ?>
    </div>
    
    <div class="tabla-responsive">
        <table class="tabla-citas">
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Cliente</th>
                    <th>Servicios</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($citas)): ?>
                    <tr>
                        <td colspan="5" class="empty-message">
                            <i class="fas fa-calendar-plus"></i>
                            No hay citas con los filtros seleccionados
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($citas as $cita): 
                        $fecha = date('d/m/Y', strtotime($cita['fecha_hora']));
                        $hora = date('H:i', strtotime($cita['fecha_hora']));
                        $estado_class = match($cita['estado_cita']) {
                            'pendiente' => 'estado-pendiente',
                            'confirmada' => 'estado-confirmada',
                            'completada' => 'estado-completada',
                            'cancelada' => 'estado-cancelada',
                            default => ''
                        };
                    ?>
                        <tr>
                            <td>
                                <div class="fecha-cita">
                                    <span class="fecha"><i class="fas fa-calendar-day"></i> <?= $fecha ?></span>
                                    <span class="hora"><i class="fas fa-clock"></i> <?= $hora ?></span>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars($cita['nombre'] . ' ' . $cita['apellido']) ?>
                                <br><small style="color: #8b949e;">@<?= htmlspecialchars($cita['usuario']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($cita['servicios'] ?? 'Sin servicio') ?></td>
                            <td><span class="estado-badge <?= $estado_class ?>"><?= ucfirst($cita['estado_cita']) ?></span></td>
                            <td>
                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                    <button class="btn-accion btn-ver" onclick="verDetalle(
                                        '<?= addslashes($cita['nombre'] . ' ' . $cita['apellido']) ?>',
                                        '<?= $fecha . ' ' . $hora ?>',
                                        '<?= addslashes($cita['servicios'] ?? 'Sin servicio') ?>',
                                        '<?= $cita['estado_cita'] ?>',
                                        '<?= addslashes($cita['observaciones'] ?? 'Sin observaciones') ?>',
                                        '<?= htmlspecialchars($cita['email']) ?>',
                                        '<?= htmlspecialchars($cita['telefono'] ?? 'No disponible') ?>'
                                    )">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($cita['estado_cita'] !== 'cancelada' && $cita['estado_cita'] !== 'completada'): ?>
                                        <button class="btn-accion btn-estado" onclick="cambiarEstado(<?= $cita['id_cita'] ?>, '<?= $cita['estado_cita'] ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php foreach ($citas as $cita): 
        $fecha = date('d/m/Y', strtotime($cita['fecha_hora']));
        $hora = date('H:i', strtotime($cita['fecha_hora']));
        $estado_class = match($cita['estado_cita']) {
            'pendiente' => 'estado-pendiente',
            'confirmada' => 'estado-confirmada',
            'completada' => 'estado-completada',
            'cancelada' => 'estado-cancelada',
            default => ''
        };
    ?>
        <div class="cita-card">
            <div class="cita-header">
                <span class="hora"><i class="fas fa-calendar-day"></i> <?= $fecha ?> - <?= $hora ?></span>
                <span class="estado-badge <?= $estado_class ?>"><?= ucfirst($cita['estado_cita']) ?></span>
            </div>
            <div style="margin-top: 4px;">
                <strong><?= htmlspecialchars($cita['nombre'] . ' ' . $cita['apellido']) ?></strong>
                <br><small style="color: #8b949e;">@<?= htmlspecialchars($cita['usuario']) ?></small>
            </div>
            <div class="cita-body">
                <div class="campo"><strong>Servicios:</strong> <?= htmlspecialchars($cita['servicios'] ?? 'Sin servicio') ?></div>
            </div>
            <div style="margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
                <button class="btn-accion btn-ver" onclick="verDetalle(
                    '<?= addslashes($cita['nombre'] . ' ' . $cita['apellido']) ?>',
                    '<?= $fecha . ' ' . $hora ?>',
                    '<?= addslashes($cita['servicios'] ?? 'Sin servicio') ?>',
                    '<?= $cita['estado_cita'] ?>',
                    '<?= addslashes($cita['observaciones'] ?? 'Sin observaciones') ?>',
                    '<?= htmlspecialchars($cita['email']) ?>',
                    '<?= htmlspecialchars($cita['telefono'] ?? 'No disponible') ?>'
                )">
                    <i class="fas fa-eye"></i> Ver
                </button>
                <?php if ($cita['estado_cita'] !== 'cancelada' && $cita['estado_cita'] !== 'completada'): ?>
                    <button class="btn-accion btn-estado" onclick="cambiarEstado(<?= $cita['id_cita'] ?>, '<?= $cita['estado_cita'] ?>')">
                        <i class="fas fa-edit"></i> Estado
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Panel de Empleado</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

<!-- MODALES -->
<div id="modalDetalle" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-info-circle"></i> Detalle de Cita</h3>
        <div id="detalleContent"></div>
        <div class="modal-actions">
            <button class="btn-modal-secondary" onclick="cerrarModal('modalDetalle')"><i class="fas fa-times"></i> Cerrar</button>
        </div>
    </div>
</div>

<div id="modalEstado" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-edit"></i> Cambiar Estado</h3>
        <form method="POST">
            <input type="hidden" name="id_cita" id="estado_id_cita">
            <div class="campo-grupo">
                <label>Nuevo estado</label>
                <select name="nuevo_estado" id="nuevo_estado">
                    <option value="pendiente">Pendiente</option>
                    <option value="confirmada">Confirmada</option>
                    <option value="completada">Completada</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-modal-secondary" onclick="cerrarModal('modalEstado')"><i class="fas fa-times"></i> Cancelar</button>
                <button type="submit" name="cambiar_estado" class="btn-modal-primary"><i class="fas fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function verDetalle(cliente, fechaHora, servicios, estado, observaciones, email, telefono) {
    const estadoClass = {
        'pendiente': 'estado-pendiente',
        'confirmada': 'estado-confirmada',
        'completada': 'estado-completada',
        'cancelada': 'estado-cancelada'
    };
    
    const estadoIcon = {
        'pendiente': 'fa-clock',
        'confirmada': 'fa-check-circle',
        'completada': 'fa-check-double',
        'cancelada': 'fa-times-circle'
    };
    
    const html = `
        <div class="detalle-item">
            <span class="detalle-label"><i class="fas fa-user"></i> Cliente</span>
            <span class="detalle-value">${cliente}</span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label"><i class="fas fa-envelope"></i> Email</span>
            <span class="detalle-value">${email}</span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label"><i class="fas fa-phone"></i> Teléfono</span>
            <span class="detalle-value">${telefono}</span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label"><i class="fas fa-calendar"></i> Fecha y Hora</span>
            <span class="detalle-value">${fechaHora}</span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label"><i class="fas fa-cut"></i> Servicios</span>
            <span class="detalle-value">${servicios}</span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label"><i class="fas fa-tag"></i> Estado</span>
            <span class="detalle-value"><span class="estado-badge ${estadoClass[estado]}"><i class="fas ${estadoIcon[estado]}"></i> ${estado}</span></span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label"><i class="fas fa-comment"></i> Observaciones</span>
            <span class="detalle-value">${observaciones}</span>
        </div>
    `;
    
    document.getElementById('detalleContent').innerHTML = html;
    document.getElementById('modalDetalle').classList.add('modal-activo');
}

function cambiarEstado(id, estadoActual) {
    document.getElementById('estado_id_cita').value = id;
    const select = document.getElementById('nuevo_estado');
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value === estadoActual) {
            select.options[i].selected = true;
            break;
        }
    }
    document.getElementById('modalEstado').classList.add('modal-activo');
}

function cerrarModal(id) {
    document.getElementById(id).classList.remove('modal-activo');
}

window.onclick = function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('modal-activo');
    }
}
</script>

</body>
</html>