<?php
// Permisos Helper

require_once __DIR__ . '/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Permisos por defecto según rol

function obtenerPermisosPorDefecto(string $rol): array {
    switch ($rol) {
        case 'administrador':
            return [
                'gestion_productos' => true,
                'gestion_proveedores' => true,
                'gestion_clientes' => true,
                'gestion_servicios' => true,
                'gestion_categorias' => true,
                'gestion_usuarios' => true,
                'gestion_empleados' => true,
                'gestion_citas' => true,
                'gestion_pagos' => true,
                'gestion_configuracion' => true,
                'cerrar_caja' => true,
                'ver_reportes' => true,
                'acceso_panel_admin' => true,
                'acceso_panel_empleado' => true,
                'reservar_citas' => true,
                'ver_mis_citas' => true,
                'cancelar_mis_citas' => true,
                'auto_gestion_citas' => true,
                'cambiar_estado_citas' => true,
                'ver_perfil' => true,
                'editar_perfil' => true,
                'ver_productos' => true,
                'ver_servicios' => true
            ];
            
        case 'empleado':
            return [
                'gestion_productos' => false,
                'gestion_proveedores' => false,
                'gestion_clientes' => false,
                'gestion_servicios' => false,
                'gestion_categorias' => false,
                'gestion_usuarios' => false,
                'gestion_empleados' => false,
                'gestion_citas' => false,
                'gestion_pagos' => false,
                'gestion_configuracion' => false,
                'cerrar_caja' => false,
                'ver_reportes' => false,
                'acceso_panel_admin' => false,
                'acceso_panel_empleado' => true,
                'reservar_citas' => true,
                'ver_mis_citas' => true,
                'cancelar_mis_citas' => true,
                'auto_gestion_citas' => true,
                'cambiar_estado_citas' => true,
                'ver_perfil' => true,
                'editar_perfil' => true,
                'ver_productos' => true,
                'ver_servicios' => true
            ];
            
        case 'cliente':
        default:
            return [
                'gestion_productos' => false,
                'gestion_proveedores' => false,
                'gestion_clientes' => false,
                'gestion_servicios' => false,
                'gestion_categorias' => false,
                'gestion_usuarios' => false,
                'gestion_empleados' => false,
                'gestion_citas' => false,
                'gestion_pagos' => false,
                'gestion_configuracion' => false,
                'cerrar_caja' => false,
                'ver_reportes' => false,
                'acceso_panel_admin' => false,
                'acceso_panel_empleado' => false,
                'reservar_citas' => true,
                'ver_mis_citas' => true,
                'cancelar_mis_citas' => true,
                'auto_gestion_citas' => false,
                'cambiar_estado_citas' => false,
                'ver_perfil' => true,
                'editar_perfil' => true,
                'ver_productos' => false,
                'ver_servicios' => false
            ];
    }
}

// Verificar si un usuario tiene un permiso

function tiene_permiso(string $permiso, ?int $id_usuario = null): bool {
    if ($id_usuario === null) {
        if (empty($_SESSION['id_usuario'])) {
            return false;
        }
        $id_usuario = (int)$_SESSION['id_usuario'];
        $rol = $_SESSION['rol'] ?? 'cliente';
    } else {
        try {
            $pdo = conectar();
            $stmt = $pdo->prepare("SELECT rol FROM usuario WHERE id_usuario = :id");
            $stmt->execute([':id' => $id_usuario]);
            $usuario = $stmt->fetch();
            $rol = $usuario ? $usuario['rol'] : 'cliente';
        } catch (PDOException $e) {
            error_log("Error al obtener rol: " . $e->getMessage());
            return false;
        }
    }

    if ($rol === 'administrador') {
        return true;
    }

    $permisos = obtenerPermisosPorDefecto($rol);

    try {
        $pdo = conectar();
        $stmt = $pdo->prepare("SELECT permisos FROM usuario WHERE id_usuario = :id");
        $stmt->execute([':id' => $id_usuario]);
        $usuario = $stmt->fetch();
        
        if ($usuario && !empty($usuario['permisos'])) {
            $personalizados = json_decode($usuario['permisos'], true);
            if (is_array($personalizados)) {
                foreach ($personalizados as $key => $value) {
                    $permisos[$key] = (bool)$value;
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error al obtener permisos JSON: " . $e->getMessage());
    }

    return isset($permisos[$permiso]) ? (bool)$permisos[$permiso] : false;
}

// Requerir login

function requerir_login(): void {
    if (empty($_SESSION['id_usuario'])) {
        header('Location: login.php');
        exit;
    }
}

// Requerir permiso

function requerir_permiso(string $permiso): void {
    requerir_login();

    if (!tiene_permiso($permiso)) {
        http_response_code(403);
        die('
        <div style="font-family:sans-serif;text-align:center;margin-top:80px;padding:40px;background:#1a1a2e;color:#e6e6e6;border-radius:12px;max-width:500px;margin-left:auto;margin-right:auto;">
            <h2 style="color:#e74c3c;">Acceso Denegado</h2>
            <p style="color:#aaa;">No tenés permiso para acceder a esta sección.</p>
            <a href="home.php" style="display:inline-block;margin-top:20px;padding:10px 24px;background:#c9a84c;color:#1a1a2e;text-decoration:none;border-radius:8px;">← Volver al inicio</a>
        </div>
        ');
        exit;
    }
}

// Obtener todos los permisos

function obtener_todos_permisos(int $id_usuario): array {
    try {
        $pdo = conectar();
        $stmt = $pdo->prepare("SELECT rol, permisos FROM usuario WHERE id_usuario = :id");
        $stmt->execute([':id' => $id_usuario]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return [];
        }

        $rol = $usuario['rol'];
        $permisos = obtenerPermisosPorDefecto($rol);

        if (!empty($usuario['permisos'])) {
            $personalizados = json_decode($usuario['permisos'], true);
            if (is_array($personalizados)) {
                foreach ($personalizados as $nombre => $valor) {
                    $permisos[$nombre] = (bool)$valor;
                }
            }
        }

        return $permisos;

    } catch (PDOException $e) {
        error_log("Error en obtener_todos_permisos(): " . $e->getMessage());
        return [];
    }
}

// Guardar permisos personalizados

function guardar_permisos_personalizados(int $usuario_id, array $permisos): bool {
    try {
        $pdo = conectar();
        $json = json_encode($permisos);
        
        $stmt = $pdo->prepare("UPDATE usuario SET permisos = :permisos WHERE id_usuario = :id");
        return $stmt->execute([':permisos' => $json, ':id' => $usuario_id]);
        
    } catch (PDOException $e) {
        error_log("Error en guardar_permisos_personalizados(): " . $e->getMessage());
        return false;
    }
}

// Lista de permisos disponibles

function obtener_lista_permisos(): array {
    return [
        'gestion_productos',
        'gestion_proveedores',
        'gestion_clientes',
        'gestion_servicios',
        'gestion_categorias',
        'gestion_usuarios',
        'gestion_empleados',
        'gestion_citas',
        'gestion_pagos',
        'gestion_configuracion',
        'cerrar_caja',
        'ver_reportes',
        'acceso_panel_admin',
        'acceso_panel_empleado',
        'reservar_citas',
        'ver_mis_citas',
        'cancelar_mis_citas',
        'auto_gestion_citas',
        'cambiar_estado_citas',
        'ver_perfil',
        'editar_perfil',
        'ver_productos',
        'ver_servicios'
    ];
}

// Alias para compatibilidad

function verificarPermiso(string $permiso, ?int $id_usuario = null): bool {
    return tiene_permiso($permiso, $id_usuario);
}

function obtenerTodosPermisos(int $id_usuario): array {
    return obtener_todos_permisos($id_usuario);
}

function guardarPermisosPersonalizados(int $usuario_id, array $permisos): bool {
    return guardar_permisos_personalizados($usuario_id, $permisos);
}

function obtenerListaPermisos(): array {
    return obtener_lista_permisos();
}

// Funciones para modo invitado

function esta_logueado() {
    return isset($_SESSION['id_usuario']) && !empty($_SESSION['id_usuario']);
}

function es_invitado() {
    return !esta_logueado();
}

function requerir_login_para_accion($accion = 'realizar esta acción') {
    if (!esta_logueado()) {
        $_SESSION['redirigir_despues_login'] = $_SERVER['REQUEST_URI'];
        $_SESSION['mensaje_login'] = "Para {$accion} necesitás iniciar sesión o registrarte.";
        header('Location: login.php');
        exit;
    }
}

function obtener_rol_usuario() {
    return $_SESSION['rol'] ?? 'invitado';
}

// Funciones de auditoría

function registrar_auditoria($accion, $tabla_afectada, $id_registro = null, $datos_anteriores = null, $datos_nuevos = null) {
    try {
        if (!esta_logueado()) {
            return false;
        }

        $pdo = conectar();
        
        $id_usuario = $_SESSION['id_usuario'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $json_anteriores = $datos_anteriores ? json_encode($datos_anteriores) : null;
        $json_nuevos = $datos_nuevos ? json_encode($datos_nuevos) : null;

        $stmt = $pdo->prepare("
            INSERT INTO auditorias (id_usuario, accion, tabla_afectada, id_registro, datos_anteriores, datos_nuevos, ip, user_agent)
            VALUES (:id_usuario, :accion, :tabla, :id_registro, :anteriores, :nuevos, :ip, :user_agent)
        ");
        
        return $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':accion' => $accion,
            ':tabla' => $tabla_afectada,
            ':id_registro' => $id_registro,
            ':anteriores' => $json_anteriores,
            ':nuevos' => $json_nuevos,
            ':ip' => $ip,
            ':user_agent' => $user_agent
        ]);

    } catch (PDOException $e) {
        error_log("Error en registrar_auditoria(): " . $e->getMessage());
        return false;
    }
}

function obtener_auditorias($limite = 50, $filtro_tabla = null, $filtro_usuario = null) {
    try {
        $pdo = conectar();
        
        $sql = "
            SELECT a.*, u.usuario, u.email
            FROM auditorias a
            LEFT JOIN usuario u ON a.id_usuario = u.id_usuario
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filtro_tabla)) {
            $sql .= " AND a.tabla_afectada = :tabla";
            $params[':tabla'] = $filtro_tabla;
        }

        if (!empty($filtro_usuario)) {
            $sql .= " AND a.id_usuario = :usuario";
            $params[':usuario'] = $filtro_usuario;
        }

        $sql .= " ORDER BY a.fecha DESC LIMIT :limite";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error en obtener_auditorias(): " . $e->getMessage());
        return [];
    }
}

function contar_auditorias() {
    try {
        $pdo = conectar();
        $stmt = $pdo->query("SELECT COUNT(*) FROM auditorias");
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error en contar_auditorias(): " . $e->getMessage());
        return 0;
    }
}