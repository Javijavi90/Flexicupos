<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

try {
    $db = Database::getConnection();

    // ============================================================
    // Detalle de un profesional específico
    // ============================================================
    $detalleId = isset($_GET['detalle_id']) ? intval($_GET['detalle_id']) : 0;
    if ($detalleId > 0) {
        $stmtProf = $db->prepare("
            SELECT pp.*, u.nombre as usuario_nombre, u.correo, u.telefono
            FROM perfiles_prestadores pp
            JOIN usuarios u ON u.id = pp.usuario_id
            WHERE pp.id = :pid
        ");
        $stmtProf->execute(['pid' => $detalleId]);
        $prof = $stmtProf->fetch();

        if (!$prof) {
            echo json_encode(['status' => 'error', 'message' => 'Profesional no encontrado.']);
            exit;
        }

        // Servicios activos con planes
        $stmtServ = $db->prepare("
            SELECT sp.*,
                   (SELECT COUNT(*) FROM planes_servicio WHERE servicio_id = sp.id) as planes_count
            FROM servicios_precios sp
            WHERE sp.prestador_id = :pid AND sp.activo = 1
            ORDER BY sp.creado_en
        ");
        $stmtServ->execute(['pid' => $detalleId]);
        $servicios = $stmtServ->fetchAll();

        foreach ($servicios as &$serv) {
            $stmtPlanes = $db->prepare("SELECT * FROM planes_servicio WHERE servicio_id = :sid ORDER BY precio");
            $stmtPlanes->execute(['sid' => $serv['id']]);
            $serv['planes'] = $stmtPlanes->fetchAll();
        }
        $prof['servicios'] = $servicios;

        // Imágenes del portafolio
        $stmtImgs = $db->prepare("SELECT * FROM imagenes_portafolio WHERE prestador_id = :pid ORDER BY subido_en DESC");
        $stmtImgs->execute(['pid' => $detalleId]);
        $prof['imagenes'] = $stmtImgs->fetchAll();

        // Horarios
        $stmtHorarios = $db->prepare("SELECT * FROM horarios_prestador WHERE prestador_id = :pid ORDER BY dia_semana, hora_inicio");
        $stmtHorarios->execute(['pid' => $detalleId]);
        $prof['horarios'] = $stmtHorarios->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $prof]);
        exit;
    }

    $busqueda = trim($_GET['q'] ?? '');
    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $limite = min(50, max(1, intval($_GET['limite'] ?? 10)));
    $offset = ($pagina - 1) * $limite;

    // ============================================================
    // Buscar profesionales con etiquetas activas
    // ============================================================
    $where = "WHERE pp.activo_etiqueta = 1";
    $params = [];

    if (!empty($busqueda)) {
        $where .= " AND (
            pp.especialidad LIKE :busqueda 
            OR pp.categoria LIKE :busqueda 
            OR u.nombre LIKE :busqueda
            OR pp.nombre_empresa LIKE :busqueda
            OR pp.descripcion LIKE :busqueda
            OR s.nombre_servicio LIKE :busqueda
        )";
        $params['busqueda'] = "%$busqueda%";
    }

    // Contar total
    $stmtCount = $db->prepare("
        SELECT COUNT(DISTINCT pp.id) as total
        FROM perfiles_prestadores pp
        JOIN usuarios u ON u.id = pp.usuario_id
        LEFT JOIN servicios_precios s ON s.prestador_id = pp.id AND s.activo = 1
        $where
    ");
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    // Obtener profesionales
    $stmtProf = $db->prepare("
        SELECT DISTINCT pp.id, pp.usuario_id, pp.tipo_entidad, pp.nombre_legal, pp.apellido_legal,
               pp.nombre_empresa, pp.especialidad, pp.categoria, pp.direccion_fisica,
               pp.descripcion, pp.logo_url, u.nombre as usuario_nombre
        FROM perfiles_prestadores pp
        JOIN usuarios u ON u.id = pp.usuario_id
        LEFT JOIN servicios_precios s ON s.prestador_id = pp.id AND s.activo = 1
        $where
        ORDER BY pp.id ASC
        LIMIT $limite OFFSET $offset
    ");
    $stmtProf->execute($params);
    $profesionales = $stmtProf->fetchAll();

    // Obtener servicios e imágenes de cada profesional
    foreach ($profesionales as &$prof) {
        // Servicios activos
        $stmtServ = $db->prepare("
            SELECT sp.*, 
                   (SELECT COUNT(*) FROM planes_servicio WHERE servicio_id = sp.id) as planes_count
            FROM servicios_precios sp 
            WHERE sp.prestador_id = :pid AND sp.activo = 1
            ORDER BY sp.creado_en
        ");
        $stmtServ->execute(['pid' => $prof['id']]);
        $servicios = $stmtServ->fetchAll();

        foreach ($servicios as &$serv) {
            $stmtPlanes = $db->prepare("SELECT * FROM planes_servicio WHERE servicio_id = :sid ORDER BY precio");
            $stmtPlanes->execute(['sid' => $serv['id']]);
            $serv['planes'] = $stmtPlanes->fetchAll();
        }

        $prof['servicios'] = $servicios;

        // Obtener primera imagen del portafolio
        $stmtImg = $db->prepare("SELECT ruta_imagen FROM imagenes_portafolio WHERE prestador_id = :pid ORDER BY subido_en DESC LIMIT 1");
        $stmtImg->execute(['pid' => $prof['id']]);
        $img = $stmtImg->fetch();
        $prof['imagen_principal'] = $img ? $img['ruta_imagen'] : null;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $profesionales,
        'total' => (int)$total,
        'pagina' => $pagina,
        'limite' => $limite,
        'total_paginas' => max(1, ceil($total / $limite))
    ]);

} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s] ') . "API Profesionales Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']);
}
