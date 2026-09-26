<?php
/**
 * GET /api/availability.php?room_id=qroo-cuarto-1&start=2026-10-01&end=2026-12-31
 * GET /api/availability.php?property_id=casa-quintana-roo&start=2026-10-01&end=2026-12-31
 *
 * Devuelve las noches ocupadas para pintar el calendario en el frontend.
 */
require_once __DIR__ . '/db.php';

$start = $_GET['start'] ?? date('Y-m-d');
$end   = $_GET['end']   ?? date('Y-m-d', strtotime('+6 months'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    json_response(['error' => 'Formato de fecha inválido, usa YYYY-MM-DD'], 400);
}

$pdo = get_db();

if (!empty($_GET['room_id'])) {
    $stmt = $pdo->prepare(
        "SELECT night_date FROM occupied_nights
         WHERE room_id = :room_id AND night_date BETWEEN :start AND :end
         ORDER BY night_date"
    );
    $stmt->execute(['room_id' => $_GET['room_id'], 'start' => $start, 'end' => $end]);
    $nights = array_column($stmt->fetchAll(), 'night_date');
    json_response(['room_id' => $_GET['room_id'], 'occupied_nights' => $nights]);
}

if (!empty($_GET['property_id'])) {
    $stmt = $pdo->prepare(
        "SELECT r.id AS room_id, r.name AS room_name, r.capacity, r.price_night,
                o.night_date
         FROM rooms r
         LEFT JOIN occupied_nights o
                ON o.room_id = r.id AND o.night_date BETWEEN :start AND :end
         WHERE r.property_id = :property_id AND r.active = 1
         ORDER BY r.id, o.night_date"
    );
    $stmt->execute(['property_id' => $_GET['property_id'], 'start' => $start, 'end' => $end]);

    $rooms = [];
    foreach ($stmt->fetchAll() as $row) {
        $rid = $row['room_id'];
        if (!isset($rooms[$rid])) {
            $rooms[$rid] = [
                'room_id'         => $rid,
                'room_name'       => $row['room_name'],
                'capacity'        => (int)$row['capacity'],
                'price_night'     => (float)$row['price_night'],
                'occupied_nights' => [],
            ];
        }
        if ($row['night_date']) {
            $rooms[$rid]['occupied_nights'][] = $row['night_date'];
        }
    }
    json_response(['property_id' => $_GET['property_id'], 'rooms' => array_values($rooms)]);
}

json_response(['error' => 'Debes indicar room_id o property_id'], 400);
