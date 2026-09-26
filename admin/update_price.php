<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../api/db.php';

$reservation_id = (int)($_POST['reservation_id'] ?? 0);
$total_price = $_POST['total_price'] ?? '';

if ($reservation_id && $total_price !== '' && is_numeric($total_price) && (float)$total_price >= 0) {
    $pdo = get_db();
    // Solo se puede editar el precio de reservas que NO vinieron directo del sitio
    // (las directas ya calculan su precio automáticamente al crearse).
    $stmt = $pdo->prepare(
        "UPDATE reservations SET total_price = :price WHERE id = :id AND source != 'directo'"
    );
    $stmt->execute(['price' => round((float)$total_price, 2), 'id' => $reservation_id]);
}

header('Location: index.php?ok=precio');
exit;
