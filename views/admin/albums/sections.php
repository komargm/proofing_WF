<?php /** @var array<string,mixed> $album */ ?>
<?php /** @var array<int,array<string,mixed>> $sections */ ?>

<h1>Sekcje w albumie</h1>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/photos">← Zdjęcia</a>
  <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/edit">Ustawienia albumu</a>
  <a class="btn" href="/admin/albums">Lista albumów</a>
</div>

<div class="card" style="margin-bottom:12px;">
  <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
    <div><strong><?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?></strong></div>
    <div class="muted">Sekcje: <?= count($sections) ?></div>
  </div>
</div>

<div class="card" style="margin-bottom:12px;">
  <h3>Dodaj sekcję</h3>
  <form method="post" action="/admin/album/<?= (int)$album['id'] ?>/sections/create" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
    <input class="input" type="text" name="title" maxlength="120" placeholder="np. Czerwona stylizacja" required />
    <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
    <button class="btn" type="submit">Dodaj</button>
  </form>
  <p class="muted" style="margin-top:8px;">Wskazówka: sekcje to "sub-albumy" w ramach jednej sesji. Klient może przełączać widok: Wszystko / wybrana sekcja.</p>
</div>

<?php if (empty($sections)): ?>
  <p class="muted">Brak sekcji. Dodaj pierwszą powyżej.</p>
<?php else: ?>
  <div class="card">
    <h3>Lista sekcji</h3>
    <div style="display:flex; flex-direction:column; gap:10px;">
      <?php foreach ($sections as $s): ?>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; border:1px solid rgba(255,255,255,.08); padding:10px; border-radius:12px;">
          <div style="min-width:220px;"><strong><?= htmlspecialchars((string)$s['title'], ENT_QUOTES, 'UTF-8') ?></strong></div>
          <a class="btn mini" href="/admin/album/<?= (int)$album['id'] ?>/photos?section=<?= (int)$s['id'] ?>">Pokaż zdjęcia</a>

          <form method="post" action="/admin/album/<?= (int)$album['id'] ?>/sections/<?= (int)$s['id'] ?>/rename" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-left:auto;">
            <input class="input" type="text" name="title" maxlength="120" value="<?= htmlspecialchars((string)$s['title'], ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
            <button class="btn mini" type="submit">Zmień</button>
          </form>

          <form method="post" action="/admin/album/<?= (int)$album['id'] ?>/sections/<?= (int)$s['id'] ?>/delete" onsubmit="return confirm('Usunąć sekcję? Zdjęcia nie znikną – tylko stracą przypisanie do tej sekcji.');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />
            <button class="btn mini" type="submit" style="border-color:#ff4d4f; color:#ff4d4f;">Usuń</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
