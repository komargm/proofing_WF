<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>WhisperedFrames</title>
  <link rel="stylesheet" href="/assets/app.css" />
  <?php if (!empty($_SESSION['user_id'])): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
</head>
<body>
  <div class="topbar">
    <div class="brand">WhisperedFrames</div>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <?php
        $fn = trim((string)($_SESSION['user_first_name'] ?? ''));
        $ln = trim((string)($_SESSION['user_last_name'] ?? ''));
        $email = trim((string)($_SESSION['user_email'] ?? ''));

        if ($fn !== '') {
          $display = 'Witaj: ' . ($ln !== '' ? ($fn . ' ' . $ln) : $fn);
        } elseif ($email !== '') {
          $display = $email;
        } else {
          $display = 'Zalogowano';
        }
      ?>
      <div class="right">
        <span class="pill"><?= htmlspecialchars($display, ENT_QUOTES, 'UTF-8') ?></span>
        <a class="btn" href="/logout">Wyloguj</a>
      </div>
    <?php endif; ?>
  </div>



  <main class="container">
    <?= $content ?>
  </main>

  <script src="/assets/app.js"></script>
</body>
</html>
