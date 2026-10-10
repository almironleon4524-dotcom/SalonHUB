<?php
// ============================================================
//  SalonHub - AJAX: Verificar si el email ya está registrado
//  Ruta: /ajax/verificar_email.php
//  Método: POST  |  Parámetro: email
// ============================================================

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

require_once __DIR__ . '/../conexion.php';

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['disponible' => false, 'mensaje' => 'Ingresá un email.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['disponible' => false, 'mensaje' => 'Formato de email inválido.']);
    exit;
}

try {
    $pdo  = conectar();
    // Verificar en tabla usuario Y en persona (para no duplicar)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM usuario
        WHERE email = :email
    ");
    $stmt->execute([':email' => $email]);
    $existe = (int)$stmt->fetchColumn() > 0;

    echo json_encode([
        'disponible' => !$existe,
        'mensaje'    => $existe ? 'Ese email ya está registrado.' : 'Email disponible ✓'
    ]);

} catch (PDOException $e) {
    error_log("AJAX verificar_email: " . $e->getMessage());
    echo json_encode(['disponible' => false, 'mensaje' => 'Error del servidor.']);
}
