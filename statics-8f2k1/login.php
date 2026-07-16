<?php
require_once __DIR__ . '/auth.php';

// Ya logueado → al panel.
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (is_locked_out($PANEL_CFG)) {
        $error = 'Demasiados intentos. Probá de nuevo en unos minutos.';
    } else {
        $user = $_POST['user'] ?? '';
        $pass = $_POST['pass'] ?? '';

        if (check_credentials($user, $pass, $PANEL_CFG)) {
            record_attempt(true);
            session_regenerate_id(true);
            $_SESSION['panel_auth'] = true;
            $_SESSION['panel_user'] = $user;
            header('Location: index.php');
            exit;
        } else {
            record_attempt(false);
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Panel · Acceso</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      background: #1a0f2e; color: #f4ecd8;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    }
    .card {
      width: 100%; max-width: 340px; padding: 32px;
      background: #241638; border: 1px solid #3a2957; border-radius: 14px;
    }
    h1 { margin: 0 0 4px; font-size: 20px; }
    p.sub { margin: 0 0 22px; font-size: 13px; color: #b3a4cc; }
    label { display: block; font-size: 12px; margin: 14px 0 6px; color: #c9bce0; }
    input {
      width: 100%; padding: 11px 12px; border-radius: 8px;
      border: 1px solid #4a3a6b; background: #1b1030; color: #f4ecd8; font-size: 15px;
    }
    button {
      width: 100%; margin-top: 22px; padding: 12px;
      border: 0; border-radius: 999px; cursor: pointer;
      background: linear-gradient(135deg, #ffd76b, #ff9f43); color: #2b1a00;
      font-weight: 600; font-size: 15px;
    }
    .err { margin-top: 16px; padding: 10px 12px; border-radius: 8px;
           background: #4a1d2b; color: #ffb3c1; font-size: 13px; }
  </style>
</head>
<body>
  <form class="card" method="POST" autocomplete="off">
    <h1>Panel de analíticas</h1>
    <p class="sub">Las Trillizas de Oro y El Libro Mágico</p>

    <label for="user">Usuario</label>
    <input id="user" name="user" type="text" required autofocus />

    <label for="pass">Contraseña</label>
    <input id="pass" name="pass" type="password" required />

    <button type="submit">Entrar</button>

    <?php if ($error): ?>
      <div class="err"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
  </form>
</body>
</html>
