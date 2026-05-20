<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

Session::start();
if (!Session::has('user_id') || Session::get('user_rol') !== 'cliente') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$userId = Session::get('user_id');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getConnection();

    // ============================================================
    // GET: Obtener perfil del cliente
    // ============================================================
    if ($method === 'GET') {
        $stmt = $db->prepare("
            SELECT u.nombre, u.correo, u.telefono, u.creado_en,
                   pc.ubicacion, pc.cancelaciones
            FROM usuarios u
            LEFT JOIN perfiles_clientes pc ON u.id = pc.usuario_id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $userId]);
        $data = $stmt->fetch();

        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // ============================================================
    // POST: Actualizar perfil
    // ============================================================
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $nombre = trim($input['nombre'] ?? '');
        $telefono = trim($input['telefono'] ?? '');
        $ubicacion = trim($input['ubicacion'] ?? '');

        if (empty($nombre)) {
            echo json_encode(['status' => 'error', 'message' => 'El nombre es obligatorio.']);
            exit;
        }

        $db->beginTransaction();

        $stmtUser = $db->prepare("UPDATE usuarios SET nombre = :nombre, telefono = :telefono WHERE id = :id");
        $stmtUser->execute(['nombre' => $nombre, 'telefono' => $telefono, 'id' => $userId]);

        // Actualizar o crear perfil de cliente
        $stmtCheck = $db->prepare("SELECT id FROM perfiles_clientes WHERE usuario_id = :uid");
        $stmtCheck->execute(['uid' => $userId]);
        $perfilExist = $stmtCheck->fetch();

        if ($perfilExist) {
            $stmtPerfil = $db->prepare("UPDATE perfiles_clientes SET ubicacion = :ubicacion WHERE usuario_id = :uid");
            $stmtPerfil->execute(['ubicacion' => $ubicacion, 'uid' => $userId]);
        } else {
            $stmtPerfil = $db->prepare("INSERT INTO perfiles_clientes (usuario_id, ubicacion) VALUES (:uid, :ubicacion)");
            $stmtPerfil->execute(['uid' => $userId, 'ubicacion' => $ubicacion]);
        }

        $db->commit();
        echo json_encode(['status' => 'success', 'message' => 'Perfil actualizado correctamente.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log(date('[Y-m-d H:i:s] ') . "API Cliente Perfil Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']);
}
