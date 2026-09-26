<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../lib/DateHelper.php';

$room_id     = trim($_POST['room_id'] ?? '');
$check_in    = trim($_POST['check_in'] ?? '');
$check_out   = trim($_POST['check_out'] ?? '');
$guest_name  = trim($_POST['guest_name'] ?? '');
$guest_phone = trim($_POST['guest_phone'] ?? '');
$guest_email = trim($_POST['guest_email'] ?? '');

if (!$room_id || !$guest_name) {
    header('Location: new_reservation.php?error=invalido');
    exit;
}

try {
    $nights = nights_between($check_in, $check_out);
} catch (InvalidArgumentException $e) {
    header('Location: new_reservation.php?error=invalido');
    exit;
}
$num_nights = count($nights);

$pdo = get_db();

$stmt = $pdo->prepare("SELECT price_night FROM rooms WHERE id = :id AND active = 1");
$stmt->execute(['id' => $room_id]);
$room = $stmt->fetch();
if (!$room) {
    header('Location: new_reservation.php?error=invalido');
    exit;
}
$total_price = $room['price_night'] * $num_nights;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO reservations
            (room_id, guest_name, guest_email, guest_phone, check_in, check_out, nights, source, status, total_price)
         VALUES
            (:room_id, :guest_name, :guest_email, :guest_phone, :check_in, :check_out, :nights, 'directo', 'confirmada', :total_price)"
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
    header('Location: index.php?ok=creada');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        header('Location: new_reservation.php?error=conflicto');
        exit;
    }
    header('Location: new_reservation.php?error=invalido');
    exit;
}
