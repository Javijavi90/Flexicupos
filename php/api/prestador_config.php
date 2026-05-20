<?php
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

if ($method !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

try {
    $db = Database::getConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    $accion = $input['accion'] ?? '';

    // Obtener perfil del prestador
    $stmtPerfil = $db->prepare("SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid");
    $stmtPerfil->execute(['uid' => $userId]);
    $perfil = $stmtPerfil->fetch();
    if (!$perfil) {
        echo json_encode(['status' => 'error', 'message' => 'Perfil de profesional no encontrado.']);
        exit;
    }
    $prestadorId = $perfil['id'];

    // ============================================================
    // Subir logo
    // ============================================================
    if ($accion === 'subir_logo') {
        if (!isset($_FILES['logo'])) {
            echo json_encode(['status' => 'error', 'message' => 'No se recibió ninguna imagen.']);
            exit;
        }

        $file = $_FILES['logo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['status' => 'error', 'message' => 'Formato no permitido. Solo JPG, PNG, WEBP.']);
            exit;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . $prestadorId . '_' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../../img/logos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $stmtUpd = $db->prepare("UPDATE perfiles_prestadores SET logo_url = :logo WHERE id = :pid");
            $stmtUpd->execute(['logo' => 'img/logos/' . $filename, 'pid' => $prestadorId]);
            echo json_encode(['status' => 'success', 'message' => 'Logo subido correctamente.', 'url' => 'img/logos/' . $filename]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al subir el logo.']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);

} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s] ') . "API Prestador Config Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']);
}
