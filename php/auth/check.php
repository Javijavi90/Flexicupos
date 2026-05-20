<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../session.php';

Session::start();

if (Session::has('user_id')) {
    echo json_encode([
        'logged_in' => true,
        'user' => [
            'id' => Session::get('user_id'),
            'nombre' => Session::get('user_nombre'),
            'rol' => Session::get('user_rol')
        ]
    ]);
} else {
    echo json_encode([
        'logged_in' => false
    ]);
}
