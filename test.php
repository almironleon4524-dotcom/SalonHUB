<?php
echo "1. PHP funciona correctamente<br>";

session_start();
echo "2. Session iniciada<br>";

require_once __DIR__ . '/conexion.php';
echo "3. conexion.php incluido<br>";

$pdo = conectar();
echo "4. Conexión a BD exitosa<br>";

$stmt = $pdo->query("SELECT 1 as test");
$resultado = $stmt->fetch();
echo "5. Consulta a BD exitosa: " . $resultado['test'] . "<br>";

echo "6. TODO FUNCIONA CORRECTAMENTE!";
?>
