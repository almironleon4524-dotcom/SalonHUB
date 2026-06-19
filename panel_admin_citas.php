<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('gestion_citas');

$pdo = conectar();
$mensaje = '';
$error = '';

// Cambiar estado de una cita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    $id_cita = (int)$_POST['id_cita'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    $stmt = $pdo->prepare("UPDATE cita SET estado_cita = :estado WHERE id_cita = :id");
    if ($stmt->execute([':estado' => $nuevo_estado, ':id' => $id_cita])) {
        $mensaje = "✅ Estado actualizado correctamente.";
    } else {
        $error = "❌ Error al actualizar.";
    }
}

// Asignar empleado a una cita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asignar_empleado'])) {
    $id_cita = (int)$_POST['id_cita'];
    $id_empleado = (int)$_POST['id_empleado'];
    
    $stmt = $pdo->prepare("UPDATE cita SET id_empleado = :id_empleado WHERE id_cita = :id");
    if ($stmt->execute([':id_empleado' => $id_empleado, ':id' => $id_cita])) {
        $mensaje = "✅ Empleado asignado correctamente.";
    } else {
        $error = "❌ Error al asignar empleado.";
    }
}

// Cancelar cita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_cita'])) {
    $id_cita = (int)$_POST['id_cita'];
    $motivo = trim($_POST['motivo_cancelacion'] ?? 'Cancelada por administrador');
    
    $stmt = $pdo->prepare("UPDATE cita SET estado_cita = 'cancelada', observaciones = CONCAT(IFNULL(observaciones, ''), ' | Cancelada: ', :motivo) WHERE id_cita = :id");
    if ($stmt->execute([':motivo' => $motivo, ':id' => $id_cita])) {
        $mensaje = "✅ Cita cancelada.";
    } else {
        $error = "❌ Error al cancelar.";
    }
}

// Filtros
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$estado_filtro = $_GET['estado'] ?? 'todas';
$empleado_filtro = $_GET['empleado'] ?? 'todos';

// Obtener lista de empleados para el filtro
$empleados = $pdo->query("
    SELECT e.id_empleado, p.nombre, p.apellido, e.especialidad
    FROM empleado e
    JOIN persona p ON p.id_persona = e.id_persona
    ORDER BY p.nombre
")->fetchAll();

// Construir consulta
$sql = "
    SELECT 
        c.id_cita,
        c.fecha_hora,
        c.estado_cita,
        c.observaciones,
        u.id_usuario,
        u.usuario,
        u.email,
        p.nombre as cliente_nombre,
        p.apellido as cliente_apellido,
        p.telefono,
        e.id_empleado,
        ep.nombre as empleado_nombre,
        ep.apellido as empleado_apellido,
        ep.telefono as empleado_telefono,
        GROUP_CONCAT(DISTINCT s.nombre SEPARATOR ', ') as servicios
    FROM cita c
    LEFT JOIN usuario u ON u.id_usuario = c.id_usuario
    LEFT JOIN cliente cl ON cl.id_cliente = u.id_cliente
    LEFT JOIN persona p ON p.id_persona = cl.id_persona
    LEFT JOIN empleado e ON e.id_empleado = c.id_empleado
    LEFT JOIN persona ep ON ep.id_persona = e.id_persona
    LEFT JOIN servicio_cita sc ON sc.id_cita = c.id_cita
    LEFT JOIN servicios s ON s.id_servicio = sc.id_servicio
    WHERE DATE(c.fecha_hora) = :fecha
";

$params = [':fecha' => $fecha_filtro];

if ($estado_filtro !== 'todas') {
    $sql .= " AND c.estado_cita = :estado";
    $params[':estado'] = $estado_filtro;
}

if ($empleado_filtro !== 'todos') {
    $sql .= " AND c.id_empleado = :id_empleado";
    $params[':id_empleado'] = $empleado_filtro;
}

$sql .= " GROUP BY c.id_cita ORDER BY c.fecha_hora ASC";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$citas = $stmt->fetchAll();

// Estadísticas del día
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado_cita = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado_cita = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
        SUM(CASE WHEN estado_cita = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN estado_cita = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
        COUNT(DISTINCT id_empleado) as empleados_con_turnos
    FROM cita
    WHERE DATE(fecha_hora) = CURDATE()
");
$stats->execute();
$stats_hoy = $stats->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin Citas - SalonHub</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: var(--loki-gris);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            border-left: 3px solid var(--loki-dorado);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--loki-dorado);
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
            background: var(--loki-dorado);
            color: var(--loki-negro);
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
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
            background: rgba(201, 168, 76, 0.1);
            color: var(--loki-dorado);
        }
        .tabla-citas tr:hover {
            background: rgba(201, 168, 76, 0.05);
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
        .btn-accion { padding: 5px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.7rem; margin: 2px; }
        .btn-asignar { background: #9b59b6; color: white; }
        .sin-empleado { color: #e74c3c; font-style: italic; }
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
            border: 1px solid var(--loki-dorado);
        }
    </style>
</head>
<body>
<header class="header-panel">
    <div class="header-contenido">
        <h1 class="logo-header"><a href="home.php">✂ SalonHub</a></h1>
        <nav class="nav-header">
            <span class="usuario-info">👤 <?= htmlspecialchars($_SESSION['usuario']) ?> <span class="badge-rol badge-administrador">Admin</span></span>
            <a href="home.php" class="btn btn-outline-sm">← Panel</a>
            <a href="cerrar_sesion.php" class="btn btn-outline-sm btn-danger-outline">Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2>📋 Administración de Citas</h2>
    <p style="color: var(--texto-suave); margin-bottom: 20px;">Gestiona todas las citas del sistema y asigna empleados</p>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?= $stats_hoy['total'] ?? 0 ?></div><div class="stat-label">📋 Citas Hoy</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#f1c40f;"><?= $stats_hoy['pendientes'] ?? 0 ?></div><div class="stat-label">⏳ Pendientes</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#2ecc71;"><?= $stats_hoy['confirmadas'] ?? 0 ?></div><div class="stat-label">✅ Confirmadas</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#3498db;"><?= $stats_hoy['completadas'] ?? 0 ?></div><div class="stat-label">✔️ Completadas</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#e74c3c;"><?= $stats_hoy['canceladas'] ?? 0 ?></div><div class="stat-label">❌ Canceladas</div></div>
        <div class="stat-card"><div class="stat-number"><?= $stats_hoy['empleados_con_turnos'] ?? 0 ?></div><div class="stat-label">👨‍💼 Empleados</div></div>
    </div>
    
    <!-- Filtros -->
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
                <option value="cancelada" <?= $estado_filtro === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
            </select>
        </div>
        <div class="filtro-grupo">
            <label>👨‍💼 Empleado</label>
            <select name="empleado">
                <option value="todos" <?= $empleado_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                <?php foreach ($empleados as $emp): ?>
                    <option value="<?= $emp['id_empleado'] ?>" <?= $empleado_filtro == $emp['id_empleado'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['nombre'] . ' ' . $emp['apellido']) ?> (<?= htmlspecialchars($emp['especialidad'] ?? 'General') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-grupo">
            <button type="submit" class="btn-filtro">🔎 Filtrar</button>
            <button type="button" class="btn-filtro" style="background:#555;" onclick="window.location.href='panel_admin_citas.php'">🔄 Limpiar</button>
        </div>
    </form>
    
    <!-- Tabla de citas -->
    <div class="tabla-responsive">
        <table class="tabla-citas">
            <thead>
                <tr><th>Hora</th><th>Cliente</th><th>Servicios</th><th>Empleado</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (empty($citas)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:40px;">📭 No hay citas para esta fecha</td></tr>
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
                        $tiene_empleado = !empty($cita['id_empleado']);
                    ?>
                        <tr>
                            <td><strong><?= $hora ?></strong></td>
                            <td><?= htmlspecialchars($cita['cliente_nombre'] . ' ' . $cita['cliente_apellido']) ?><br><small>@<?= htmlspecialchars($cita['usuario']) ?></small></td>
                            <td><?= htmlspecialchars($cita['servicios'] ?? 'Sin servicio') ?></td>
                            <td>
                                <?php if ($tiene_empleado): ?>
                                    🧑 <?= htmlspecialchars($cita['empleado_nombre'] . ' ' . $cita['empleado_apellido']) ?>
                                <?php else: ?>
                                    <span class="sin-empleado">⚠️ Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="estado-badge <?= $estado_class ?>"><?= ucfirst($cita['estado_cita']) ?></span></td>
                            <td>
                                <button class="btn-accion btn-ver" onclick="verDetalle(<?= $cita['id_cita'] ?>, '<?= addslashes($cita['cliente_nombre'] . ' ' . $cita['cliente_apellido']) ?>', '<?= $hora ?>', '<?= addslashes($cita['servicios'] ?? 'Sin servicio') ?>', '<?= $cita['estado_cita'] ?>', '<?= addslashes($cita['observaciones'] ?? 'Sin observaciones') ?>', '<?= htmlspecialchars($cita['email']) ?>', '<?= htmlspecialchars($cita['telefono'] ?? 'No disponible') ?>', <?= $cita['id_empleado'] ?? 'null' ?>, '<?= addslashes($cita['empleado_nombre'] ?? '') . ' ' . addslashes($cita['empleado_apellido'] ?? '') ?>')">👁️ Ver</button>
                                <button class="btn-accion btn-asignar" onclick="asignarEmpleado(<?= $cita['id_cita'] ?>, <?= $cita['id_empleado'] ?? 'null' ?>)">👨‍💼 Asignar</button>
                                <?php if ($cita['estado_cita'] !== 'cancelada' && $cita['estado_cita'] !== 'completada'): ?>
                                <button class="btn-accion btn-estado" onclick="cambiarEstado(<?= $cita['id_cita'] ?>, '<?= $cita['estado_cita'] ?>')">📝 Estado</button>
                                <button class="btn-accion btn-inactivo" onclick="cancelarCita(<?= $cita['id_cita'] ?>)">❌ Cancelar</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Tarjetas móvil -->
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
            <div><strong>Cliente:</strong> <?= htmlspecialchars($cita['cliente_nombre'] . ' ' . $cita['cliente_apellido']) ?></div>
            <div><strong>Servicios:</strong> <?= htmlspecialchars($cita['servicios'] ?? 'Sin servicio') ?></div>
            <div><strong>Empleado:</strong> <?= !empty($cita['id_empleado']) ? htmlspecialchars($cita['empleado_nombre'] . ' ' . $cita['empleado_apellido']) : '<span class="sin-empleado">Sin asignar</span>' ?></div>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                <button class="btn-accion btn-ver" onclick="verDetalle(<?= $cita['id_cita'] ?>, '<?= addslashes($cita['cliente_nombre'] . ' ' . $cita['cliente_apellido']) ?>', '<?= $hora ?>', '<?= addslashes($cita['servicios'] ?? 'Sin servicio') ?>', '<?= $cita['estado_cita'] ?>', '<?= addslashes($cita['observaciones'] ?? 'Sin observaciones') ?>', '<?= htmlspecialchars($cita['email']) ?>', '<?= htmlspecialchars($cita['telefono'] ?? 'No disponible') ?>', <?= $cita['id_empleado'] ?? 'null' ?>, '<?= addslashes($cita['empleado_nombre'] ?? '') . ' ' . addslashes($cita['empleado_apellido'] ?? '') ?>')">👁️ Ver</button>
                <button class="btn-accion btn-asignar" onclick="asignarEmpleado(<?= $cita['id_cita'] ?>, <?= $cita['id_empleado'] ?? 'null' ?>)">👨‍💼 Asignar</button>
            </div>
        </div>
    <?php endforeach; ?>
</main>

<!-- Modales -->
<div id="modalDetalle" class="modal"><div class="modal-content"><h3 style="color:var(--loki-dorado);">📋 Detalle de Cita</h3><div id="detalleContent"></div><button class="btn-accion" onclick="cerrarModal()" style="margin-top:15px;">Cerrar</button></div></div>
<div id="modalAsignar" class="modal"><div class="modal-content"><h3 style="color:var(--loki-dorado);">👨‍💼 Asignar Empleado</h3><form method="POST"><input type="hidden" name="id_cita" id="asignar_id_cita"><div class="campo-grupo"><label>Empleado</label><select name="id_empleado" id="asignar_empleado" style="width:100%; padding:8px;"><?php foreach ($empleados as $emp): ?><option value="<?= $emp['id_empleado'] ?>"><?= htmlspecialchars($emp['nombre'] . ' ' . $emp['apellido']) ?> (<?= htmlspecialchars($emp['especialidad'] ?? 'General') ?>)</option><?php endforeach; ?></select></div><div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;"><button type="button" class="btn-accion" onclick="cerrarModalAsignar()" style="background:#555;">Cancelar</button><button type="submit" name="asignar_empleado" class="btn-accion btn-asignar">Asignar</button></div></form></div></div>
<div id="modalEstado" class="modal"><div class="modal-content"><h3 style="color:var(--loki-dorado);">📝 Cambiar Estado</h3><form method="POST"><input type="hidden" name="id_cita" id="estado_id_cita"><div class="campo-grupo"><label>Nuevo estado</label><select name="nuevo_estado" id="nuevo_estado" style="width:100%; padding:8px;"><option value="pendiente">⏳ Pendiente</option><option value="confirmada">✅ Confirmada</option><option value="completada">✔️ Completada</option><option value="cancelada">❌ Cancelada</option></select></div><div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;"><button type="button" class="btn-accion" onclick="cerrarModalEstado()" style="background:#555;">Cancelar</button><button type="submit" name="cambiar_estado" class="btn-accion btn-estado">Guardar</button></div></form></div></div>
<div id="modalCancelar" class="modal"><div class="modal-content"><h3 style="color:#e74c3c;">❌ Cancelar Cita</h3><form method="POST"><input type="hidden" name="id_cita" id="cancelar_id_cita"><div class="campo-grupo"><label>Motivo</label><textarea name="motivo_cancelacion" rows="3" style="width:100%; padding:8px; border-radius:8px;"></textarea></div><div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;"><button type="button" class="btn-accion" onclick="cerrarModalCancelar()" style="background:#555;">Volver</button><button type="submit" name="cancelar_cita" class="btn-accion btn-inactivo">Confirmar</button></div></form></div></div>

<script>
function verDetalle(id, cliente, hora, servicios, estado, observaciones, email, telefono, id_empleado, empleado_nombre) {
    const estadoClass = {'pendiente':'estado-pendiente','confirmada':'estado-confirmada','completada':'estado-completada','cancelada':'estado-cancelada'};
    const empleadoText = id_empleado ? empleado_nombre : '<span class="sin-empleado">Sin asignar</span>';
    const html = `<div><strong>👤 Cliente:</strong> ${cliente}</div><div><strong>📧 Email:</strong> ${email}</div><div><strong>📞 Teléfono:</strong> ${telefono}</div><div><strong>🕐 Hora:</strong> ${hora}</div><div><strong>💇 Servicios:</strong> ${servicios}</div><div><strong>👨‍💼 Empleado:</strong> ${empleadoText}</div><div><strong>📌 Estado:</strong> <span class="estado-badge ${estadoClass[estado]}">${estado}</span></div><div><strong>📝 Observaciones:</strong> ${observaciones}</div>`;
    document.getElementById('detalleContent').innerHTML = html;
    document.getElementById('modalDetalle').classList.add('modal-activo');
}
function asignarEmpleado(id, empleadoActual) {
    document.getElementById('asignar_id_cita').value = id;
    if(empleadoActual) document.getElementById('asignar_empleado').value = empleadoActual;
    document.getElementById('modalAsignar').classList.add('modal-activo');
}
function cambiarEstado(id, estadoActual) {
    document.getElementById('estado_id_cita').value = id;
    const select = document.getElementById('nuevo_estado');
    for(let i=0;i<select.options.length;i++) if(select.options[i].value === estadoActual) select.options[i].selected = true;
    document.getElementById('modalEstado').classList.add('modal-activo');
}
function cancelarCita(id) { document.getElementById('cancelar_id_cita').value = id; document.getElementById('modalCancelar').classList.add('modal-activo'); }
function cerrarModal() { document.getElementById('modalDetalle').classList.remove('modal-activo'); }
function cerrarModalAsignar() { document.getElementById('modalAsignar').classList.remove('modal-activo'); }
function cerrarModalEstado() { document.getElementById('modalEstado').classList.remove('modal-activo'); }
function cerrarModalCancelar() { document.getElementById('modalCancelar').classList.remove('modal-activo'); }
window.onclick = function(e) { if(e.target.classList.contains('modal')) e.target.classList.remove('modal-activo'); }
</script>
</body>
</html>
