<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

Session::start();
if (!Session::has('user_id')) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$userId = Session::get('user_id');
$rol = Session::get('user_rol');

try {
    $db = Database::getConnection();
    
    if ($rol === 'cliente') {
        $stmt = $db->prepare("SELECT u.nombre, u.correo, u.telefono, p.ubicacion FROM usuarios u JOIN perfiles_clientes p ON u.id = p.usuario_id WHERE u.id = :id");
        $stmt->execute(['id' => $userId]);
        $data = $stmt->fetch();
    } elseif ($rol === 'prestador') {
        $stmt = $db->prepare("SELECT u.nombre, u.correo, u.telefono, p.especialidad, p.direccion_fisica, p.descripcion FROM usuarios u JOIN perfiles_prestadores p ON u.id = p.usuario_id WHERE u.id = :id");
        $stmt->execute(['id' => $userId]);
        $data = $stmt->fetch();
    } else { // admin
        $stmt = $db->prepare("SELECT nombre, correo, telefono FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $data = $stmt->fetch();
    }

    if ($data) {
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Perfil no encontrado']);
    }

} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s] ') . "API Perfil Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
