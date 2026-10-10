<?php
// Marcar todas las notificaciones como leídas

require_once __DIR__ . '/../permisos_helper.php';

header('Content-Type: application/json');

if (!esta_logueado()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$rol = $_SESSION['rol'];

$pdo = conectar();
$stmt = $pdo->prepare("
    UPDATE notificaciones 
    SET leido = 1 
    WHERE leido = 0 
      AND (id_usuario = ? OR (id_usuario IS NULL AND rol_destino = ?))
");
$success = $stmt->execute([$id_usuario, $rol]);

echo json_encode(['success' => $success]);