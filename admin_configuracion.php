<?php



/*SalonHub - Configuración del Sistema - Módulo de Administración*/



session_start();
require_once __DIR__ . '/permisos_helper.php';

requerir_login();
requerir_permiso('gestion_configuracion');

$pdo = conectar();
$mensaje = '';
$error = '';



// Crear tabla de configuración si no existe



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



// Insertar configuraciones por defecto si no existen



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



// Guardar configuración



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



// Obtener configuraciones agrupadas



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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Configuración del Sistema</title>
    <link rel="stylesheet" href="css/estilos.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>



        /* ESTILOS GENERALES (igual que home.php)             */

        
        
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
            max-width: 1400px;
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
        

        
        /* ALERTAS                                            */

        
        
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
        
        .alerta-info {
            background: rgba(88, 166, 255, 0.08);
            border: 1px solid #30363d;
            color: #8b949e;
        }
        

        
        /* CONFIGURACIÓN                                      */

        
        
        .config-grupo {
            background: #161b22;
            border-radius: 12px;
            margin-bottom: 24px;
            overflow: hidden;
            border: 1px solid #30363d;
        }
        
        .config-grupo-header {
            background: rgba(201, 168, 76, 0.06);
            padding: 14px 20px;
            border-bottom: 1px solid #30363d;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .config-grupo-header i {
            color: #c9a84c;
            font-size: 1.2rem;
        }
        
        .config-grupo-header h3 {
            margin: 0;
            color: #c9a84c;
            font-size: 1.1rem;
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            gap: 12px;
        }
        
        .config-row:last-child {
            border-bottom: none;
        }
        
        .config-label {
            flex: 1;
            min-width: 180px;
        }
        
        .config-label label {
            font-weight: 600;
            cursor: pointer;
            color: #e6edf3;
            display: block;
        }
        
        .config-desc {
            font-size: 0.75rem;
            color: #8b949e;
            margin-top: 2px;
        }
        
        .config-input {
            flex: 1;
            min-width: 180px;
            max-width: 100%;
        }
        
        .config-input input, 
        .config-input select {
            width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #30363d;
            background: #0d1117;
            color: #e6edf3;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .config-input input:focus,
        .config-input select:focus {
            outline: none;
            border-color: #c9a84c;
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.15);
        }
        
        .config-input input[type="checkbox"] {
            width: auto;
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #c9a84c;
        }
        
        .config-boolean {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .config-boolean span {
            color: #8b949e;
            font-size: 0.9rem;
        }
        

        
        /* DÍAS Y FECHAS                                      */

        
        
        .dias-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .dia-item {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.03);
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.3s;
            color: #8b949e;
        }
        
        .dia-item:hover {
            background: rgba(201, 168, 76, 0.05);
            border-color: rgba(201, 168, 76, 0.2);
        }
        
        .dia-item input {
            margin: 0;
            accent-color: #c9a84c;
        }
        
        .dia-item input:checked + span {
            color: #c9a84c;
        }
        
        .fecha-item {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.03);
            padding: 6px 12px;
            border-radius: 8px;
            margin: 4px;
            border: 1px solid #30363d;
        }
        
        .fecha-item span {
            color: #e6edf3;
            font-size: 0.9rem;
        }
        
        .btn-eliminar-fecha {
            background: #da3633;
            border: none;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .btn-eliminar-fecha:hover {
            background: #f85149;
            transform: scale(1.1);
        }
        
        .btn-agregar-fecha {
            background: #238636;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-agregar-fecha:hover {
            background: #2ea043;
            transform: translateY(-1px);
        }
        

        
        /* BOTÓN GUARDAR                                     */

        
        
        .btn-guardar-config {
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
            padding: 12px 32px;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-guardar-config:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
        }
        
        .config-footer {
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #30363d;
        }
        

        
        /* INFO SISTEMA                                      */

        
        
        .info-sistema {
            margin-top: 30px;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 10px;
            border: 1px solid #30363d;
        }
        
        .info-sistema strong {
            color: #c9a84c;
        }
        
        .info-sistema .row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 8px;
        }
        
        .info-sistema .item {
            color: #8b949e;
            font-size: 0.85rem;
        }
        
        .info-sistema .item span {
            color: #e6edf3;
        }
        

        
        /* RESPONSIVE                                          */

        
        
        @media (max-width: 768px) {
            .config-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .config-label {
                min-width: auto;
            }
            
            .config-input {
                min-width: auto;
            }
            
            .header-contenido {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .nav-header {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .config-footer {
                text-align: center;
            }
            
            .btn-guardar-config {
                width: 100%;
                justify-content: center;
            }
            
            .dias-selector {
                gap: 4px;
            }
            
            .dia-item {
                padding: 4px 8px;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .contenedor-panel h2 {
                font-size: 1.4rem;
            }
            
            .fecha-item {
                font-size: 0.8rem;
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
            <a href="home.php" class="btn-outline-sm"><i class="fas fa-arrow-left"></i> Panel</a>
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2><i class="fas fa-cog"></i> Configuración del Sistema</h2>
    
    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <?php 
        $iconos_grupo = [
            'horarios' => 'fa-clock',
            'notificaciones' => 'fa-bell',
            'reservas' => 'fa-calendar-check',
            'apariencia' => 'fa-palette',
            'general' => 'fa-cogs'
        ];
        
        $titulos_grupo = [
            'horarios' => 'Horarios',
            'notificaciones' => 'Notificaciones',
            'reservas' => 'Reservas',
            'apariencia' => 'Apariencia',
            'general' => 'General'
        ];
        
        foreach ($configuraciones as $grupo => $items): 
        ?>
            <div class="config-grupo">
                <div class="config-grupo-header">
                    <i class="fas <?= $iconos_grupo[$grupo] ?? 'fa-cog' ?>"></i>
                    <h3><?= $titulos_grupo[$grupo] ?? ucfirst($grupo) ?></h3>
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
                                    <input type="number" name="cfg_<?= $clave ?>" value="<?= htmlspecialchars($valor) ?>" min="0">
                                <?php elseif ($item['tipo'] === 'horario'): ?>
                                    <input type="time" name="cfg_<?= $clave ?>" value="<?= htmlspecialchars($valor) ?>">
                                <?php elseif ($clave === 'dias_no_laborables'): ?>
                                    <div id="dias-no-laborables-container">
                                        <?php foreach ($dias_no_laborables as $fecha): ?>
                                            <div class="fecha-item">
                                                <span><?= date('d/m/Y', strtotime($fecha)) ?></span>
                                                <input type="hidden" name="dias_no_laborables_array[]" value="<?= $fecha ?>">
                                                <button type="button" class="btn-eliminar-fecha" onclick="eliminarFecha(this)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn-agregar-fecha" onclick="agregarFecha()">
                                        <i class="fas fa-plus"></i> Agregar día no laborable
                                    </button>
                                <?php elseif ($clave === 'dias_semana_laborales'): ?>
                                    <div class="dias-selector">
                                        <?php 
                                        $dias_nombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                                        for ($i = 1; $i <= 7; $i++): 
                                            $checked = in_array($i, $dias_laborables);
                                        ?>
                                            <label class="dia-item">
                                                <input type="checkbox" name="dias_laborables[]" value="<?= $i ?>" <?= $checked ? 'checked' : '' ?>>
                                                <span><?= $dias_nombres[$i-1] ?></span>
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
        
        <div class="config-footer">
            <button type="submit" name="guardar_config" class="btn-guardar-config">
                <i class="fas fa-save"></i> Guardar toda la configuración
            </button>
        </div>
    </form>
    
    <div class="info-sistema">
        <strong><i class="fas fa-info-circle"></i> Información del Sistema</strong>
        <div class="row">
            <div class="item"><i class="fas fa-code"></i> PHP: <span><?= phpversion() ?></span></div>
            <div class="item"><i class="fas fa-server"></i> Servidor: <span><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido' ?></span></div>
            <div class="item"><i class="fas fa-database"></i> Base de Datos: <span>MySQL / MariaDB</span></div>
            <div class="item"><i class="fas fa-clock"></i> Zona Horaria: <span><?= date_default_timezone_get() ?></span></div>
            <div class="item"><i class="fas fa-calendar-alt"></i> Fecha/Hora: <span><?= date('d/m/Y H:i:s') ?></span></div>
        </div>
    </div>
</main>

<footer style="text-align: center; padding: 30px 20px 20px; border-top: 1px solid #30363d; margin-top: 40px; color: #8b949e;">
    <p>&copy; <?= date('Y') ?> <span style="color: #c9a84c; font-weight: 600;">SalonHub</span> — Configuración del Sistema</p>
    <p style="font-size:0.75rem;margin-top:4px;">
        <i class="fas fa-cut"></i> El poder de la transformación
    </p>
</footer>

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
            <button type="button" class="btn-eliminar-fecha" onclick="eliminarFecha(this)">
                <i class="fas fa-times"></i>
            </button>
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