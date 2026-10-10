<?php
// Helper de Notificaciones

require_once __DIR__ . '/conexion.php';

// Crear notificación (con anti-spam y preferencias)
function crear_notificacion($tipo, $titulo, $mensaje, $datos = []) {
    try {
        $pdo = conectar();
        
        $id_usuario    = $datos['id_usuario'] ?? null;
        $rol_destino   = $datos['rol_destino'] ?? null;
        $prioridad     = $datos['prioridad'] ?? 'media';
        $modulo        = $datos['modulo'] ?? 'sistema';
        $url_accion    = $datos['url_accion'] ?? null;
        $id_referencia = $datos['id_referencia'] ?? null;
        $tabla_ref     = $datos['tabla_referencia'] ?? null;

        // Anti-spam: verificar duplicados no leídos
        if ($id_referencia && $tabla_ref) {
            $check = $pdo->prepare("
                SELECT COUNT(*) FROM notificaciones 
                WHERE tipo = ? AND id_referencia = ? AND tabla_referencia = ? AND leido = 0
            ");
            $check->execute([$tipo, $id_referencia, $tabla_ref]);
            
            if ($check->fetchColumn() > 0) {
                return false;
            }
        }

        // Verificar preferencias del usuario (si es usuario específico)
        if ($id_usuario) {
            $check_pref = $pdo->prepare("
                SELECT recibir FROM notificacion_preferencias 
                WHERE id_usuario = ? AND tipo_notificacion = ?
            ");
            $check_pref->execute([$id_usuario, $tipo]);
            $pref = $check_pref->fetchColumn();
            
            // Si tiene preferencia guardada y es 0, no enviar
            if ($pref !== false && $pref == 0) {
                return false;
            }
        }

        // Insertar notificación
        $stmt = $pdo->prepare("
            INSERT INTO notificaciones 
            (id_usuario, rol_destino, titulo, tipo, prioridad, mensaje, modulo, url_accion, id_referencia, tabla_referencia, leido)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        
        return $stmt->execute([
            $id_usuario, $rol_destino, $titulo, $tipo, $prioridad,
            $mensaje, $modulo, $url_accion, $id_referencia, $tabla_ref
        ]);
        
    } catch (PDOException $e) {
        error_log("Error en crear_notificacion: " . $e->getMessage());
        return false;
    }
}

// Atajos
function notificar_admin($tipo, $titulo, $mensaje, $datos = []) {
    $datos['rol_destino'] = 'administrador';
    return crear_notificacion($tipo, $titulo, $mensaje, $datos);
}

function notificar_empleado($id_usuario_empleado, $tipo, $titulo, $mensaje, $datos = []) {
    $datos['id_usuario'] = $id_usuario_empleado;
    return crear_notificacion($tipo, $titulo, $mensaje, $datos);
}

function notificar_cliente($id_usuario_cliente, $tipo, $titulo, $mensaje, $datos = []) {
    $datos['id_usuario'] = $id_usuario_cliente;
    return crear_notificacion($tipo, $titulo, $mensaje, $datos);
}

// Notificaciones específicas de negocio

function notificar_stock_bajo($id_producto, $nombre_producto, $stock_actual) {
    return notificar_admin(
        'stock_bajo',
        'Stock Bajo',
        "El producto '{$nombre_producto}' alcanzó el stock mínimo ({$stock_actual} unidades).",
        [
            'prioridad' => 'alta',
            'modulo' => 'inventario',
            'url_accion' => "admin_productos.php?editar={$id_producto}",
            'id_referencia' => $id_producto,
            'tabla_referencia' => 'productos'
        ]
    );
}

function notificar_stock_agotado($id_producto, $nombre_producto) {
    return notificar_admin(
        'stock_agotado',
        'Stock Agotado',
        "El producto '{$nombre_producto}' se quedó sin stock.",
        [
            'prioridad' => 'critica',
            'modulo' => 'inventario',
            'url_accion' => "admin_productos.php?editar={$id_producto}",
            'id_referencia' => $id_producto,
            'tabla_referencia' => 'productos'
        ]
    );
}

function notificar_nueva_venta($id_venta, $total, $metodo) {
    return notificar_admin(
        'venta_realizada',
        'Nueva Venta',
        "Se registró una venta por $" . number_format($total, 2, ',', '.') . " con {$metodo}.",
        [
            'prioridad' => 'media',
            'modulo' => 'ventas',
            'url_accion' => "admin_ventas.php?id={$id_venta}",
            'id_referencia' => $id_venta,
            'tabla_referencia' => 'ventas'
        ]
    );
}

function notificar_diferencia_caja($monto_sistema, $monto_declarado, $diferencia) {
    return notificar_admin(
        'diferencia_caja',
        'Diferencia en Caja',
        "Se detectó una diferencia de $" . number_format($diferencia, 2, ',', '.') . " en el cierre de caja.",
        [
            'prioridad' => 'critica',
            'modulo' => 'caja',
            'url_accion' => "admin_cierre_caja.php"
        ]
    );
}

// Limpieza automática (1 vez por día)
function ejecutar_limpieza_notificaciones() {
    $hoy = date('Y-m-d');
    
    if (isset($_SESSION['ultima_limpieza_notif']) && $_SESSION['ultima_limpieza_notif'] === $hoy) {
        return;
    }

    try {
        $pdo = conectar();
        $pdo->exec("
            DELETE FROM notificaciones 
            WHERE (leido = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
               OR (prioridad = 'baja' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
        ");
        
        $_SESSION['ultima_limpieza_notif'] = $hoy;
    } catch (PDOException $e) {
        error_log("Error en limpieza: " . $e->getMessage());
    }
}