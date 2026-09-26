<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método no permitido, usa POST'], 405);
}

$payload = staff_require_permission('update_status');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($input['id'] ?? 0);
$status = trim($input['status'] ?? '');

if (!$id || !$status) {
    json_response(['error' => 'id y status son obligatorios'], 400);
}

$pdo = get_db();
try {
    $ok = mr_update_status($pdo, $id, $status, (int)$payload['sub']);
} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 400);
}

if (!$ok) {
    json_response(['error' => 'Reporte no encontrado'], 404);
}
json_response(['success' => true]);
