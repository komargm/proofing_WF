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
      <a class="btn" href="/logout">Wyloguj</a>
    <?php endif; ?>
  </div>

  <main class="container">
    <?= $content ?>
  </main>

  <script src="/assets/app.js"></script>
</body>
</html>
