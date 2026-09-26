<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../api/db.php';

$reservation_id = (int)($_POST['reservation_id'] ?? 0);

if ($reservation_id) {
    $pdo = get_db();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelada' WHERE id = :id AND status IN ('confirmada', 'pendiente')");
        $stmt->execute(['id' => $reservation_id]);

        // Libera las noches si las tenía bloqueadas (una pendiente no tenía ninguna,
        // así que este DELETE simplemente no afecta nada en ese caso).
        $del = $pdo->prepare("DELETE FROM occupied_nights WHERE reservation_id = :id");
        $del->execute(['id' => $reservation_id]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('[Habitara] Error cancelando reserva: ' . $e->getMessage());
    }
}

header('Location: index.php?ok=cancelada');
exit;
