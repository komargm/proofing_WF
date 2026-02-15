<?php /** @var array<string,mixed> $album */ ?>
<?php /** @var array<int,array<string,mixed>> $sections */ ?>

<h1>Sekcje w albumie</h1>

<div class="toolbar">
  <div></div>
  <div class="toolbar-actions">
    <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/photos">← Zdjęcia</a>
    <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/edit">Ustawienia albumu</a>
    <a class="btn" href="/admin/albums">Lista albumów</a>
  </div>
</div>

<div class="card admin-sections-album" >
  <div class="admin-sections-album-head">
    <div><strong><?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?></strong></div>
    <div class="muted">Sekcje: <?= count($sections) ?></div>
  </div>
</div>

<div class="card admin-sections-add">
  <h3>Dodaj sekcję</h3>
  <form method="post" action="/admin/album/<?= (int)$album['id'] ?>/sections/create" class="admin-sections-add-form">
    <input class="input" type="text" name="title" maxlength="120" placeholder="np. Czerwona stylizacja" required />
    <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
    <button class="btn" type="submit">Dodaj</button>
  </form>
  <p class="muted admin-sections-hint">Wskazówka: sekcje to "sub-albumy" w ramach jednej sesji. Klient może przełączać widok: Wszystko / wybrana sekcja.</p>
</div>

<?php if (empty($sections)): ?>
  <p class="muted">Brak sekcji. Dodaj pierwszą powyżej.</p>
<?php else: ?>
  <div class="card">
    <h3>Lista sekcji</h3>
    <div class="admin-sections-list">
      <?php foreach ($sections as $s): ?>
        <div class="admin-sections-item admin-card-hover">
          <div class="admin-sections-title"><strong><?= htmlspecialchars((string)$s['title'], ENT_QUOTES, 'UTF-8') ?></strong></div>
          <a class="btn mini" href="/admin/album/<?= (int)$album['id'] ?>/photos?section=<?= (int)$s['id'] ?>">Pokaż zdjęcia</a>

          <form method="post" action="/admin/album/<?= (int)$album['id'] ?>/sections/<?= (int)$s['id'] ?>/rename" class="admin-sections-rename">
            <input class="input" type="text" name="title" maxlength="120" value="<?= htmlspecialchars((string)$s['title'], ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
            <button class="btn mini" type="submit">Zmień</button>
          </form>

          <form method="post" action="/admin/album/<?= (int)$album['id'] ?>/sections/<?= (int)$s['id'] ?>/delete" onsubmit="return confirm('Usunąć sekcję? Zdjęcia nie znikną – tylko stracą przypisanie do tej sekcji.');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
            <button class="btn mini danger" type="submit">Usuń</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
