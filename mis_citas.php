<?php
session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('ver_mis_citas');

$pdo = conectar();
$id_cliente = $_SESSION['id_cliente'];

// Obtener citas del cliente
$stmt = $pdo->prepare("
    SELECT c.id_cita, c.fecha_hora, c.estado_cita, c.observaciones,
           s.nombre as servicio_nombre, s.duracion, s.precio,
           e.id_empleado, p.nombre as empleado_nombre, p.apellido as empleado_apellido
    FROM cita c
    LEFT JOIN detalle_cita dc ON dc.id_cita = c.id_cita
    LEFT JOIN servicios s ON s.id_servicio = dc.id_servicio
    LEFT JOIN empleado e ON e.id_empleado = c.id_empleado
    LEFT JOIN persona p ON p.id_persona = e.id_persona
    WHERE c.id_cliente = :id_cliente AND c.visible_panel = 1
    ORDER BY c.fecha_hora DESC
");
$stmt->execute([':id_cliente' => $id_cliente]);
$citas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Citas - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .cita-card { background: var(--loki-gris); border-radius: 12px; padding: 20px; margin-bottom: 15px; border-left: 4px solid var(--loki-dorado); }
        .estado-pendiente { color: #f39c12; }
        .estado-confirmada { color: #2ecc71; }
        .estado-cancelada { color: #e74c3c; }
        .cita-fecha { font-size: 1.1rem; font-weight: bold; color: var(--loki-dorado); }
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
    <h2>📅 Mis Citas</h2>
    <?php if (empty($citas)): ?>
        <div class="alerta alerta-info">No tenés citas programadas. <a href="nueva_cita.php">Reservá un turno</a></div>
    <?php else: ?>
        <?php foreach ($citas as $cita): ?>
        <div class="cita-card">
            <div class="cita-fecha">📅 <?= date('d/m/Y H:i', strtotime($cita['fecha_hora'])) ?></div>
            <div><strong>Servicio:</strong> <?= htmlspecialchars($cita['servicio_nombre'] ?? 'No especificado') ?></div>
            <div><strong>Empleado:</strong> <?= htmlspecialchars($cita['empleado_nombre'] ?? '') ?> <?= htmlspecialchars($cita['empleado_apellido'] ?? 'Por asignar') ?></div>
            <div><strong>Estado:</strong> <span class="estado-<?= $cita['estado_cita'] ?>"><?= ucfirst($cita['estado_cita']) ?></span></div>
            <?php if ($cita['observaciones']): ?>
                <div><strong>Observaciones:</strong> <?= htmlspecialchars($cita['observaciones']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>
