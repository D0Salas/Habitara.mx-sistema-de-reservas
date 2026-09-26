<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método no permitido, usa POST'], 405);
}

$payload = staff_require_permission('delete');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($input['id'] ?? 0);
if (!$id) {
    json_response(['error' => 'id es obligatorio'], 400);
}

$pdo = get_db();
$ok = mr_delete($pdo, $id);

if (!$ok) {
    json_response(['error' => 'Reporte no encontrado'], 404);
}
json_response(['success' => true]);
