<?php
// ============================================================
//   SalonHub - Helper de Permisos (Versión Definitiva InfinityFree)
// ============================================================

require_once __DIR__ . '/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Limpia un JSON que pudo haberse corrompido al importar la BD en InfinityFree
 */
function limpiar_json_infinityfree($json_string) {
    if (empty($json_string)) return null;
    // Remueve las barras invertidas que agrega phpMyAdmin al importar textos largos
    $limpio = stripslashes($json_string);
    // Asegura que empiece y termine con llaves, si tiene comillas dobles extrañas a los lados
    $limpio = trim($limpio, '"'); 
    return json_decode($limpio, true);
}

/**
 * Verifica si un usuario tiene un permiso específico.
 */
function tiene_permiso(string $permiso, ?int $id_usuario = null): bool {
    if ($id_usuario === null) {
        if (empty($_SESSION['id_usuario'])) {
            return false;
        }
        $id_usuario = (int)$_SESSION['id_usuario'];
    }

    try {
        $pdo = conectar();
        $stmt = $pdo->prepare("SELECT rol, permisos FROM usuario WHERE id_usuario = :id");
        $stmt->execute([':id' => $id_usuario]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return false;
        }

        // El administrador siempre tiene acceso total
        if ($usuario['rol'] === 'administrador') {
            return true;
        }

        // Permisos PERSONALES (columna `permisos`, formato JSON)
        if (!empty($usuario['permisos'])) {
            // Usamos nuestra nueva función limpiadora
            $personales = limpiar_json_infinityfree($usuario['permisos']);
            
            if (is_array($personales) && array_key_exists($permiso, $personales)) {
                return (bool)$personales[$permiso];
            }
        }

        // Si el JSON falla o no existe, intentamos con la tabla rol_permisos
        $stmtRol = $pdo->prepare("SELECT concedido FROM rol_permisos WHERE rol = :rol AND nombre_permiso = :permiso");
        $stmtRol->execute([':rol' => $usuario['rol'], ':permiso' => $permiso]);
        $fila = $stmtRol->fetch();

        return $fila ? (bool)$fila['concedido'] : false;

    } catch (PDOException $e) {
        error_log("Error en tiene_permiso(): " . $e->getMessage());
        return false;
    }
}

/**
 * Redirige al login si no hay sesión activa.
 */
function requerir_login(): void {
    if (empty($_SESSION['id_usuario'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Exige que el usuario esté logueado Y tenga un permiso específico.
 */
function requerir_permiso(string $permiso): void {
    requerir_login();

    if (!tiene_permiso($permiso)) {
        http_response_code(403);
        die('
        <div style="font-family:sans-serif;text-align:center;margin-top:80px;">
            <h2>⛔ Acceso denegado</h2>
            <p>No tenés permiso para acceder a esta sección.</p>
            <a href="home.php">← Volver al inicio</a>
        </div>');
    }
}

/**
 * Devuelve TODOS los permisos de un usuario como array asociativo.
 */
function obtener_todos_permisos(int $id_usuario): array {
    try {
        $pdo = conectar();
        $stmt = $pdo->prepare("SELECT rol, permisos FROM usuario WHERE id_usuario = :id");
        $stmt->execute([':id' => $id_usuario]);
        $usuario = $stmt->fetch();

        if (!$usuario) return [];

        $rol = $usuario['rol'];
        $resultado = [];

        if ($rol === 'administrador') {
            $stmtTodos = $pdo->query("SELECT nombre_permiso FROM permisos WHERE estado = 'activo'");
            foreach ($stmtTodos->fetchAll() as $fila) {
                $resultado[$fila['nombre_permiso']] = true;
            }
        }

        $stmtRol = $pdo->prepare("SELECT nombre_permiso, concedido FROM rol_permisos WHERE rol = :rol");
        $stmtRol->execute([':rol' => $rol]);
        foreach ($stmtRol->fetchAll() as $fila) {
            $resultado[$fila['nombre_permiso']] = (bool)$fila['concedido'];
        }

        if (!empty($usuario['permisos'])) {
            $personales = limpiar_json_infinityfree($usuario['permisos']);
            if (is_array($personales)) {
                foreach ($personales as $nombre => $valor) {
                    $resultado[$nombre] = (bool)$valor;
                }
            }
        }

        return $resultado;

    } catch (PDOException $e) {
        error_log("Error en obtener_todos_permisos(): " . $e->getMessage());
        return [];
    }
}
?>