<?php
require_once __DIR__ . '/auth.php';
require_login();

$property_id = $_GET['property_id'] ?? '';
$safe = preg_replace('/[^a-z0-9\-]/', '', $property_id);
$path = __DIR__ . '/files/' . $safe . '.xlsx';

if (!$safe || !file_exists($path)) {
    http_response_code(404);
    echo 'Reporte no encontrado. Genera uno primero desde el panel.';
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="habitara-' . $safe . '-' . date('Y-m-d') . '.xlsx"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
