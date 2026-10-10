<?php
// Reservar Turno

session_start();
require_once __DIR__ . '/permisos_helper.php';
require_once __DIR__ . '/notificaciones_helper.php';

requerir_login();
requerir_permiso('reservar_citas');

// Verificar que el usuario tenga cliente asociado
if (empty($_SESSION['id_cliente'])) {
    die('Error: Tu usuario no tiene un cliente asociado. Contactá al administrador.');
}

$pdo = conectar();
$mensaje = '';
$error = '';

// Obtener servicios activos
$servicios = $pdo->query("SELECT id_servicio, nombre, duracion, precio FROM servicios WHERE estado = 'activo' ORDER BY nombre")->fetchAll();

// Obtener empleados activos
$empleados = $pdo->query("
    SELECT e.id_empleado, e.id_usuario, p.nombre, p.apellido, e.especialidad 
    FROM empleado e 
    JOIN persona p ON p.id_persona = e.id_persona
    JOIN usuario u ON u.id_usuario = e.id_usuario
    WHERE u.estado = 'activo'
    ORDER BY p.nombre
")->fetchAll();

// Procesar reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_servicio = (int)$_POST['id_servicio'];
    $id_empleado = (int)$_POST['id_empleado'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $observaciones = trim($_POST['observaciones'] ?? '');
    $fecha_hora = $fecha . ' ' . $hora . ':00';
    
    // Validaciones
    if ($id_servicio <= 0) {
        $error = 'Seleccioná un servicio.';
    } elseif ($id_empleado <= 0) {
        $error = 'Seleccioná un empleado.';
    } elseif (empty($fecha) || empty($hora)) {
        $error = 'Seleccioná fecha y hora.';
    } elseif (strtotime($fecha_hora) < time()) {
        $error = 'No se puede reservar en una fecha u hora pasada. Elegí un horario futuro.';
    } else {
        try {
            // Verificar disponibilidad del empleado
            $stmt_check = $pdo->prepare("
                SELECT COUNT(*) FROM cita 
                WHERE id_empleado = :id_empleado 
                AND fecha_hora = :fecha_hora 
                AND estado_cita != 'cancelada'
            ");
            $stmt_check->execute([
                ':id_empleado' => $id_empleado,
                ':fecha_hora' => $fecha_hora
            ]);
            
            if ($stmt_check->fetchColumn() > 0) {
                $error = 'El empleado ya tiene una cita en ese horario.';
            } else {
                $pdo->beginTransaction();
                
                // Crear la cita
                $stmt = $pdo->prepare("
                    INSERT INTO cita (id_cliente, id_empleado, fecha_hora, estado_cita, observaciones, visible_panel) 
                    VALUES (:id_cliente, :id_empleado, :fecha_hora, 'pendiente', :observaciones, 1)
                ");
                $stmt->execute([
                    ':id_cliente' => $_SESSION['id_cliente'],
                    ':id_empleado' => $id_empleado,
                    ':fecha_hora' => $fecha_hora,
                    ':observaciones' => $observaciones
                ]);
                $id_cita = $pdo->lastInsertId();
                
                // Crear detalle de la cita
                $stmt = $pdo->prepare("INSERT INTO detalle_cita (id_cita, id_servicio) VALUES (:id_cita, :id_servicio)");
                $stmt->execute([':id_cita' => $id_cita, ':id_servicio' => $id_servicio]);
                
                $pdo->commit();
                
                // Obtener datos para la notificación
                $stmt_info = $pdo->prepare("SELECT nombre FROM servicios WHERE id_servicio = ?");
                $stmt_info->execute([$id_servicio]);
                $nombre_servicio = $stmt_info->fetchColumn();
                
                $stmt_info = $pdo->prepare("
                    SELECT p.nombre, p.apellido 
                    FROM empleado e 
                    JOIN persona p ON p.id_persona = e.id_persona 
                    WHERE e.id_empleado = ?
                ");
                $stmt_info->execute([$id_empleado]);
                $emp = $stmt_info->fetch(PDO::FETCH_ASSOC);
                $nombre_empleado = $emp['nombre'] . ' ' . $emp['apellido'];
                
                $stmt_info = $pdo->prepare("
                    SELECT p.nombre, p.apellido 
                    FROM cliente c 
                    JOIN persona p ON p.id_persona = c.id_persona 
                    WHERE c.id_cliente = ?
                ");
                $stmt_info->execute([$_SESSION['id_cliente']]);
                $cli = $stmt_info->fetch(PDO::FETCH_ASSOC);
                $nombre_cliente = $cli['nombre'] . ' ' . $cli['apellido'];
                
                $fecha_formateada = date('d/m/Y H:i', strtotime($fecha_hora));
                
                // Notificar al empleado
                $stmt_emp = $pdo->prepare("SELECT id_usuario FROM empleado WHERE id_empleado = ?");
                $stmt_emp->execute([$id_empleado]);
                $id_usuario_empleado = $stmt_emp->fetchColumn();
                
                if ($id_usuario_empleado) {
                    notificar_empleado(
                        $id_usuario_empleado,
                        'nueva_cita_asignada',
                        'Nueva Cita Asignada',
                        "Tenés una nueva cita con {$nombre_cliente} el {$fecha_formateada} - Servicio: {$nombre_servicio}",
                        [
                            'prioridad' => 'media',
                            'modulo' => 'citas',
                            'url_accion' => "panel_empleado.php?id={$id_cita}",
                            'id_referencia' => $id_cita,
                            'tabla_referencia' => 'cita'
                        ]
                    );
                }
                
                // Notificar a los admins
                notificar_admin(
                    'nueva_cita',
                    'Nueva Cita',
                    "Nueva cita: {$nombre_cliente} con {$nombre_empleado} el {$fecha_formateada}",
                    [
                        'prioridad' => 'baja',
                        'modulo' => 'citas',
                        'url_accion' => "panel_admin_citas.php",
                        'id_referencia' => $id_cita,
                        'tabla_referencia' => 'cita'
                    ]
                );
                
                $mensaje = "Turno reservado correctamente.";
            }
        } catch (PDOException $e) {
            if (isset($pdo)) $pdo->rollBack();
            error_log("Error al reservar turno: " . $e->getMessage());
            $error = "Error al reservar turno: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar Turno - SalonHub</title>
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
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .contenedor-panel h2 {
            color: #e6edf3;
            font-size: 1.8rem;
            margin-bottom: 24px;
            border-bottom: 2px solid #30363d;
            padding-bottom: 12px;
        }
        
        .contenedor-panel h2 i {
            color: #c9a84c;
            margin-right: 10px;
        }
        
        .alerta {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .alerta-exito {
            background: rgba(46, 160, 67, 0.15);
            border: 1px solid #238636;
            color: #3fb950;
        }
        
        .alerta-exito a {
            color: #c9a84c;
            text-decoration: none;
            font-weight: 600;
            margin-left: auto;
        }
        
        .alerta-exito a:hover {
            text-decoration: underline;
        }
        
        .alerta-error {
            background: rgba(248, 81, 73, 0.15);
            border: 1px solid #da3633;
            color: #f85149;
        }
        
        .formulario {
            background: #161b22;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #30363d;
        }
        
        .campo-grupo {
            margin-bottom: 16px;
        }
        
        .campo-grupo label {
            display: block;
            color: #8b949e;
            font-size: 0.85rem;
            margin-bottom: 4px;
            font-weight: 500;
        }
        
        .campo-grupo label i {
            color: #c9a84c;
            margin-right: 6px;
            width: 18px;
        }
        
        .campo-grupo select,
        .campo-grupo input,
        .campo-grupo textarea {
            width: 100%;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #30363d;
            background: #0d1117;
            color: #e6edf3;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .campo-grupo select:focus,
        .campo-grupo input:focus,
        .campo-grupo textarea:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.15);
        }
        
        .campo-grupo select option {
            background: #161b22;
            color: #e6edf3;
        }
        
        .campo-grupo textarea {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        .fila-campos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .btn-primario {
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            justify-content: center;
        }
        
        .btn-primario:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
        }
        
        .btn-primario i {
            font-size: 1.1rem;
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
            .fila-campos {
                grid-template-columns: 1fr;
            }
            
            .contenedor-panel {
                max-width: 100%;
                padding: 16px;
            }
            
            .contenedor-panel h2 {
                font-size: 1.4rem;
            }
            
            .header-contenido {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .nav-header {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .formulario {
                padding: 16px;
            }
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
            <a href="home.php" class="btn-outline-sm"><i class="fas fa-arrow-left"></i> Volver</a>
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2><i class="fas fa-calendar-plus"></i> Reservar Turno</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($mensaje) ?>
            <a href="mis_citas.php"><i class="fas fa-arrow-right"></i> Ver mis citas</a>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alerta alerta-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="formulario">
        <div class="campo-grupo">
            <label for="id_servicio"><i class="fas fa-cut"></i> Servicio <span style="color: #f85149;">*</span></label>
            <select name="id_servicio" id="id_servicio" required>
                <option value="">Seleccionar servicio</option>
                <?php foreach ($servicios as $s): ?>
                    <option value="<?= $s['id_servicio'] ?>">
                        <?= htmlspecialchars($s['nombre']) ?> - $<?= number_format($s['precio'], 0, ',', '.') ?> (<?= $s['duracion'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="campo-grupo">
            <label for="id_empleado"><i class="fas fa-user-tie"></i> Empleado <span style="color: #f85149;">*</span></label>
            <select name="id_empleado" id="id_empleado" required>
                <option value="">Seleccionar empleado</option>
                <?php foreach ($empleados as $e): ?>
                    <option value="<?= $e['id_empleado'] ?>">
                        <?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?>
                        <?php if ($e['especialidad']): ?>
                            - <?= htmlspecialchars($e['especialidad']) ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="fila-campos">
            <div class="campo-grupo">
                <label for="fecha"><i class="fas fa-calendar-day"></i> Fecha <span style="color: #f85149;">*</span></label>
                <input type="date" name="fecha" id="fecha" required min="<?= date('Y-m-d') ?>">
            </div>
            <div class="campo-grupo">
                <label for="hora"><i class="fas fa-clock"></i> Hora <span style="color: #f85149;">*</span></label>
                <input type="time" name="hora" id="hora" required>
            </div>
        </div>
        
        <div class="campo-grupo">
            <label for="observaciones"><i class="fas fa-comment"></i> Observaciones</label>
            <textarea name="observaciones" id="observaciones" rows="3" placeholder="Alguna preferencia o comentario..."></textarea>
        </div>
        
        <button type="submit" class="btn-primario">
            <i class="fas fa-calendar-check"></i> Reservar Turno
        </button>
    </form>
</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Reservar Turno</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

<script>
// Validación de hora en el cliente
document.getElementById('fecha').addEventListener('change', function() {
    const fechaSeleccionada = this.value;
    const hoy = new Date().toISOString().split('T')[0];
    const horaInput = document.getElementById('hora');
    
    if (fechaSeleccionada === hoy) {
        // Si es hoy, la hora mínima es la hora actual + 30 min
        const ahora = new Date();
        ahora.setMinutes(ahora.getMinutes() + 30);
        const horaMinima = ahora.toTimeString().slice(0, 5);
        horaInput.min = horaMinima;
    } else {
        // Si es otro día, sin restricción
        horaInput.removeAttribute('min');
    }
});

// Validación al enviar
document.querySelector('.formulario').addEventListener('submit', function(e) {
    const fecha = document.getElementById('fecha').value;
    const hora = document.getElementById('hora').value;
    
    if (!fecha || !hora) return;
    
    const fechaHoraSeleccionada = new Date(fecha + 'T' + hora);
    const ahora = new Date();
    
    if (fechaHoraSeleccionada < ahora) {
        e.preventDefault();
        alert('No podés reservar en una fecha u hora pasada. Elegí un horario futuro.');
    }
});
</script>

</body>
</html>