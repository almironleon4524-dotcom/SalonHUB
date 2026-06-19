<?php
// ============================================================
//  SalonHub - Index
// ============================================================

session_start();

if (isset($_SESSION['id_usuario']) || isset($_SESSION['usuario_id'])) {
    header('Location: home.php');
    exit;
} 

header('Location: login.php');
exit;
?>
