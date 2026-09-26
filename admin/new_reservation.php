<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../api/db.php';

$pdo = get_db();
$rooms = $pdo->query(
    "SELECT r.id, r.name AS room_name, p.name AS property_name
     FROM rooms r JOIN properties p ON p.id = r.property_id
     WHERE r.active = 1
     ORDER BY p.name, r.name"
)->fetchAll();

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Nueva reserva manual — Habitara</title>
<style>
  body { font-family: 'Jost', sans-serif; background: #f5f0e8; margin: 0; padding: 48px 24px; color: #2c2318; }
  .wrap { max-width: 560px; margin: 0 auto; background: #fdfaf4; border: 1px solid #e8dece; padding: 36px; }
  h1 { font-family: 'Cormorant Garamond', serif; font-size: 28px; color: #3b2f20; margin: 0 0 4px; }
  .sub { color: #7a6f63; font-size: 13px; margin-bottom: 28px; }
  label { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #7a6f63; margin: 16px 0 6px; }
  input, select { width: 100%; box-sizing: border-box; padding: 11px 13px; border: 1px solid #c9b89a; font-size: 14px; font-family: inherit; }
  .row { display: flex; gap: 14px; }
  .row > div { flex: 1; }
  button { margin-top: 26px; width: 100%; padding: 13px; background: #5a6650; color: #fff; border: none; font-size: 13px; letter-spacing: 0.08em; text-transform: uppercase; cursor: pointer; }
  button:hover { background: #3b2f20; }
  .error { background: #f6dfd8; color: #7a2e1b; padding: 12px 14px; font-size: 13.5px; margin-bottom: 18px; }
  a.back { display: inline-block; margin-top: 18px; font-size: 12.5px; color: #7a6f63; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Nueva reserva manual</h1>
  <p class="sub">Para reservas tomadas por teléfono o en persona. Queda confirmada de inmediato.</p>

  <?php if ($error === 'conflicto'): ?>
    <div class="error">Esas fechas ya no están disponibles para ese cuarto. Elige otras fechas.</div>
  <?php elseif ($error === 'invalido'): ?>
    <div class="error">Revisa los datos: falta algo o las fechas no son válidas.</div>
  <?php endif; ?>

  <form method="post" action="create_reservation.php">
    <label>Cuarto</label>
    <select name="room_id" required>
      <option value="">Selecciona un cuarto...</option>
      <?php foreach ($rooms as $r): ?>
        <option value="<?= htmlspecialchars($r['id']) ?>"><?= htmlspecialchars($r['property_name'] . ' — ' . $r['room_name']) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="row">
      <div>
        <label>Entrada</label>
        <input type="date" name="check_in" required>
      </div>
      <div>
        <label>Salida</label>
        <input type="date" name="check_out" required>
      </div>
    </div>

    <label>Nombre del huésped</label>
    <input type="text" name="guest_name" required>

    <div class="row">
      <div>
        <label>Teléfono</label>
        <input type="text" name="guest_phone">
      </div>
      <div>
        <label>Correo (opcional)</label>
        <input type="email" name="guest_email">
      </div>
    </div>

    <button type="submit">Crear reserva confirmada</button>
  </form>
  <a class="back" href="index.php">← Regresar al panel</a>
</div>
</body>
</html>
