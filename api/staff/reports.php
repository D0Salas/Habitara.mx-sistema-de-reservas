<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $payload = staff_require_permission('list');
    $pdo = get_db();
    $status = $_GET['status'] ?? null;
    try {
        $reports = mr_list($pdo, $status);
    } catch (InvalidArgumentException $e) {
        json_response(['error' => $e->getMessage()], 400);
    }
    json_response(['reports' => $reports]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = staff_require_permission('create');
    $pdo = get_db();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $input['reported_by'] = $payload['sub'];

    try {
        $id = mr_create($pdo, $input);
    } catch (InvalidArgumentException $e) {
        json_response(['error' => $e->getMessage()], 400);
    }
    json_response(['success' => true, 'id' => $id], 201);
}

json_response(['error' => 'Método no permitido'], 405);
