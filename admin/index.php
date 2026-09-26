<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../api/db.php';

$pdo = get_db();

$pending = $pdo->query(
    "SELECT res.id, res.guest_name, res.guest_phone, res.guest_email, res.check_in, res.check_out,
            res.nights, res.source, res.total_price, res.status,
            r.name AS room_name, p.name AS property_name
     FROM reservations res
     JOIN rooms r ON r.id = res.room_id
     JOIN properties p ON p.id = r.property_id
     WHERE res.status = 'pendiente'
     ORDER BY res.check_in ASC"
)->fetchAll();

$upcoming = $pdo->query(
    "SELECT res.id, res.guest_name, res.guest_phone, res.guest_email, res.check_in, res.check_out,
            res.nights, res.source, res.total_price, res.status,
            r.name AS room_name, p.name AS property_name
     FROM reservations res
     JOIN rooms r ON r.id = res.room_id
     JOIN properties p ON p.id = r.property_id
     WHERE res.status = 'confirmada' AND res.check_out >= CURDATE()
     ORDER BY res.check_in ASC"
)->fetchAll();

$history = $pdo->query(
    "SELECT res.id, res.guest_name, res.check_in, res.check_out, res.nights,
            res.source, res.status, r.name AS room_name, p.name AS property_name
     FROM reservations res
     JOIN rooms r ON r.id = res.room_id
     JOIN properties p ON p.id = r.property_id
     WHERE res.status = 'cancelada' OR (res.status = 'confirmada' AND res.check_out < CURDATE())
     ORDER BY res.check_in DESC
     LIMIT 100"
)->fetchAll();

$origenLabel = ['directo' => 'Directo', 'airbnb' => 'Airbnb', 'booking' => 'Booking.com'];
$flash = $_GET['ok'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Administración — Habitara</title>
<style>
  body { font-family: 'Jost', sans-serif; background: #f5f0e8; margin: 0; padding: 48px 24px; color: #2c2318; }
  .wrap { max-width: 1100px; margin: 0 auto; }
  h1 { font-family: 'Cormorant Garamond', serif; font-size: 34px; color: #3b2f20; margin-bottom: 4px; }
  h2 { font-family: 'Cormorant Garamond', serif; font-size: 24px; color: #3b2f20; margin: 48px 0 16px; display: flex; align-items: center; gap: 12px; }
  .sub { color: #7a6f63; font-size: 14px; }
  .top-actions { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 12px; }
  .logout { font-size: 12px; color: #7a6f63; text-decoration: none; }
  .btn-new { background: #5a6650; color: #fff; text-decoration: none; font-size: 12px; letter-spacing: 0.06em; text-transform: uppercase; padding: 10px 18px; }
  .btn-new:hover { background: #3b2f20; }
  table { width: 100%; border-collapse: collapse; background: #fdfaf4; border: 1px solid #e8dece; }
  th, td { text-align: left; padding: 12px 14px; font-size: 13.5px; border-bottom: 1px solid #e8dece; }
  th { text-transform: uppercase; font-size: 11px; letter-spacing: 0.06em; color: #7a6f63; font-weight: 400; background: #f5f0e8; }
  tr:last-child td { border-bottom: none; }
  .badge { display: inline-block; padding: 3px 9px; font-size: 11px; letter-spacing: 0.03em; border-radius: 2px; }
  .badge-directo { background: #e4f0de; color: #2f5227; }
  .badge-airbnb { background: #fbe6d8; color: #9a4a14; }
  .badge-booking { background: #dde8f0; color: #1e4a6e; }
  .badge-cancelada { background: #f6dfd8; color: #7a2e1b; }
  .badge-pendiente { background: #fbead0; color: #8a5a12; }
  .count-badge { font-size: 13px; background: #fbead0; color: #8a5a12; padding: 2px 10px; border-radius: 10px; }
  .actions-cell { display: flex; gap: 6px; flex-wrap: wrap; }
  button.cancel, a.edit-link, button.confirm { font-size: 11px; letter-spacing: 0.05em; text-transform: uppercase; padding: 7px 11px; cursor: pointer; text-decoration: none; display: inline-block; border: 1px solid transparent; }
  button.cancel { background: transparent; border-color: #c9b89a; color: #9a4a3a; }
  button.cancel:hover { background: #f6dfd8; border-color: #9a4a3a; }
  a.edit-link { background: transparent; border-color: #c9b89a; color: #3b2f20; }
  a.edit-link:hover { border-color: #5a6650; color: #5a6650; }
  button.confirm { background: #5a6650; color: #fff; }
  button.confirm:hover { background: #3b2f20; }
  .flash { background: #e4f0de; color: #2f5227; padding: 14px 18px; margin-bottom: 24px; font-size: 14px; }
  .flash-error { background: #f6dfd8; color: #7a2e1b; }
  .empty { padding: 24px; text-align: center; color: #7a6f63; font-size: 14px; background: #fdfaf4; border: 1px solid #e8dece; }
  form.inline { display: inline; }
  .price-form { display: flex; gap: 6px; align-items: center; }
  .price-input { width: 80px; padding: 5px 7px; font-size: 12.5px; border: 1px solid #c9b89a; }
  .price-save { background: transparent; border: 1px solid #5a6650; color: #5a6650; font-size: 10.5px; letter-spacing: 0.04em; text-transform: uppercase; padding: 6px 9px; cursor: pointer; }
  .price-save:hover { background: #5a6650; color: #fff; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-actions">
    <div>
      <h1>Administración</h1>
      <p class="sub">Reservas de todas las propiedades. &nbsp;·&nbsp; <a class="logout" href="logout.php">Cerrar sesión</a> &nbsp;·&nbsp; <a class="logout" href="../reportes/index.php">Ir a Reportes</a></p>
    </div>
    <a class="btn-new" href="new_reservation.php">+ Nueva reserva manual</a>
  </div>

  <?php if ($flash === 'cancelada'): ?>
    <div class="flash">La reserva se canceló y esas fechas ya quedaron libres de nuevo.</div>
  <?php elseif ($flash === 'precio'): ?>
    <div class="flash">Precio actualizado. Ya se reflejará en el próximo reporte de Excel.</div>
  <?php elseif ($flash === 'confirmada'): ?>
    <div class="flash">Reserva marcada como confirmada.</div>
  <?php elseif ($flash === 'creada'): ?>
    <div class="flash">Reserva manual creada y confirmada.</div>
  <?php elseif ($flash === 'editada'): ?>
    <div class="flash">Reserva actualizada correctamente.</div>
  <?php endif; ?>

  <h2>Pendientes de confirmar <?php if ($pending): ?><span class="count-badge"><?= count($pending) ?></span><?php endif; ?></h2>
  <?php if ($pending): ?><p class="sub" style="margin-top:-10px;">Estas ya bloquean las fechas. Confírmalas para tu registro, o cancélalas para liberar esas noches.</p><?php endif; ?>
  <?php if (!$pending): ?>
    <div class="empty">No hay reservas pendientes.</div>
  <?php else: ?>
  <table>
    <tr>
      <th>Propiedad</th><th>Cuarto</th><th>Huésped</th><th>Contacto</th>
      <th>Entrada</th><th>Salida</th><th>Noches</th><th>Total</th><th></th>
    </tr>
    <?php foreach ($pending as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['property_name']) ?></td>
      <td><?= htmlspecialchars($r['room_name']) ?></td>
      <td><?= htmlspecialchars($r['guest_name']) ?></td>
      <td><?= htmlspecialchars($r['guest_phone'] ?: $r['guest_email'] ?: '—') ?></td>
      <td><?= htmlspecialchars($r['check_in']) ?></td>
      <td><?= htmlspecialchars($r['check_out']) ?></td>
      <td><?= (int)$r['nights'] ?></td>
      <td><?= $r['total_price'] > 0 ? '$' . number_format((float)$r['total_price'], 2) : '—' ?></td>
      <td>
        <div class="actions-cell">
          <form class="inline" method="post" action="confirm_reservation.php">
            <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
            <button class="confirm" type="submit">Confirmar</button>
          </form>
          <a class="edit-link" href="edit_reservation.php?id=<?= (int)$r['id'] ?>">Editar</a>
          <form class="inline" method="post" action="cancel_reservation.php" onsubmit="return confirm('¿Cancelar esta reserva pendiente?');">
            <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
            <button class="cancel" type="submit">Cancelar</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <h2>Próximas reservas confirmadas <?php if ($upcoming): ?><span class="count-badge"><?= count($upcoming) ?></span><?php endif; ?></h2>
  <?php if (!$upcoming): ?>
    <div class="empty">No hay reservas próximas confirmadas.</div>
  <?php else: ?>
  <table>
    <tr>
      <th>Propiedad</th><th>Cuarto</th><th>Huésped</th><th>Contacto</th>
      <th>Entrada</th><th>Salida</th><th>Noches</th><th>Origen</th><th>Total</th><th></th>
    </tr>
    <?php foreach ($upcoming as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['property_name']) ?></td>
      <td><?= htmlspecialchars($r['room_name']) ?></td>
      <td><?= htmlspecialchars($r['guest_name']) ?></td>
      <td><?= htmlspecialchars($r['guest_phone'] ?: $r['guest_email'] ?: '—') ?></td>
      <td><?= htmlspecialchars($r['check_in']) ?></td>
      <td><?= htmlspecialchars($r['check_out']) ?></td>
      <td><?= (int)$r['nights'] ?></td>
      <td><span class="badge badge-<?= htmlspecialchars($r['source']) ?>"><?= $origenLabel[$r['source']] ?? $r['source'] ?></span></td>
      <td>
        <?php if ($r['source'] === 'directo'): ?>
          <?= $r['total_price'] > 0 ? '$' . number_format((float)$r['total_price'], 2) : '—' ?>
        <?php else: ?>
          <form class="inline price-form" method="post" action="update_price.php">
            <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
            <input type="number" step="0.01" min="0" name="total_price" class="price-input"
                   value="<?= $r['total_price'] > 0 ? number_format((float)$r['total_price'], 2, '.', '') : '' ?>"
                   placeholder="Sin precio">
            <button class="price-save" type="submit">Guardar</button>
          </form>
        <?php endif; ?>
      </td>
      <td>
        <div class="actions-cell">
          <a class="edit-link" href="edit_reservation.php?id=<?= (int)$r['id'] ?>">Editar</a>
          <form class="inline" method="post" action="cancel_reservation.php" onsubmit="return confirm('¿Cancelar esta reserva? Las fechas quedarán libres de nuevo.');">
            <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
            <button class="cancel" type="submit">Cancelar</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <h2>Historial (últimas 100)</h2>
  <?php if (!$history): ?>
    <div class="empty">Aún no hay historial.</div>
  <?php else: ?>
  <table>
    <tr><th>Propiedad</th><th>Cuarto</th><th>Huésped</th><th>Entrada</th><th>Salida</th><th>Noches</th><th>Origen</th><th>Estado</th></tr>
    <?php foreach ($history as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['property_name']) ?></td>
      <td><?= htmlspecialchars($r['room_name']) ?></td>
      <td><?= htmlspecialchars($r['guest_name']) ?></td>
      <td><?= htmlspecialchars($r['check_in']) ?></td>
      <td><?= htmlspecialchars($r['check_out']) ?></td>
      <td><?= (int)$r['nights'] ?></td>
      <td><span class="badge badge-<?= htmlspecialchars($r['source']) ?>"><?= $origenLabel[$r['source']] ?? $r['source'] ?></span></td>
      <td><?= $r['status'] === 'cancelada' ? '<span class="badge badge-cancelada">Cancelada</span>' : 'Completada' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
</body>
</html>
