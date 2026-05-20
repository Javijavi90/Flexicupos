<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

Session::start();
if (!Session::has('user_id') || Session::get('user_rol') !== 'prestador') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['imagen'])) {
    echo json_encode(['status' => 'error', 'message' => 'Petición inválida']);
    exit;
}

$userId = Session::get('user_id');

try {
    $db = Database::getConnection();
    
    // Obtener ID del prestador
    $stmt = $db->prepare("SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid");
    $stmt->execute(['uid' => $userId]);
    $prestador = $stmt->fetch();
    
    if (!$prestador) {
        echo json_encode(['status' => 'error', 'message' => 'Perfil de prestador no encontrado']);
        exit;
    }
    
    $prestadorId = $prestador['id'];

    // Validar límite de 10 imágenes
    $stmt = $db->prepare("SELECT COUNT(*) FROM imagenes_portafolio WHERE prestador_id = :pid");
    $stmt->execute(['pid' => $prestadorId]);
    if ($stmt->fetchColumn() >= 10) {
        echo json_encode(['status' => 'error', 'message' => 'Límite máximo de 10 imágenes alcanzado.']);
        exit;
    }

    $file = $_FILES['imagen'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'Formato no permitido. Solo JPG, PNG, WEBP.']);
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'portafolio_' . $prestadorId . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/../../img/portafolio/';
    
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $destination = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $stmt = $db->prepare("INSERT INTO imagenes_portafolio (prestador_id, ruta_imagen) VALUES (:pid, :ruta)");
        $stmt->execute(['pid' => $prestadorId, 'ruta' => 'img/portafolio/' . $filename]);
        
        echo json_encode(['status' => 'success', 'message' => 'Imagen subida correctamente.', 'url' => 'img/portafolio/' . $filename]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al mover el archivo.']);
    }

} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s] ') . "API Upload Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
