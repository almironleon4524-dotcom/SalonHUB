<?php
// Marcar una notificación como leída

require_once __DIR__ . '/../permisos_helper.php';

header('Content-Type: application/json');

if (!esta_logueado()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$id_notificacion = (int)($_POST['id_notificacion'] ?? 0);
$id_usuario = $_SESSION['id_usuario'];
$rol = $_SESSION['rol'];

if ($id_notificacion <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$pdo = conectar();
$stmt = $pdo->prepare("
    UPDATE notificaciones 
    SET leido = 1 
    WHERE id_notificacion = ? 
      AND (id_usuario = ? OR (id_usuario IS NULL AND rol_destino = ?))
");
$success = $stmt->execute([$id_notificacion, $id_usuario, $rol]);

echo json_encode(['success' => $success]);