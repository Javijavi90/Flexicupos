<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../session.php';

Session::destroy();
echo json_encode(['status' => 'success', 'message' => 'Sesión cerrada exitosamente.']);
