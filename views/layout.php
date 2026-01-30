<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>WhisperedFrames</title>
  <link rel="stylesheet" href="/assets/app.css" />
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
</body>
</html>
