<h1>Pulpit klienta</h1>

<?php if (empty($albums)): ?>
  <p class="muted">Nie masz jeszcze przypisanych albumów.</p>
<?php else: ?>
  <div class="grid">
    <?php foreach ($albums as $a): ?>
      <a class="card album" href="/client/album/<?= (int)$a['id'] ?>">
        <div class="album-title"><?= htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="muted">Utworzono: <?= htmlspecialchars((string)$a['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="pill">Otwórz album</div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
