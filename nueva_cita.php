<?php
session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('reservar_citas');

$pdo = conectar();
$mensaje = '';
$error = '';
$servicios = $pdo->query("SELECT id_servicio, nombre, duracion, precio FROM servicios WHERE estado = 'activo'")->fetchAll();
$empleados = $pdo->query("
    SELECT e.id_empleado, p.nombre, p.apellido, e.especialidad 
    FROM empleado e 
    JOIN persona p ON p.id_persona = e.id_persona
    JOIN usuario u ON u.id_usuario = e.id_usuario
    WHERE u.estado = 'activo'
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_servicio = (int)$_POST['id_servicio'];
    $id_empleado = (int)$_POST['id_empleado'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $observaciones = trim($_POST['observaciones'] ?? '');
    $fecha_hora = $fecha . ' ' . $hora . ':00';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO cita (id_cliente, id_empleado, fecha_hora, estado_cita, observaciones, visible_panel) VALUES (:id_cliente, :id_empleado, :fecha_hora, 'pendiente', :observaciones, 1)");
        $stmt->execute([
            ':id_cliente' => $_SESSION['id_cliente'],
            ':id_empleado' => $id_empleado,
            ':fecha_hora' => $fecha_hora,
            ':observaciones' => $observaciones
        ]);
        $id_cita = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO detalle_cita (id_cita, id_servicio) VALUES (:id_cita, :id_servicio)");
        $stmt->execute([':id_cita' => $id_cita, ':id_servicio' => $id_servicio]);
        
        $mensaje = "✅ Turno reservado correctamente.";
    } catch (PDOException $e) {
        $error = "❌ Error al reservar turno.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reservar Turno - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
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
    <h2>📅 Reservar Turno</h2>
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><?= htmlspecialchars($mensaje) ?> <a href="mis_citas.php">Ver mis citas</a></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" class="formulario">
        <div class="campo-grupo">
            <label>Servicio *</label>
            <select name="id_servicio" required>
                <option value="">Seleccionar servicio</option>
                <?php foreach ($servicios as $s): ?>
                <option value="<?= $s['id_servicio'] ?>"><?= htmlspecialchars($s['nombre']) ?> - $<?= number_format($s['precio'], 0, ',', '.') ?> (<?= $s['duracion'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo-grupo">
            <label>Empleado *</label>
            <select name="id_empleado" required>
                <option value="">Seleccionar empleado</option>
                <?php foreach ($empleados as $e): ?>
                <option value="<?= $e['id_empleado'] ?>"><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?> - <?= htmlspecialchars($e['especialidad'] ?? 'General') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fila-campos">
            <div class="campo-grupo">
                <label>Fecha *</label>
                <input type="date" name="fecha" required min="<?= date('Y-m-d') ?>">
            </div>
            <div class="campo-grupo">
                <label>Hora *</label>
                <input type="time" name="hora" required>
            </div>
        </div>
        <div class="campo-grupo">
            <label>Observaciones</label>
            <textarea name="observaciones" rows="3" placeholder="Alguna preferencia o comentario..."></textarea>
        </div>
        <button type="submit" class="btn btn-primario">✅ Reservar Turno</button>
    </form>
</main>
</body>
</html>
