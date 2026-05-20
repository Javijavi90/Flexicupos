<?php
@ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

Session::start();
if (!Session::has('user_id') || Session::get('user_rol') !== 'prestador') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$userId = Session::get('user_id');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getConnection();

    // Obtener perfil del prestador
    if ($method === 'GET') {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM perfiles_prestadores");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $cols = [];
        }

        // Build SELECT dynamically based on existing columns
        $selectFields = ['u.nombre', 'u.correo', 'u.telefono', 'p.id as perfil_id'];
        $basicFields = ['especialidad', 'direccion_fisica', 'descripcion'];
        $extraFields = ['tipo_entidad', 'nombre_legal', 'apellido_legal', 'nombre_empresa', 'ruc', 'categoria', 'sitio_web', 'redes_sociales', 'logo_url', 'activo_etiqueta'];

        foreach (array_merge($basicFields, $extraFields) as $col) {
            if (in_array($col, $cols)) {
                $selectFields[] = "p.$col";
            }
        }

        $selectSQL = implode(', ', $selectFields);
        $stmt = $db->prepare("SELECT $selectSQL FROM usuarios u LEFT JOIN perfiles_prestadores p ON u.id = p.usuario_id WHERE u.id = :id");
        $stmt->execute(['id' => $userId]);
        $data = $stmt->fetch();

        // Obtener servicios con planes
        $servicios = [];
        try {
            $stmtServ = $db->prepare("SELECT * FROM servicios_precios WHERE prestador_id = (SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid) ORDER BY creado_en");
            $stmtServ->execute(['uid' => $userId]);
            $servicios = $stmtServ->fetchAll();

            foreach ($servicios as &$serv) {
                $stmtPlanes = $db->prepare("SELECT * FROM planes_servicio WHERE servicio_id = :sid ORDER BY precio");
                $stmtPlanes->execute(['sid' => $serv['id']]);
                $serv['planes'] = $stmtPlanes->fetchAll();
            }
        } catch (Exception $e) {
            // Tabla no existe aún
        }

        // Obtener imágenes
        $imagenes = [];
        try {
            $stmtImg = $db->prepare("SELECT * FROM imagenes_portafolio WHERE prestador_id = (SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid) ORDER BY subido_en DESC");
            $stmtImg->execute(['uid' => $userId]);
            $imagenes = $stmtImg->fetchAll();
        } catch (Exception $e) {}

        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'servicios' => $servicios,
            'imagenes' => $imagenes
        ]);
        exit;
    }

    // Actualizar perfil
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        $especialidad = trim($input['especialidad'] ?? '');
        $direccion = trim($input['direccion_fisica'] ?? '');
        $descripcion = trim($input['descripcion'] ?? '');
        $nombre = trim($input['nombre'] ?? '');
        $telefono = trim($input['telefono'] ?? '');

        if (empty($especialidad) || empty($direccion)) {
            echo json_encode(['status' => 'error', 'message' => 'Especialidad y dirección son obligatorios.']);
            exit;
        }

        $db->beginTransaction();

        // Actualizar datos de usuario
        $stmtUser = $db->prepare("UPDATE usuarios SET nombre = :nombre, telefono = :telefono WHERE id = :id");
        $stmtUser->execute(['nombre' => $nombre, 'telefono' => $telefono, 'id' => $userId]);

        // Verificar si ya existe perfil
        $stmtCheck = $db->prepare("SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid");
        $stmtCheck->execute(['uid' => $userId]);
        $existing = $stmtCheck->fetch();

        // Columnas básicas que SEGURO existen
        $basicSQL = "especialidad = :especialidad, direccion_fisica = :direccion, descripcion = :descripcion";
        $basicParams = [
            'especialidad' => $especialidad,
            'direccion' => $direccion,
            'descripcion' => $descripcion
        ];

        // Columnas extra que PODRÍAN existir
        $extraFields = [
            'tipo_entidad' => $input['tipo_entidad'] ?? 'particular',
            'nombre_legal' => $input['nombre_legal'] ?? '',
            'apellido_legal' => $input['apellido_legal'] ?? '',
            'nombre_empresa' => $input['nombre_empresa'] ?? '',
            'ruc' => $input['ruc'] ?? '',
            'categoria' => $input['categoria'] ?? '',
            'sitio_web' => $input['sitio_web'] ?? '',
            'redes_sociales' => $input['redes_sociales'] ?? ''
        ];

        // Detectar columnas existentes
        $existingCols = [];
        try {
            $stmtCols = $db->query("SHOW COLUMNS FROM perfiles_prestadores");
            $existingCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}

        $extraSQLParts = [];
        $extraParams = [];
        foreach ($extraFields as $col => $val) {
            if (in_array($col, $existingCols)) {
                $paramName = str_replace('_', '', $col);
                $extraSQLParts[] = "$col = :$paramName";
                $extraParams[$paramName] = $val;
            }
        }
        $extraSQL = !empty($extraSQLParts) ? ', ' . implode(', ', $extraSQLParts) : '';

        if ($existing) {
            $sql = "UPDATE perfiles_prestadores SET $basicSQL$extraSQL WHERE usuario_id = :uid";
            $params = array_merge($basicParams, $extraParams, ['uid' => $userId]);
            $stmtPerfil = $db->prepare($sql);
            $stmtPerfil->execute($params);
        } else {
            $columns = 'usuario_id, especialidad, direccion_fisica, descripcion';
            $values = ':uid, :especialidad, :direccion, :descripcion';
            $params = array_merge(['uid' => $userId], $basicParams);

            foreach ($extraFields as $col => $val) {
                if (in_array($col, $existingCols)) {
                    $paramName = str_replace('_', '', $col);
                    $columns .= ", $col";
                    $values .= ", :$paramName";
                    $params[$paramName] = $val;
                }
            }

            $sql = "INSERT INTO perfiles_prestadores ($columns) VALUES ($values)";
            $stmtInsert = $db->prepare($sql);
            $stmtInsert->execute($params);
        }

        $db->commit();

        echo json_encode(['status' => 'success', 'message' => 'Perfil actualizado correctamente.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $errorMsg = $e->getMessage();
    error_log(date('[Y-m-d H:i:s] ') . "API Prestador Perfil Error: " . $errorMsg . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $errorMsg]);
}
