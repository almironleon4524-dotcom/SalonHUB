<?php
// Obtener notificaciones del usuario

require_once __DIR__ . '/../permisos_helper.php';

header('Content-Type: application/json');

if (!esta_logueado()) {
    echo json_encode(['error' => 'No autorizado', 'notificaciones' => [], 'no_leidas' => 0]);
    exit;
}

$pdo = conectar();
$id_usuario = $_SESSION['id_usuario'];
$rol = $_SESSION['rol'];

// Obtener últimas 20 notificaciones (por usuario + por rol)
$stmt = $pdo->prepare("
    SELECT 
        id_notificacion, 
        titulo,
        tipo, 
        prioridad,
        modulo,
        mensaje, 
        url_accion, 
        leido, 
        created_at 
    FROM notificaciones 
    WHERE id_usuario = ? 
       OR (id_usuario IS NULL AND rol_destino = ?)
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$id_usuario, $rol]);
$notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar no leídas
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM notificaciones 
    WHERE leido = 0 
      AND (id_usuario = ? OR (id_usuario IS NULL AND rol_destino = ?))
");
$stmt->execute([$id_usuario, $rol]);
$no_leidas = (int)$stmt->fetchColumn();

// Obtener la prioridad más alta para el badge
$stmt = $pdo->prepare("
    SELECT prioridad 
    FROM notificaciones 
    WHERE leido = 0 
      AND (id_usuario = ? OR (id_usuario IS NULL AND rol_destino = ?))
    ORDER BY FIELD(prioridad, 'critica', 'alta', 'media', 'baja')
    LIMIT 1
");
$stmt->execute([$id_usuario, $rol]);
$prioridad_maxima = $stmt->fetchColumn() ?: 'baja';

echo json_encode([
    'notificaciones' => $notificaciones,
    'no_leidas' => $no_leidas,
    'prioridad_maxima' => $prioridad_maxima
]);