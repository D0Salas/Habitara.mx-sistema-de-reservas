<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../api/db.php';

$pdo = get_db();
$properties = $pdo->query("SELECT id, name, destination FROM properties ORDER BY name")->fetchAll();

$filesDir = __DIR__ . '/files';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reportes — Habitara</title>
<style>
  body { font-family: 'Jost', sans-serif; background: #f5f0e8; margin: 0; padding: 60px 24px; color: #2c2318; }
  .wrap { max-width: 760px; margin: 0 auto; }
  h1 { font-family: 'Cormorant Garamond', serif; font-size: 34px; color: #3b2f20; margin-bottom: 4px; }
  .sub { color: #7a6f63; font-size: 14px; margin-bottom: 40px; }
  .card { background: #fdfaf4; border: 1px solid #e8dece; padding: 28px 32px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
  .card h2 { font-family: 'Cormorant Garamond', serif; font-size: 22px; margin: 0 0 4px; color: #3b2f20; }
  .card .meta { font-size: 12.5px; color: #7a6f63; }
  .actions { display: flex; gap: 10px; }
  a.btn, button.btn { text-decoration: none; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; padding: 11px 20px; cursor: pointer; border: none; }
  a.btn { background: #5a6650; color: #fff; }
  a.btn:hover { background: #3b2f20; }
  button.btn { background: transparent; border: 1px solid #c9b89a; color: #3b2f20; }
  button.btn:hover { border-color: #5a6650; color: #5a6650; }
  .logout { font-size: 12px; color: #7a6f63; text-decoration: none; }
  form { display: inline; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Reportes</h1>
  <p class="sub">Un archivo de Excel por propiedad, con una hoja de resumen y una hoja por mes. &nbsp;·&nbsp; <a class="logout" href="logout.php">Cerrar sesión</a> &nbsp;·&nbsp; <a class="logout" href="../admin/index.php">Ir a Administración</a></p>

  <?php foreach ($properties as $p):
      $filePath = $filesDir . '/' . preg_replace('/[^a-z0-9\-]/', '', $p['id']) . '.xlsx';
      $exists = file_exists($filePath);
      $updated = $exists ? date('d/m/Y H:i', filemtime($filePath)) : null;
  ?>
    <div class="card">
      <div>
        <h2><?= htmlspecialchars($p['name']) ?></h2>
        <div class="meta">
          <?= $exists ? 'Última actualización: ' . $updated : 'Aún no se ha generado ningún reporte' ?>
        </div>
      </div>
      <div class="actions">
        <form method="post" action="generate_now.php">
          <input type="hidden" name="property_id" value="<?= htmlspecialchars($p['id']) ?>">
          <button class="btn" type="submit">Generar ahora</button>
        </form>
        <?php if ($exists): ?>
          <a class="btn" href="download.php?property_id=<?= urlencode($p['id']) ?>">Descargar Excel</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <p class="sub" style="margin-top: 30px;">Los reportes también se regeneran automáticamente todas las noches. El botón "Generar ahora" te da una versión al momento si acabas de recibir una reserva y no quieres esperar.</p>
</div>
</body>
</html>
