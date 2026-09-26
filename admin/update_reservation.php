<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../lib/DateHelper.php';

$reservation_id = (int)($_POST['reservation_id'] ?? 0);
$room_id     = trim($_POST['room_id'] ?? '');
$check_in    = trim($_POST['check_in'] ?? '');
$check_out   = trim($_POST['check_out'] ?? '');
$guest_name  = trim($_POST['guest_name'] ?? '');
$guest_phone = trim($_POST['guest_phone'] ?? '');
$guest_email = trim($_POST['guest_email'] ?? '');

if (!$reservation_id || !$room_id || !$guest_name) {
    header("Location: edit_reservation.php?id={$reservation_id}&error=invalido");
    exit;
}

try {
    $nights = nights_between($check_in, $check_out);
} catch (InvalidArgumentException $e) {
    header("Location: edit_reservation.php?id={$reservation_id}&error=invalido");
    exit;
}
$num_nights = count($nights);

$pdo = get_db();

$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = :id");
$stmt->execute(['id' => $reservation_id]);
$current = $stmt->fetch();

if (!$current || $current['status'] === 'cancelada') {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT price_night FROM rooms WHERE id = :id AND active = 1");
$stmt->execute(['id' => $room_id]);
$room = $stmt->fetch();
if (!$room) {
    header("Location: edit_reservation.php?id={$reservation_id}&error=invalido");
    exit;
}
$total_price = $room['price_night'] * $num_nights;

try {
    $pdo->beginTransaction();

    // Tanto 'pendiente' como 'confirmada' ya tienen noches bloqueadas desde que
    // se crearon (el único estado sin noches bloqueadas es 'cancelada', y esas
    // ni siquiera llegan aquí -- se filtran arriba). Liberamos las viejas y
    // reclamamos las nuevas dentro de la misma transacción: si las nuevas
    // chocan con algo, todo se revierte y las viejas quedan intactas.
    $del = $pdo->prepare("DELETE FROM occupied_nights WHERE reservation_id = :id");
    $del->execute(['id' => $reservation_id]);

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

    $upd = $pdo->prepare(
        "UPDATE reservations
         SET room_id = :room_id, check_in = :check_in, check_out = :check_out,
             nights = :nights, guest_name = :guest_name, guest_phone = :guest_phone,
             guest_email = :guest_email, total_price = :total_price
         WHERE id = :id"
    );
    $upd->execute([
        'room_id'     => $room_id,
        'check_in'    => $check_in,
        'check_out'   => $check_out,
        'nights'      => $num_nights,
        'guest_name'  => $guest_name,
        'guest_phone' => $guest_phone ?: null,
        'guest_email' => $guest_email ?: null,
        'total_price' => $total_price,
        'id'          => $reservation_id,
    ]);

    $pdo->commit();
    header('Location: index.php?ok=editada');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        header("Location: edit_reservation.php?id={$reservation_id}&error=conflicto");
        exit;
    }
    header("Location: edit_reservation.php?id={$reservation_id}&error=invalido");
    exit;
}
