<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/permisos_helper.php';

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
    
    $stmt = $pdo->prepare("SELECT id_cita FROM cita WHERE id_cita = :id_cita AND id_empleado = :id_empleado");
    $stmt->execute([':id_cita' => $id_cita, ':id_empleado' => $id_empleado]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE cita SET estado_cita = :estado WHERE id_cita = :id");
        if ($stmt->execute([':estado' => $nuevo_estado, ':id' => $id_cita])) {
            $mensaje = "✅ Estado de la cita actualizado correctamente.";
        } else {
            $error = "❌ Error al actualizar el estado.";
        }
    } else {
        $error = "No tienes permiso para modificar esta cita.";
    }
}

// Filtros
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$estado_filtro = $_GET['estado'] ?? 'todas';

// Obtener citas del empleado - CORREGIDO: usar detalle_cita en lugar de servicio_cita
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
    LEFT JOIN usuario u ON u.id_usuario = c.id_usuario
    LEFT JOIN cliente cl ON cl.id_cliente = u.id_cliente
    LEFT JOIN persona p ON p.id_persona = cl.id_persona
    LEFT JOIN detalle_cita dc ON dc.id_cita = c.id_cita
    LEFT JOIN servicios s ON s.id_servicio = dc.id_servicio
    WHERE c.id_empleado = :id_empleado
    AND DATE(c.fecha_hora) = :fecha
";

$params = [':id_empleado' => $id_empleado, ':fecha' => $fecha_filtro];

if ($estado_filtro !== 'todas') {
    $sql .= " AND c.estado_cita = :estado";
    $params[':estado'] = $estado_filtro;
}

$sql .= " GROUP BY c.id_cita ORDER BY c.fecha_hora ASC";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$citas = $stmt->fetchAll();

// Obtener estadísticas del día
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado_cita = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado_cita = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
        SUM(CASE WHEN estado_cita = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN estado_cita = 'cancelada' THEN 1 ELSE 0 END) as canceladas
    FROM cita
    WHERE id_empleado = :id_empleado AND DATE(fecha_hora) = CURDATE()
");
$stats->execute([':id_empleado' => $id_empleado]);
$stats_hoy = $stats->fetch();

// Obtener nombre del empleado
$stmt = $pdo->prepare("
    SELECT p.nombre, p.apellido, e.especialidad
    FROM empleado e
    JOIN persona p ON p.id_persona = e.id_persona
    WHERE e.id_empleado = :id_empleado
");
$stmt->execute([':id_empleado' => $id_empleado]);
$empleado = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Empleado - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .header-empleado {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.15), rgba(46, 204, 113, 0.05));
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid var(--loki-verde);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: var(--loki-gris);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.8rem;
            color: var(--texto-suave);
        }
        .filtros-container {
            background: var(--loki-gris);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filtro-grupo {
            flex: 1;
            min-width: 150px;
        }
        .filtro-grupo label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.8rem;
            color: var(--texto-suave);
        }
        .filtro-grupo select, .filtro-grupo input {
            width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--borde);
            background: var(--loki-negro);
            color: var(--texto);
        }
        .btn-filtro {
            background: var(--loki-verde);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        .tabla-citas {
            width: 100%;
            border-collapse: collapse;
            background: var(--loki-gris);
            border-radius: 12px;
            overflow: hidden;
        }
        .tabla-citas th, .tabla-citas td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--borde);
        }
        .tabla-citas th {
            background: rgba(46, 204, 113, 0.1);
            color: var(--loki-verde);
        }
        .tabla-citas tr:hover {
            background: rgba(46, 204, 113, 0.05);
        }
        .estado-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .estado-pendiente { background: rgba(241, 196, 15, 0.2); color: #f1c40f; }
        .estado-confirmada { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .estado-completada { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .estado-cancelada { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .btn-accion { padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.75rem; margin: 2px; }
        .btn-ver { background: #3498db; color: white; }
        .btn-estado { background: var(--loki-verde); color: white; }
        .cita-card {
            background: var(--loki-gris);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid var(--borde);
            display: none;
        }
        @media (max-width: 768px) {
            .tabla-citas { display: none; }
            .cita-card { display: block; }
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-activo { display: flex; }
        .modal-content {
            background: var(--loki-gris);
            border-radius: 16px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
            border: 1px solid var(--loki-verde);
        }
    </style>
</head>
<body>
<header class="header-panel">
    <div class="header-contenido">
        <h1 class="logo-header"><a href="home.php">✂ SalonHub</a></h1>
        <nav class="nav-header">
            <span class="usuario-info">👤 <?= htmlspecialchars($_SESSION['usuario']) ?> <span class="badge-rol badge-empleado">Empleado</span></span>
            <a href="home.php" class="btn btn-outline-sm">← Panel</a>
            <a href="cerrar_sesion.php" class="btn btn-outline-sm btn-danger-outline">Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <div class="header-empleado">
        <h2>👨‍💼 Panel de Empleado</h2>
        <p style="margin-top: 5px;">
            <strong><?= htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido']) ?></strong><br>
            <span style="color: var(--loki-verde-claro);">✂️ Especialidad: <?= htmlspecialchars($empleado['especialidad'] ?? 'General') ?></span>
        </p>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number" style="color: #3498db;"><?= $stats_hoy['total'] ?? 0 ?></div>
            <div class="stat-label">📋 Citas Hoy</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #f1c40f;"><?= $stats_hoy['pendientes'] ?? 0 ?></div>
            <div class="stat-label">⏳ Pendientes</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #2ecc71;"><?= $stats_hoy['confirmadas'] ?? 0 ?></div>
            <div class="stat-label">✅ Confirmadas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #3498db;"><?= $stats_hoy['completadas'] ?? 0 ?></div>
            <div class="stat-label">✔️ Completadas</div>
        </div>
    </div>
    
    <form method="GET" class="filtros-container">
        <div class="filtro-grupo">
            <label>📅 Fecha</label>
            <input type="date" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>">
        </div>
        <div class="filtro-grupo">
            <label>📌 Estado</label>
            <select name="estado">
                <option value="todas" <?= $estado_filtro === 'todas' ? 'selected' : '' ?>>Todas</option>
                <option value="pendiente" <?= $estado_filtro === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="confirmada" <?= $estado_filtro === 'confirmada' ? 'selected' : '' ?>>Confirmada</option>
                <option value="completada" <?= $estado_filtro === 'completada' ? 'selected' : '' ?>>Completada</option>
            </select>
        </div>
        <div class="filtro-grupo">
            <button type="submit" class="btn-filtro">🔎 Filtrar</button>
            <button type="button" class="btn-filtro" style="background: #555;" onclick="window.location.href='panel_empleado.php'">🔄 Limpiar</button>
        </div>
    </form>
    
    <div class="tabla-responsive">
        <table class="tabla-citas">
            <thead>
                <tr><th>Hora</th><th>Cliente</th><th>Servicios</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (empty($citas)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px;">📭 No hay citas para esta fecha</td></tr>
                <?php else: ?>
                    <?php foreach ($citas as $cita): 
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
                            <td><strong><?= $hora ?></strong></td>
                            <td><?= htmlspecialchars($cita['nombre'] . ' ' . $cita['apellido']) ?><br><small>@<?= htmlspecialchars($cita['usuario']) ?></small></td>
                            <td><?= htmlspecialchars($cita['servicios'] ?? 'Sin servicio') ?></td>
                            <td><span class="estado-badge <?= $estado_class ?>"><?= ucfirst($cita['estado_cita']) ?></span></td>
                            <td>
                                <button class="btn-accion btn-ver" onclick="verDetalle(<?= $cita['id_cita'] ?>, '<?= addslashes($cita['nombre'] . ' ' . $cita['apellido']) ?>', '<?= $hora ?>', '<?= addslashes($cita['servicios'] ?? 'Sin servicio') ?>', '<?= $cita['estado_cita'] ?>', '<?= addslashes($cita['observaciones'] ?? 'Sin observaciones') ?>', '<?= htmlspecialchars($cita['email']) ?>', '<?= htmlspecialchars($cita['telefono'] ?? 'No disponible') ?>')">👁️ Ver</button>
                                <?php if ($cita['estado_cita'] !== 'cancelada' && $cita['estado_cita'] !== 'completada'): ?>
                                <button class="btn-accion btn-estado" onclick="cambiarEstado(<?= $cita['id_cita'] ?>, '<?= $cita['estado_cita'] ?>')">📝 Estado</button>
                                <?php endif; ?>
                              </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php foreach ($citas as $cita): 
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
            <div style="display:flex; justify-content:space-between;">
                <strong>🕐 <?= $hora ?></strong>
                <span class="estado-badge <?= $estado_class ?>"><?= ucfirst($cita['estado_cita']) ?></span>
            </div>
            <div style="margin-top:8px;"><strong>Cliente:</strong> <?= htmlspecialchars($cita['nombre'] . ' ' . $cita['apellido']) ?></div>
            <div><strong>Servicios:</strong> <?= htmlspecialchars($cita['servicios'] ?? 'Sin servicio') ?></div>
            <div style="margin-top:10px; display:flex; gap:8px;">
                <button class="btn-accion btn-ver" onclick="verDetalle(<?= $cita['id_cita'] ?>, '<?= addslashes($cita['nombre'] . ' ' . $cita['apellido']) ?>', '<?= $hora ?>', '<?= addslashes($cita['servicios'] ?? 'Sin servicio') ?>', '<?= $cita['estado_cita'] ?>', '<?= addslashes($cita['observaciones'] ?? 'Sin observaciones') ?>', '<?= htmlspecialchars($cita['email']) ?>', '<?= htmlspecialchars($cita['telefono'] ?? 'No disponible') ?>')">👁️ Ver</button>
                <?php if ($cita['estado_cita'] !== 'cancelada' && $cita['estado_cita'] !== 'completada'): ?>
                <button class="btn-accion btn-estado" onclick="cambiarEstado(<?= $cita['id_cita'] ?>, '<?= $cita['estado_cita'] ?>')">📝 Estado</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</main>

<div id="modalDetalle" class="modal">
    <div class="modal-content">
        <h3 style="color: var(--loki-verde);">📋 Detalle de Cita</h3>
        <div id="detalleContent"></div>
        <div style="margin-top:20px; display:flex; justify-content:flex-end;">
            <button class="btn-accion" onclick="cerrarModal()" style="background:#555;">Cerrar</button>
        </div>
    </div>
</div>

<div id="modalEstado" class="modal">
    <div class="modal-content">
        <h3 style="color: var(--loki-verde);">📝 Cambiar Estado</h3>
        <form method="POST">
            <input type="hidden" name="id_cita" id="estado_id_cita">
            <div class="campo-grupo">
                <label>Nuevo estado</label>
                <select name="nuevo_estado" id="nuevo_estado" style="width:100%; padding:8px; border-radius:8px; background: var(--loki-negro);">
                    <option value="pendiente">⏳ Pendiente</option>
                    <option value="confirmada">✅ Confirmada</option>
                    <option value="completada">✔️ Completada</option>
                </select>
            </div>
            <div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn-accion" onclick="cerrarModalEstado()" style="background:#555;">Cancelar</button>
                <button type="submit" name="cambiar_estado" class="btn-accion btn-estado">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function verDetalle(id, cliente, hora, servicios, estado, observaciones, email, telefono) {
    const estadoClass = {
        'pendiente': 'estado-pendiente',
        'confirmada': 'estado-confirmada', 
        'completada': 'estado-completada',
        'cancelada': 'estado-cancelada'
    };
    const html = `
        <div><strong>👤 Cliente:</strong> ${cliente}</div>
        <div><strong>📧 Email:</strong> ${email}</div>
        <div><strong>📞 Teléfono:</strong> ${telefono}</div>
        <div><strong>🕐 Hora:</strong> ${hora}</div>
        <div><strong>💇 Servicios:</strong> ${servicios}</div>
        <div><strong>📌 Estado:</strong> <span class="estado-badge ${estadoClass[estado]}">${estado}</span></div>
        <div><strong>📝 Observaciones:</strong> ${observaciones}</div>
    `;
    document.getElementById('detalleContent').innerHTML = html;
    document.getElementById('modalDetalle').classList.add('modal-activo');
}

function cambiarEstado(id, estadoActual) {
    document.getElementById('estado_id_cita').value = id;
    const select = document.getElementById('nuevo_estado');
    for(let i = 0; i < select.options.length; i++) {
        if(select.options[i].value === estadoActual) {
            select.options[i].selected = true;
            break;
        }
    }
    document.getElementById('modalEstado').classList.add('modal-activo');
}

function cerrarModal() {
    document.getElementById('modalDetalle').classList.remove('modal-activo');
}

function cerrarModalEstado() {
    document.getElementById('modalEstado').classList.remove('modal-activo');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('modal-activo');
    }
}
</script>
</body>
</html>