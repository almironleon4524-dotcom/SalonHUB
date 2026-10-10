<?php
// ============================================================
//  SalonHub - AJAX: Verificar si el nombre de usuario existe
//  Ruta: /ajax/verificar_usuario.php
//  Método: POST  |  Parámetro: usuario
// ============================================================

header('Content-Type: application/json; charset=UTF-8');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

require_once __DIR__ . '/../conexion.php';

$usuario = trim($_POST['usuario'] ?? '');

// Validación básica
if (empty($usuario)) {
    echo json_encode(['disponible' => false, 'mensaje' => 'Ingresá un nombre de usuario.']);
    exit;
}

if (strlen($usuario) < 4) {
    echo json_encode(['disponible' => false, 'mensaje' => 'Mínimo 4 caracteres.']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9._]+$/', $usuario)) {
    echo json_encode(['disponible' => false, 'mensaje' => 'Solo letras, números, puntos y guiones bajos.']);
    exit;
}

try {
    $pdo  = conectar();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE usuario = :usuario");
    $stmt->execute([':usuario' => $usuario]);
    $existe = (int)$stmt->fetchColumn() > 0;

    echo json_encode([
        'disponible' => !$existe,
        'mensaje'    => $existe ? 'Ese nombre de usuario ya está en uso.' : 'Nombre de usuario disponible ✓'
    ]);

} catch (PDOException $e) {
    error_log("AJAX verificar_usuario: " . $e->getMessage());
    echo json_encode(['disponible' => false, 'mensaje' => 'Error del servidor.']);
}
