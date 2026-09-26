<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../api/db.php';

$reservation_id = (int)($_POST['reservation_id'] ?? 0);

if ($reservation_id) {
    $stmt = get_db()->prepare("UPDATE reservations SET status = 'confirmada' WHERE id = :id AND status = 'pendiente'");
    $stmt->execute(['id' => $reservation_id]);
}

header('Location: index.php?ok=confirmada');
exit;
