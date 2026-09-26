<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

staff_require_auth();

$pdo = get_db();
$rooms = $pdo->query(
    "SELECT r.id, r.name AS room_name, p.name AS property_name
     FROM rooms r JOIN properties p ON p.id = r.property_id
     WHERE r.active = 1
     ORDER BY p.name, r.name"
)->fetchAll();

json_response(['rooms' => $rooms]);
