<?php
/**
 * POST /api/create_booking.php
 * Body JSON: {
 *   "room_id": "qroo-cuarto-1",
 *   "check_in": "2026-10-05",
 *   "check_out": "2026-10-09",
 *   "guest_name": "Juan Pérez",
 *   "guest_email": "juan@example.com",
 *   "guest_phone": "9931234567"
 * }
 *
 * Las reservas directas del sitio BLOQUEAN las fechas de inmediato
 * (igual que siempre), y además nacen con status = 'pendiente' -- esto
 * ya no afecta la disponibilidad, es solo para que el equipo lleve un
 * registro de qué reservas ya revisó/confirmó en el panel y cuáles no.
 *
 * Si el huésped sí se queda: el administrador la confirma (admin/confirm_reservation.php),
 * que solo cambia el estado, las fechas ya estaban bloqueadas desde aquí.
 * Si el huésped no se queda: el administrador la cancela, y ESO es lo
 * que libera las fechas (admin/cancel_reservation.php).
 *
 * Lógica anti-doble-reserva (igual que siempre):
 * 1) Transacción: se inserta la reserva y UNA fila por noche en occupied_nights.
 * 2) occupied_nights tiene UNIQUE KEY (room_id, night_date): si cualquier
 *    noche ya está tomada, MySQL rechaza el INSERT (error 23000) y se
 *    revierte todo -- no quedan reservas parciales.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../notify/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método no permitido, usa POST'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    json_response(['error' => 'JSON inválido'], 400);
}

$room_id    = trim($input['room_id'] ?? '');
$check_in   = trim($input['check_in'] ?? '');
$check_out  = trim($input['check_out'] ?? '');
$guest_name = trim($input['guest_name'] ?? '');
$guest_email = trim($input['guest_email'] ?? '');
$guest_phone = trim($input['guest_phone'] ?? '');

if (!$room_id || !$guest_name) {
    json_response(['error' => 'room_id y guest_name son obligatorios'], 400);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
    json_response(['error' => 'Formato de fecha inválido, usa YYYY-MM-DD'], 400);
}

$in  = new DateTime($check_in);
$out = new DateTime($check_out);
if ($out <= $in) {
    json_response(['error' => 'La fecha de salida debe ser posterior a la de entrada'], 400);
}

// Genera la lista de noches ocupadas: entrada inclusive, salida exclusiva.
$nights = [];
$cursor = clone $in;
while ($cursor < $out) {
    $nights[] = $cursor->format('Y-m-d');
    $cursor->modify('+1 day');
}
$num_nights = count($nights);

$pdo = get_db();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT price_night FROM rooms WHERE id = :id AND active = 1 FOR UPDATE");
    $stmt->execute(['id' => $room_id]);
    $room = $stmt->fetch();
    if (!$room) {
        $pdo->rollBack();
        json_response(['error' => 'Cuarto no encontrado o inactivo'], 404);
    }
    $total_price = $room['price_night'] * $num_nights;

    $stmt = $pdo->prepare(
        "INSERT INTO reservations
            (room_id, guest_name, guest_email, guest_phone, check_in, check_out, nights, source, status, total_price)
         VALUES
            (:room_id, :guest_name, :guest_email, :guest_phone, :check_in, :check_out, :nights, 'directo', 'pendiente', :total_price)"
    );
    $stmt->execute([
        'room_id'      => $room_id,
        'guest_name'   => $guest_name,
        'guest_email'  => $guest_email ?: null,
        'guest_phone'  => $guest_phone ?: null,
        'check_in'     => $check_in,
        'check_out'    => $check_out,
        'nights'       => $num_nights,
        'total_price'  => $total_price,
    ]);
    $reservation_id = (int)$pdo->lastInsertId();

    $insertNight = $pdo->prepare(
        "INSERT INTO occupied_nights (room_id, night_date, reservation_id) VALUES (:room_id, :night_date, :reservation_id)"
    );
    foreach ($nights as $night) {
        $insertNight->execute([
            'room_id'        => $room_id,
            'night_date'     => $night,
            'reservation_id' => $reservation_id,
        ]);
    }

    $pdo->commit();

    // Aviso por correo a los dueños (si falla, no afecta la reserva ya guardada).
    $roomInfo = $pdo->prepare("SELECT r.name AS room_name, p.name AS property_name FROM rooms r JOIN properties p ON p.id = r.property_id WHERE r.id = :id");
    $roomInfo->execute(['id' => $room_id]);
    $ri = $roomInfo->fetch();
    notify_new_reservation([
        'property_name' => $ri['property_name'] ?? '',
        'room_name'     => $ri['room_name'] ?? '',
        'guest_name'    => $guest_name,
        'guest_phone'   => $guest_phone,
        'guest_email'   => $guest_email,
        'check_in'      => $check_in,
        'check_out'     => $check_out,
        'nights'        => $num_nights,
        'source'        => 'directo',
        'total_price'   => $total_price,
        'status'        => 'pendiente',
    ]);

    json_response([
        'success'        => true,
        'status'         => 'pendiente',
        'reservation_id' => $reservation_id,
        'room_id'        => $room_id,
        'check_in'       => $check_in,
        'check_out'      => $check_out,
        'nights'         => $num_nights,
        'total_price'    => $total_price,
    ], 201);

} catch (PDOException $e) {
    $pdo->rollBack();

    if ($e->getCode() === '23000') {
        json_response([
            'error' => 'Una o más noches de ese rango ya no están disponibles. Por favor elige otras fechas.',
        ], 409);
    }

    json_response(['error' => 'Error al procesar la reserva', 'detail' => $e->getMessage()], 500);
}
