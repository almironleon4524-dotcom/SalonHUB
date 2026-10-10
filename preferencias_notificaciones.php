<?php
// Preferencias de Notificaciones

session_start();
require_once __DIR__ . '/permisos_helper.php';
require_once __DIR__ . '/notificaciones_helper.php';

requerir_login();

$logueado = esta_logueado();
$rol = $_SESSION['rol'] ?? 'invitado';
$usuario = $_SESSION['usuario'] ?? 'Invitado';
$id_usuario = $_SESSION['id_usuario'];

$pdo = conectar();
$mensaje = '';
$error = '';

// Catálogo de notificaciones por rol
$catalogo = [
    'cliente' => [
        'citas' => [
            'titulo' => 'Mis Citas',
            'icono' => 'fa-calendar-alt',
            'items' => [
                'cita_creada' => ['titulo' => 'Cita creada', 'desc' => 'Cuando reservás un turno', 'critica' => false],
                'cita_confirmada' => ['titulo' => 'Cita confirmada', 'desc' => 'Cuando el salón acepta tu reserva', 'critica' => false],
                'cita_rechazada' => ['titulo' => 'Cita rechazada', 'desc' => 'Cuando el salón no puede atenderte', 'critica' => true],
                'cita_reagendada' => ['titulo' => 'Cita reagendada', 'desc' => 'Cuando cambian tu turno', 'critica' => true],
                'cita_cancelada' => ['titulo' => 'Cita cancelada por el salón', 'desc' => 'Cuando el salón cancela tu turno', 'critica' => true],
                'cita_asignada_otro' => ['titulo' => 'Cambio de profesional', 'desc' => 'Cuando te asignan otro empleado', 'critica' => false],
                'cita_recordatorio' => ['titulo' => 'Recordatorio de cita', 'desc' => '24 horas antes de tu cita', 'critica' => false],
            ]
        ],
        'caja' => [
            'titulo' => 'Mis Pagos',
            'icono' => 'fa-money-bill',
            'items' => [
                'sena_solicitada' => ['titulo' => 'Seña solicitada', 'desc' => 'Cuando debés pagar la seña', 'critica' => true],
                'sena_acreditada' => ['titulo' => 'Seña acreditada', 'desc' => 'Cuando se confirma tu pago', 'critica' => false],
                'sena_devuelta' => ['titulo' => 'Seña devuelta', 'desc' => 'Cuando te devuelven la seña', 'critica' => false],
                'saldo_pendiente' => ['titulo' => 'Saldo pendiente', 'desc' => 'Cuando te falta pagar el resto', 'critica' => true],
            ]
        ],
        'marketing' => [
            'titulo' => 'Promociones',
            'icono' => 'fa-tag',
            'items' => [
                'promocion_nueva' => ['titulo' => 'Nueva promoción', 'desc' => 'Cuando hay descuentos disponibles', 'critica' => false],
                'cumpleanos' => ['titulo' => 'Cumpleaños', 'desc' => 'Saludo y descuento especial', 'critica' => false],
            ]
        ],
    ],

    'empleado' => [
        'agenda' => [
            'titulo' => 'Mi Agenda',
            'icono' => 'fa-calendar-check',
            'items' => [
                'nueva_cita_asignada' => ['titulo' => 'Nueva cita asignada', 'desc' => 'Cuando te asignan un turno', 'critica' => true],
                'cita_cancelada_cliente' => ['titulo' => 'Cita cancelada por cliente', 'desc' => 'Cuando un cliente cancela', 'critica' => true],
                'cita_cancelada_salon' => ['titulo' => 'Cita cancelada por el salón', 'desc' => 'Cuando el salón cancela', 'critica' => true],
                'cita_reagendada' => ['titulo' => 'Cita reagendada', 'desc' => 'Cuando cambian una cita tuya', 'critica' => true],
                'cita_editada' => ['titulo' => 'Cita editada', 'desc' => 'Cuando el admin modifica una cita', 'critica' => false],
                'cita_asignada_otro' => ['titulo' => 'Cita reasignada', 'desc' => 'Cuando te quitan una cita', 'critica' => true],
                'cita_recordatorio' => ['titulo' => 'Recordatorio', 'desc' => '24 horas antes', 'critica' => false],
                'no_asistio' => ['titulo' => 'Cliente no asistió', 'desc' => 'Cuando no vino un cliente', 'critica' => false],
                'cita_completada' => ['titulo' => 'Cita completada', 'desc' => 'Registro de trabajo', 'critica' => false],
            ]
        ],
        'avisos' => [
            'titulo' => 'Avisos del Salón',
            'icono' => 'fa-bullhorn',
            'items' => [
                'feriado' => ['titulo' => 'Feriado próximo', 'desc' => 'Cuando hay un feriado', 'critica' => true],
                'cambio_horario_salon' => ['titulo' => 'Cambio de horario del salón', 'desc' => 'Cuando cambia el horario', 'critica' => true],
                'cambio_horario_propio' => ['titulo' => 'Cambio de mi horario', 'desc' => 'Cuando te cambian el horario', 'critica' => false],
                'vacaciones_aprobadas' => ['titulo' => 'Vacaciones aprobadas', 'desc' => 'Cuando aprueban tus vacaciones', 'critica' => true],
                'vacaciones_rechazadas' => ['titulo' => 'Vacaciones rechazadas', 'desc' => 'Cuando rechazan tus vacaciones', 'critica' => true],
                'cambio_especialidad' => ['titulo' => 'Cambio de especialidad', 'desc' => 'Cuando cambia tu especialidad', 'critica' => false],
            ]
        ],
        'insumos' => [
            'titulo' => 'Insumos',
            'icono' => 'fa-box',
            'items' => [
                'stock_bajo_insumo' => ['titulo' => 'Stock bajo', 'desc' => 'Cuando falta insumo', 'critica' => false],
                'insumo_agotado' => ['titulo' => 'Insumo agotado', 'desc' => 'Cuando no hay insumo', 'critica' => true],
                'nuevo_servicio' => ['titulo' => 'Nuevo servicio', 'desc' => 'Cuando agregan un servicio', 'critica' => false],
            ]
        ],
    ],

    'administrador' => [
        'inventario' => [
            'titulo' => 'Inventario',
            'icono' => 'fa-boxes',
            'items' => [
                'stock_agotado' => ['titulo' => 'Stock agotado', 'desc' => 'Producto en cero', 'critica' => true],
                'stock_bajo' => ['titulo' => 'Stock bajo', 'desc' => 'Producto en mínimo', 'critica' => false],
                'stock_repuesto' => ['titulo' => 'Stock repuesto', 'desc' => 'Producto reabastecido', 'critica' => false],
                'producto_editado' => ['titulo' => 'Producto editado', 'desc' => 'Alerta anti-robo', 'critica' => true],
                'producto_eliminado' => ['titulo' => 'Producto eliminado', 'desc' => 'Alerta anti-robo', 'critica' => true],
                'producto_roto' => ['titulo' => 'Producto roto', 'desc' => 'Se rompió en el local', 'critica' => false],
                'producto_vencido' => ['titulo' => 'Producto vencido', 'desc' => 'Se venció', 'critica' => false],
                'producto_por_vencer' => ['titulo' => 'Producto por vencer', 'desc' => 'Vence pronto', 'critica' => true],
                'movimiento_grande' => ['titulo' => 'Movimiento grande de stock', 'desc' => 'Movimiento inusual', 'critica' => false],
                'entrada_mercaderia' => ['titulo' => 'Entrada de mercadería', 'desc' => 'Llegó mercadería', 'critica' => false],
                'producto_creado' => ['titulo' => 'Producto creado', 'desc' => 'Nuevo producto', 'critica' => false],
            ]
        ],
        'citas' => [
            'titulo' => 'Citas',
            'icono' => 'fa-calendar-alt',
            'items' => [
                'muchas_cancelaciones' => ['titulo' => 'Muchas cancelaciones', 'desc' => 'Alerta de cancelaciones', 'critica' => false],
                'muchas_inasistencias' => ['titulo' => 'Muchas inasistencias', 'desc' => 'Alerta de ausencias', 'critica' => false],
                'cita_editada_empleado' => ['titulo' => 'Cita editada por empleado', 'desc' => 'Control de cambios', 'critica' => false],
                'cita_eliminada' => ['titulo' => 'Cita eliminada', 'desc' => 'Control de cambios', 'critica' => true],
            ]
        ],
        'caja' => [
            'titulo' => 'Caja y Ventas',
            'icono' => 'fa-cash-register',
            'items' => [
                'diferencia_caja' => ['titulo' => 'Diferencia en cierre', 'desc' => 'Caja no cuadra', 'critica' => true],
                'venta_cancelada' => ['titulo' => 'Venta cancelada', 'desc' => 'Se anuló una venta', 'critica' => false],
                'pago_pendiente' => ['titulo' => 'Pago pendiente', 'desc' => 'Pago sin cobrar', 'critica' => false],
                'venta_grande' => ['titulo' => 'Venta grande', 'desc' => 'Venta inusual', 'critica' => false],
            ]
        ],
        'clientes' => [
            'titulo' => 'Clientes',
            'icono' => 'fa-users',
            'items' => [
                'nuevo_cliente' => ['titulo' => 'Nuevo cliente', 'desc' => 'Se registró un cliente', 'critica' => false],
                'cliente_frecuente' => ['titulo' => 'Cliente frecuente', 'desc' => 'Cliente con muchas visitas', 'critica' => false],
                'cliente_inactivo' => ['titulo' => 'Cliente inactivo', 'desc' => 'Cliente que no viene hace tiempo', 'critica' => false],
            ]
        ],
        'usuarios' => [
            'titulo' => 'Usuarios y Empleados',
            'icono' => 'fa-user-cog',
            'items' => [
                'nuevo_usuario' => ['titulo' => 'Nuevo usuario', 'desc' => 'Se registró un usuario', 'critica' => false],
                'cambio_rol' => ['titulo' => 'Cambio de rol', 'desc' => 'Alerta de seguridad', 'critica' => true],
                'cuenta_bloqueada' => ['titulo' => 'Cuenta bloqueada', 'desc' => 'Cuenta bloqueada por intentos', 'critica' => true],
                'login_sospechoso' => ['titulo' => 'Login sospechoso', 'desc' => 'Múltiples intentos fallidos', 'critica' => true],
                'nuevo_empleado' => ['titulo' => 'Nuevo empleado', 'desc' => 'Se agregó un empleado', 'critica' => false],
                'solicitud_vacaciones' => ['titulo' => 'Solicitud de vacaciones', 'desc' => 'Empleado pide vacaciones', 'critica' => false],
                'empleado_enfermo' => ['titulo' => 'Empleado enfermo', 'desc' => 'Empleado avisa que no viene', 'critica' => true],
                'cambio_horario_empleado' => ['titulo' => 'Cambio de horario', 'desc' => 'Empleado cambió horario', 'critica' => false],
            ]
        ],
        'sistema' => [
            'titulo' => 'Sistema',
            'icono' => 'fa-server',
            'items' => [
                'backup_completado' => ['titulo' => 'Backup completado', 'desc' => 'Copia de seguridad exitosa', 'critica' => false],
                'backup_fallido' => ['titulo' => 'Backup fallido', 'desc' => 'Error en la copia de seguridad', 'critica' => true],
                'error_sistema' => ['titulo' => 'Error del sistema', 'desc' => 'Error crítico', 'critica' => true],
                'espacio_bajo' => ['titulo' => 'Espacio en disco bajo', 'desc' => 'Poco espacio disponible', 'critica' => false],
                'bd_con_errores' => ['titulo' => 'Base de datos con errores', 'desc' => 'Problemas en la BD', 'critica' => true],
            ]
        ],
    ],
];

// Guardar preferencias
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Obtener todos los tipos del rol
        $tipos_disponibles = [];
        foreach ($catalogo[$rol] ?? [] as $categoria) {
            foreach ($categoria['items'] as $tipo => $info) {
                $tipos_disponibles[$tipo] = $info;
            }
        }

        $marcados = $_POST['notificaciones'] ?? [];

        foreach ($tipos_disponibles as $tipo => $info) {
            // Las críticas siempre activas
            $recibir = $info['critica'] ? 1 : (in_array($tipo, $marcados) ? 1 : 0);

            $stmt = $pdo->prepare("
                INSERT INTO notificacion_preferencias (id_usuario, tipo_notificacion, recibir) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE recibir = VALUES(recibir)
            ");
            $stmt->execute([$id_usuario, $tipo, $recibir]);
        }

        $mensaje = 'Preferencias guardadas correctamente.';

    } catch (PDOException $e) {
        $error = 'Error al guardar: ' . $e->getMessage();
    }
}

// Obtener preferencias actuales
$preferencias_actuales = [];
try {
    $stmt = $pdo->prepare("SELECT tipo_notificacion, recibir FROM notificacion_preferencias WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $preferencias_actuales[$row['tipo_notificacion']] = (int)$row['recibir'];
    }
} catch (PDOException $e) {}

$catalogo_rol = $catalogo[$rol] ?? [];

// Estadísticas
$total_tipos = 0;
$total_activos = 0;
$total_criticos = 0;

foreach ($catalogo_rol as $categoria) {
    foreach ($categoria['items'] as $tipo => $info) {
        $total_tipos++;
        if ($info['critica']) {
            $total_criticos++;
            $total_activos++;
        } else {
            $estado = isset($preferencias_actuales[$tipo]) ? $preferencias_actuales[$tipo] : 1;
            if ($estado) $total_activos++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalonHub - Preferencias de Notificaciones</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #0d1117; color: #e6edf3; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; }
        
        .header-panel { background: #161b22; border-bottom: 1px solid #30363d; padding: 0 20px; position: sticky; top: 0; z-index: 100; }
        .header-contenido { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; padding: 12px 0; }
        .logo-header a { font-size: 1.5rem; font-weight: 700; color: #c9a84c; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .logo-header a i { font-size: 1.8rem; }
        .nav-header { display: flex; align-items: center; gap: 16px; }
        .usuario-info { color: #8b949e; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .usuario-info i { font-size: 1.1rem; color: #c9a84c; }
        .badge-rol { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; }
        .badge-admin { background: #c9a84c; color: #0d1117; }
        .badge-empleado { background: #0f5c45; color: white; }
        .badge-cliente { background: #4a5568; color: white; }
        .btn-outline-sm { padding: 6px 16px; border-radius: 8px; font-size: 0.8rem; text-decoration: none; transition: all 0.3s; background: transparent; border: 1px solid #30363d; color: #8b949e; }
        .btn-outline-sm:hover { border-color: #c9a84c; color: #c9a84c; }
        .btn-danger-outline:hover { border-color: #e74c3c; color: #e74c3c; }
        
        .contenedor-panel { max-width: 900px; margin: 0 auto; padding: 20px; }
        .contenedor-panel h2 { color: #e6edf3; font-size: 1.8rem; margin-bottom: 24px; border-bottom: 2px solid #30363d; padding-bottom: 12px; }
        .contenedor-panel h2 i { color: #c9a84c; margin-right: 10px; }
        
        .alerta { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alerta-exito { background: rgba(46,160,67,0.15); border: 1px solid #238636; color: #3fb950; }
        .alerta-error { background: rgba(248,81,73,0.15); border: 1px solid #da3633; color: #f85149; }
        
        .info-box {
            background: rgba(88,166,255,0.08);
            border: 1px solid rgba(88,166,255,0.3);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .info-box i { color: #58a6ff; font-size: 1.2rem; margin-top: 2px; }
        .info-box p { color: #8b949e; font-size: 0.85rem; line-height: 1.5; margin: 0; }
        .info-box strong { color: #e6edf3; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 16px 20px; text-align: center; }
        .stat-card .numero { font-size: 2rem; font-weight: 700; color: #c9a84c; }
        .stat-card .etiqueta { color: #8b949e; font-size: 0.8rem; margin-top: 4px; }
        
        .categoria {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .categoria-header {
            background: rgba(201, 168, 76, 0.08);
            padding: 14px 20px;
            border-bottom: 1px solid #30363d;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .categoria-header i { color: #c9a84c; font-size: 1.1rem; }
        .categoria-header h3 {
            color: #c9a84c;
            font-size: 1rem;
            margin: 0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .categoria-body { padding: 8px 0; }
        
        .item-preferencia {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            transition: background 0.3s;
        }
        .item-preferencia:last-child { border-bottom: none; }
        .item-preferencia:hover { background: rgba(255, 255, 255, 0.02); }
        
        .item-info { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
        
        .item-texto { flex: 1; min-width: 0; }
        .item-titulo {
            color: #e6edf3;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .item-desc { color: #8b949e; font-size: 0.75rem; }
        
        .badge-obligatoria {
            background: rgba(248, 81, 73, 0.15);
            color: #f85149;
            border: 1px solid rgba(248, 81, 73, 0.3);
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
            flex-shrink: 0;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #30363d;
            border-radius: 24px;
            transition: 0.3s;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: #8b949e;
            border-radius: 50%;
            transition: 0.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background: rgba(201, 168, 76, 0.3); }
        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(22px);
            background: #c9a84c;
        }
        .toggle-switch input:disabled + .toggle-slider { opacity: 0.5; cursor: not-allowed; }
        .toggle-switch input:disabled + .toggle-slider::before { background: #f85149; }
        
        .acciones-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 20px 0;
            position: sticky;
            bottom: 0;
            background: linear-gradient(180deg, transparent, #0d1117 30%);
            padding-top: 30px;
            margin-top: 20px;
        }
        
        .btn-primario {
            background: linear-gradient(135deg, #c9a84c, #b8943a);
            color: #0d1117;
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primario:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.3);
        }
        
        .btn-secundario {
            background: transparent;
            border: 1px solid #30363d;
            color: #8b949e;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-secundario:hover { border-color: #8b949e; color: #e6edf3; }
        
        .sin-catalogo {
            text-align: center;
            padding: 60px 20px;
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            color: #8b949e;
        }
        .sin-catalogo i { font-size: 3rem; color: #30363d; margin-bottom: 16px; display: block; }
        
        .footer-panel { text-align: center; padding: 30px 20px 20px; border-top: 1px solid #30363d; margin-top: 40px; color: #8b949e; }
        .footer-panel span { color: #c9a84c; font-weight: 600; }
        
        @media (max-width: 768px) {
            .header-contenido { flex-wrap: wrap; gap: 10px; }
            .nav-header { flex-wrap: wrap; gap: 8px; }
            .contenedor-panel h2 { font-size: 1.4rem; }
            .item-preferencia { flex-direction: column; align-items: flex-start; gap: 12px; }
            .toggle-switch { align-self: flex-end; }
            .acciones-bar { flex-direction: column; }
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
                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($usuario) ?>
                <span class="badge-rol badge-<?= $rol ?>"><?= ucfirst($rol) ?></span>
            </span>
            <a href="home.php" class="btn-outline-sm"><i class="fas fa-arrow-left"></i> Inicio</a>
            <a href="notificaciones.php" class="btn-outline-sm"><i class="fas fa-bell"></i> Notificaciones</a>
            <a href="cerrar_sesion.php" class="btn-outline-sm btn-danger-outline"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </div>
</header>

<main class="contenedor-panel">
    <h2><i class="fas fa-cog"></i> Preferencias de Notificaciones</h2>

    <?php if ($mensaje): ?>
        <div class="alerta alerta-exito"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <p>
            <strong>Elegí qué notificaciones querés recibir.</strong> Las marcadas como <span class="badge-obligatoria">Obligatoria</span> no se pueden desactivar porque son críticas para tu trabajo o tu reserva.
        </p>
    </div>

    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="numero"><?= $total_tipos ?></div>
            <div class="etiqueta">Tipos disponibles</div>
        </div>
        <div class="stat-card">
            <div class="numero" style="color: #3fb950;"><?= $total_activos ?></div>
            <div class="etiqueta">Activas</div>
        </div>
        <div class="stat-card">
            <div class="numero" style="color: #f85149;"><?= $total_criticos ?></div>
            <div class="etiqueta">Obligatorias</div>
        </div>
        <div class="stat-card">
            <div class="numero" style="color: #8b949e;"><?= $total_tipos - $total_activos ?></div>
            <div class="etiqueta">Desactivadas</div>
        </div>
    </div>

    <!-- Formulario -->
    <?php if (empty($catalogo_rol)): ?>
        <div class="sin-catalogo">
            <i class="fas fa-inbox"></i>
            <h3>No hay notificaciones disponibles</h3>
            <p>No hay tipos de notificaciones configurados para tu rol.</p>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <?php foreach ($catalogo_rol as $cat_key => $categoria): ?>
                <div class="categoria">
                    <div class="categoria-header">
                        <i class="fas <?= $categoria['icono'] ?>"></i>
                        <h3><?= htmlspecialchars($categoria['titulo']) ?></h3>
                    </div>
                    <div class="categoria-body">
                        <?php foreach ($categoria['items'] as $tipo => $info):
                            // Estado actual: si es crítica, siempre activa; si no, según preferencias
                            if ($info['critica']) {
                                $checked = true;
                                $disabled = true;
                            } else {
                                $checked = isset($preferencias_actuales[$tipo]) ? ($preferencias_actuales[$tipo] == 1) : true;
                                $disabled = false;
                            }
                        ?>
                            <div class="item-preferencia">
                                <div class="item-info">
                                    <div class="item-texto">
                                        <div class="item-titulo">
                                            <?= htmlspecialchars($info['titulo']) ?>
                                            <?php if ($info['critica']): ?>
                                                <span class="badge-obligatoria">Obligatoria</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-desc"><?= htmlspecialchars($info['desc']) ?></div>
                                    </div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           name="notificaciones[]"
                                           value="<?= $tipo ?>"
                                           <?= $checked ? 'checked' : '' ?>
                                           <?= $disabled ? 'disabled' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="acciones-bar">
                <a href="notificaciones.php" class="btn-secundario">
                    <i class="fas fa-arrow-left"></i> Volver a Notificaciones
                </a>
                <button type="submit" class="btn-primario">
                    <i class="fas fa-save"></i> Guardar Preferencias
                </button>
            </div>
        </form>
    <?php endif; ?>

</main>

<footer class="footer-panel">
    <p>&copy; <?= date('Y') ?> <span>SalonHub</span> — Preferencias de Notificaciones</p>
</footer>

</body>
</html>