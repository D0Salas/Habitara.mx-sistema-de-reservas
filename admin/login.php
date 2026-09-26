<?php
session_set_cookie_params(60 * 24 * 60 * 60);
session_start();
require_once __DIR__ . '/config_admin.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Contraseña incorrecta.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Administración — Habitara</title>
<style>
  body { font-family: 'Jost', sans-serif; background: #f5f0e8; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
  form { background: #fdfaf4; border: 1px solid #e8dece; padding: 40px; width: 320px; }
  h1 { font-family: 'Cormorant Garamond', serif; font-size: 26px; color: #3b2f20; margin: 0 0 24px; }
  input { width: 100%; box-sizing: border-box; padding: 12px 14px; border: 1px solid #c9b89a; margin-bottom: 14px; font-size: 14px; }
  button { width: 100%; padding: 13px; background: #5a6650; color: #fff; border: none; font-size: 13px; letter-spacing: 0.1em; text-transform: uppercase; cursor: pointer; }
  button:hover { background: #3b2f20; }
  .error { color: #9a4a3a; font-size: 13px; margin-bottom: 14px; }
</style>
</head>
<body>
  <form method="post">
    <h1>Administración</h1>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <input type="password" name="password" placeholder="Contraseña" required autofocus>
    <button type="submit">Entrar</button>
  </form>
</body>
</html>
