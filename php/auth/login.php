<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$correo = trim($input['correo'] ?? '');
$password = $input['password'] ?? '';

if (empty($correo) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
    exit;
}

try {
    $db = Database::getConnection();
    
    // Verificar si la cuenta está desactivada
    $stmt = $db->prepare("SELECT id, nombre, password_hash, rol, estado FROM usuarios WHERE correo = :correo");
    $stmt->execute(['correo' => $correo]);
    $user = $stmt->fetch();

    if (!$user || sha1($password) !== $user['password_hash']) {
        // Mensaje genérico: no revelar si el usuario existe o no
        echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas.']);
        exit;
    }

    // Cuenta desactivada → mismo mensaje que si no existiera
    if ($user['estado'] === 'desactivado') {
        echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas.']);
        exit;
    }

    Session::start();
    Session::set('user_id', $user['id']);
    Session::set('user_nombre', $user['nombre']);
    Session::set('user_rol', $user['rol']);
    
    echo json_encode(['status' => 'success', 'role' => $user['rol']]);
} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s] ') . "API Login Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']);
}
