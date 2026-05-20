<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../config.php';

Session::start();

if (Session::has('user_id')) {
    $userId = Session::get('user_id');
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, nombre, rol, estado FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user || $user['estado'] === 'desactivado') {
            Session::destroy();
            echo json_encode(['logged_in' => false]);
            exit;
        }
        
        echo json_encode([
            'logged_in' => true,
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'rol' => $user['rol']
            ]
        ]);
    } catch (Exception $e) {
        // Fallback a sesión si DB falla
        echo json_encode([
            'logged_in' => true,
            'user' => [
                'id' => Session::get('user_id'),
                'nombre' => Session::get('user_nombre'),
                'rol' => Session::get('user_rol')
            ]
        ]);
    }
} else {
    echo json_encode(['logged_in' => false]);
}
