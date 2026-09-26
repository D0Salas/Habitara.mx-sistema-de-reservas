<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método no permitido, usa POST'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (!$username || !$password) {
    json_response(['error' => 'username y password son obligatorios'], 400);
}

$pdo = get_db();
$stmt = $pdo->prepare("SELECT * FROM staff_users WHERE username = :u AND active = 1");
$stmt->execute(['u' => $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response(['error' => 'Usuario o contraseña incorrectos'], 401);
}

$token = jwt_encode([
    'sub'  => (int)$user['id'],
    'role' => $user['role'],
    'name' => $user['full_name'],
], staff_jwt_secret(), 3600 * 8); // 8 horas, un turno de trabajo

json_response([
    'token'     => $token,
    'role'      => $user['role'],
    'full_name' => $user['full_name'],
    'expires_in' => 3600 * 8,
]);
