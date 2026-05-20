<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$tipo = $input['tipo'] ?? 'cliente';
$nombre = $input['nombre'] ?? '';
$correo = $input['correo'] ?? '';
$telefono = $input['telefono'] ?? '';
$password = $input['password'] ?? '';

if (empty($nombre) || empty($correo) || empty($telefono) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos obligatorios.']);
    exit;
}

try {
    $db = Database::getConnection();
    $db->beginTransaction();

    $hash = sha1($password);

    $stmt = $db->prepare("INSERT INTO usuarios (nombre, correo, password_hash, telefono, rol) VALUES (:nombre, :correo, :hash, :telefono, :rol)");
    $stmt->execute([
        'nombre' => $nombre,
        'correo' => $correo,
        'hash' => $hash,
        'telefono' => $telefono,
        'rol' => $tipo
    ]);

    $userId = $db->lastInsertId();

    if ($tipo === 'cliente') {
        $ubicacion = $input['ubicacion'] ?? '';
        $stmtPerfil = $db->prepare("INSERT INTO perfiles_clientes (usuario_id, ubicacion) VALUES (:usuario_id, :ubicacion)");
        $stmtPerfil->execute(['usuario_id' => $userId, 'ubicacion' => $ubicacion]);
    } elseif ($tipo === 'prestador') {
        $especialidad = $input['especialidad'] ?? '';
        $direccion = $input['direccion'] ?? '';
        $descripcion = $input['descripcion'] ?? '';
        
        if (empty($especialidad) || empty($direccion)) {
            $db->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos de perfil del prestador.']);
            exit;
        }

        $stmtPerfil = $db->prepare("INSERT INTO perfiles_prestadores (usuario_id, especialidad, direccion_fisica, descripcion) VALUES (:usuario_id, :especialidad, :direccion_fisica, :descripcion)");
        $stmtPerfil->execute([
            'usuario_id' => $userId,
            'especialidad' => $especialidad,
            'direccion_fisica' => $direccion,
            'descripcion' => $descripcion
        ]);
    }

    $db->commit();
    echo json_encode(['status' => 'success', 'message' => 'Registro exitoso.']);
} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log(date('[Y-m-d H:i:s] ') . "API Register Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => 'Error al registrar. Verifica si el correo ya existe.']);
}
