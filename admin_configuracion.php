<?php
session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('gestion_configuracion');

$pdo = conectar();
$mensaje = '';
$error = '';

// ============================================================
// Crear tabla de configuración si no existe
// ============================================================
$pdo->exec("
CREATE TABLE IF NOT EXISTS `configuracion` (
    `id_config` int(11) NOT NULL AUTO_INCREMENT,
    `clave` varchar(100) NOT NULL,
    `valor` text,
    `tipo` enum('texto','numero','json','boolean','horario') DEFAULT 'texto',
    `descripcion` varchar(255) DEFAULT NULL,
    `grupo` varchar(50) DEFAULT 'general',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_config`),
    UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ============================================================
// Insertar configuraciones por defecto si no existen
// ============================================================
$config_defaults = [
    // Horarios
    ['clave' => 'hora_apertura', 'valor' => '09:00', 'tipo' => 'horario', 'descripcion' => 'Hora de apertura del salón', 'grupo' => 'horarios'],
    ['clave' => 'hora_cierre', 'valor' => '20:00', 'tipo' => 'horario', 'descripcion' => 'Hora de cierre del salón', 'grupo' => 'horarios'],
    ['clave' => 'duracion_turno', 'valor' => '30', 'tipo' => 'numero', 'descripcion' => 'Duración predeterminada de los turnos (minutos)', 'grupo' => 'horarios'],
    ['clave' => 'intervalo_turnos', 'valor' => '30', 'tipo' => 'numero', 'descripcion' => 'Intervalo entre turnos (minutos)', 'grupo' => 'horarios'],
    
    // Días no laborables (JSON)
    ['clave' => 'dias_no_laborables', 'valor' => '["2026-01-01","2026-05-01","2026-12-25"]', 'tipo' => 'json', 'descripcion' => 'Días no laborables (formato YYYY-MM-DD)', 'grupo' => 'horarios'],
    ['clave' => 'dias_semana_laborales', 'valor' => '[1,2,3,4,5,6]', 'tipo' => 'json', 'descripcion' => 'Días laborables (1=Lun, 2=Mar, 3=Mie, 4=Jue, 5=Vie, 6=Sab, 7=Dom)', 'grupo' => 'horarios'],
    
    // Notificaciones
    ['clave' => 'recordatorio_citas', 'valor' => '1', 'tipo' => 'boolean', 'descripcion' => 'Enviar recordatorio de citas por email', 'grupo' => 'notificaciones'],
    ['clave' => 'recordatorio_horas', 'valor' => '24', 'tipo' => 'numero', 'descripcion' => 'Horas antes para enviar recordatorio', 'grupo' => 'notificaciones'],
    ['clave' => 'notificar_cancelaciones', 'valor' => '1', 'tipo' => 'boolean', 'descripcion' => 'Notificar cancelaciones al admin', 'grupo' => 'notificaciones'],
    
    // Reservas
    ['clave' => 'anticipo_reserva', 'valor' => '0', 'tipo' => 'boolean', 'descripcion' => 'Requerir anticipo/Seña para reservas', 'grupo' => 'reservas'],
    ['clave' => 'porcentaje_anticipo', 'valor' => '30', 'tipo' => 'numero', 'descripcion' => 'Porcentaje de anticipo requerido', 'grupo' => 'reservas'],
    ['clave' => 'cancelacion_horas', 'valor' => '24', 'tipo' => 'numero', 'descripcion' => 'Horas de anticipación para cancelar sin costo', 'grupo' => 'reservas'],
    
    // Apariencia
    ['clave' => 'nombre_salon', 'valor' => 'SalonHub', 'tipo' => 'texto', 'descripcion' => 'Nombre del salón', 'grupo' => 'apariencia'],
    ['clave' => 'color_primario', 'valor' => '#c9a84c', 'tipo' => 'texto', 'descripcion' => 'Color principal del sistema', 'grupo' => 'apariencia']
];

$stmt_check = $pdo->prepare("SELECT COUNT(*) FROM configuracion WHERE clave = :clave");
$stmt_insert = $pdo->prepare("INSERT INTO configuracion (clave, valor, tipo, descripcion, grupo) VALUES (:clave, :valor, :tipo, :descripcion, :grupo)");

foreach ($config_defaults as $config) {
    $stmt_check->execute([':clave' => $config['clave']]);
    if ($stmt_check->fetchColumn() == 0) {
        $stmt_insert->execute([
            ':clave' => $config['clave'],
            ':valor' => $config['valor'],
            ':tipo' => $config['tipo'],
            ':descripcion' => $config['descripcion'],
            ':grupo' => $config['grupo']
        ]);
    }
}

// ============================================================
// Guardar configuración
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'cfg_') === 0) {
                $clave = substr($key, 4);
                $stmt = $pdo->prepare("UPDATE configuracion SET valor = :valor WHERE clave = :clave");
                $stmt->execute([':valor' => $value, ':clave' => $clave]);
            }
        }
        
        // Procesar días no laborables (array)
        if (isset($_POST['dias_no_laborables_array'])) {
            $dias_no_laborables = json_encode(array_values($_POST['dias_no_laborables_array']));
            $stmt = $pdo->prepare("UPDATE configuracion SET valor = :valor WHERE clave = 'dias_no_laborables'");
            $stmt->execute([':valor' => $dias_no_laborables]);
        }
        
        // Procesar días laborables (array)
        if (isset($_POST['dias_laborables'])) {
            $dias_laborables = json_encode(array_values($_POST['dias_laborables']));
            $stmt = $pdo->prepare("UPDATE configuracion SET valor = :valor WHERE clave = 'dias_semana_laborales'");
            $stmt->execute([':valor' => $dias_laborables]);
        }
        
        $pdo->commit();
        $mensaje = "✅ Configuración guardada correctamente.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ Error al guardar configuración: " . $e->getMessage();
    }
}

// ============================================================
// Obtener configuraciones agrupadas
// ============================================================
$configuraciones = [];
$stmt = $pdo->query("SELECT * FROM configuracion ORDER BY grupo, clave");
while ($row = $stmt->fetch()) {
    $configuraciones[$row['grupo']][] = $row;
}

// Procesar valores específicos
$dias_no_laborables = [];
$dias_laborables = [1,2,3,4,5,6]; // Default

foreach ($configuraciones as $grupo => $items) {
    foreach ($items as $item) {
        if ($item['clave'] === 'dias_no_laborables') {
            $dias_no_laborables = json_decode($item['valor'], true) ?: [];
        }
        if ($item['clave'] === 'dias_semana_laborales') {
            $dias_laborables = json_decode($item['valor'], true) ?: [1,2,3,4,5,6];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SalonHub - Configuración del Sistema</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .config-grupo {
            background: var(--loki-gris);
            border-radius: 16px;
            margin-bottom: 30px;
            overflow: hidden;
            border: 1px solid var(--borde);
        }
        .config-grupo-header {
            background: linear-gradient(135deg, rgba(201, 168, 76, 0.15), rgba(201, 168, 76, 0.05));
            padding: 15px 20px;
            border-bottom: 1px solid var(--borde);
        }
        .config-grupo-header h3 {
            margin: 0;
            color: var(--loki-dorado);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .config-grupo-body {
            padding: 20px;
        }
        .config-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--borde);
        }
        .config-row:last-child {
            border-bottom: none;
        }
        .config-label {
            flex: 1;
            min-width: 200px;
        }
        .config-label label {
            font-weight: 600;
            cursor: pointer;
        }
        .config-desc {
            font-size: 0.7rem;
            color: var(--texto-suave);
            margin-top: 4px;
        }
        .config-input {
            flex: 1;
            min-width: 200px;
        }
        .config-input input, 
        .config-input select {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--borde);
            background: var(--loki-negro);
            color: var(--texto);
        }
        .config-input input[type="checkbox"] {
            width: auto;
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--loki-dorado);
        }
        .config-boolean {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-guardar-config {
            background: linear-gradient(135deg, var(--loki-dorado), #b8943a);
            color: var(--loki-negro);
            padding: 12px 30px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-guardar-config:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(201, 168, 76, 0.3);
        }
        .dias-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .dia-item {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.05);
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
        .dia-item input {
            margin: 0;
        }
        .dia-item:hover {
            background: rgba(201, 168, 76, 0.1);
        }
        .fecha-item {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.05);
            padding: 8px 12px;
            border-radius: 8px;
            margin: 5px;
        }
        .btn-eliminar-fecha {
            background: #e74c3c;
            border: none;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-agregar-fecha {
            background: var(--loki-verde);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
        }
        .grid-2col {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
    </style>
</head>
<body>
<header class="header-panel">
    <div class="header-contenido">
        <h1 class="logo-header"><a href="home.php">✂ SalonHub</a></h1>
        <nav class="nav-header">
            <a href="home.php" class="btn btn-outline-sm">← Panel</a>
            <a href="cerrar_sesion.php" class="btn btn-outline-sm btn-danger-outline">Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2>⚙️ Configuración del Sistema</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <?php foreach ($configuraciones as $grupo => $items): ?>
            <div class="config-grupo">
                <div class="config-grupo-header">
                    <h3>
                        <?php 
                        $iconos = [
                            'horarios' => '⏰',
                            'notificaciones' => '📧',
                            'reservas' => '📅',
                            'apariencia' => '🎨',
                            'general' => '⚙️'
                        ];
                        echo $iconos[$grupo] ?? '🔧';
                        ?>
                        <?= ucfirst($grupo) ?>
                    </h3>
                </div>
                <div class="config-grupo-body">
                    <?php foreach ($items as $item): 
                        $valor = $item['valor'];
                        $clave = $item['clave'];
                    ?>
                        <div class="config-row">
                            <div class="config-label">
                                <label><?= htmlspecialchars(str_replace('_', ' ', ucfirst($clave))) ?></label>
                                <div class="config-desc"><?= htmlspecialchars($item['descripcion'] ?? '') ?></div>
                            </div>
                            <div class="config-input">
                                <?php if ($item['tipo'] === 'boolean'): ?>
                                    <div class="config-boolean">
                                        <input type="checkbox" name="cfg_<?= $clave ?>" value="1" <?= $valor == '1' ? 'checked' : '' ?>>
                                        <span>Activo</span>
                                    </div>
                                <?php elseif ($item['tipo'] === 'numero'): ?>
                                    <input type="number" name="cfg_<?= $clave ?>" value="<?= htmlspecialchars($valor) ?>">
                                <?php elseif ($item['tipo'] === 'horario'): ?>
                                    <input type="time" name="cfg_<?= $clave ?>" value="<?= htmlspecialchars($valor) ?>">
                                <?php elseif ($clave === 'dias_no_laborables'): ?>
                                    <div id="dias-no-laborables-container">
                                        <?php foreach ($dias_no_laborables as $fecha): ?>
                                            <div class="fecha-item">
                                                <span><?= date('d/m/Y', strtotime($fecha)) ?></span>
                                                <input type="hidden" name="dias_no_laborables_array[]" value="<?= $fecha ?>">
                                                <button type="button" class="btn-eliminar-fecha" onclick="eliminarFecha(this)">✖</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn-agregar-fecha" onclick="agregarFecha()">+ Agregar día no laborable</button>
                                <?php elseif ($clave === 'dias_semana_laborales'): ?>
                                    <div class="dias-selector">
                                        <?php 
                                        $dias_nombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                                        for ($i = 1; $i <= 7; $i++): 
                                            $checked = in_array($i, $dias_laborables);
                                        ?>
                                            <label class="dia-item">
                                                <input type="checkbox" name="dias_laborables[]" value="<?= $i ?>" <?= $checked ? 'checked' : '' ?>>
                                                <?= $dias_nombres[$i-1] ?>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <input type="text" name="cfg_<?= $clave ?>" value="<?= htmlspecialchars($valor) ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="text-align: right; margin-top: 20px;">
            <button type="submit" name="guardar_config" class="btn-guardar-config">
                💾 Guardar toda la configuración
            </button>
        </div>
    </form>
    
    <div class="alerta alerta-info" style="margin-top: 20px;">
        <strong>ℹ️ Información del Sistema:</strong><br>
        • PHP Version: <?= phpversion() ?><br>
        • Servidor: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido' ?><br>
        • Base de Datos: MySQL / MariaDB<br>
        • Zona Horaria: <?= date_default_timezone_get() ?>
    </div>
</main>

<script>
function agregarFecha() {
    const container = document.getElementById('dias-no-laborables-container');
    const nuevaFecha = prompt('Ingresá la fecha (formato: YYYY-MM-DD):', '2026-01-01');
    if (nuevaFecha && /^\d{4}-\d{2}-\d{2}$/.test(nuevaFecha)) {
        const div = document.createElement('div');
        div.className = 'fecha-item';
        div.innerHTML = `
            <span>${formatearFecha(nuevaFecha)}</span>
            <input type="hidden" name="dias_no_laborables_array[]" value="${nuevaFecha}">
            <button type="button" class="btn-eliminar-fecha" onclick="eliminarFecha(this)">✖</button>
        `;
        container.appendChild(div);
    } else if (nuevaFecha) {
        alert('Formato inválido. Usá YYYY-MM-DD (ejemplo: 2026-12-25)');
    }
}

function eliminarFecha(boton) {
    if (confirm('¿Eliminar este día no laborable?')) {
        boton.parentElement.remove();
    }
}

function formatearFecha(fecha) {
    const partes = fecha.split('-');
    return partes[2] + '/' + partes[1] + '/' + partes[0];
}
</script>

</body>
</html>
