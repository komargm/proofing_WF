<?php /** @var array<string,mixed> $album */ ?>

<h1>Ustawienia albumu</h1>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/albums">← Lista albumów</a>
  <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/photos">Podgląd zdjęć</a>
</div>

<div class="card" style="max-width: 860px;">
  <form method="post" action="/admin/album/<?= (int)$album['id'] ?>/edit" style="display:grid; gap:12px;">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>" />

    <label style="display:grid; gap:6px;">
      <div class="muted">Tytuł</div>
      <input class="input" name="title" required maxlength="255" value="<?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?>" />
    </label>

    <label style="display:grid; gap:6px;">
      <div class="muted">Notatka do albumu (dla klienta)</div>
      <textarea class="input" name="album_comment" rows="6" maxlength="20000" placeholder="Kilka słów do klienta..." required><?= htmlspecialchars((string)($album['album_comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    </label>

    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:6px;">
      <button class="btn primary" type="submit">Zapisz ustawienia</button>
      <a class="btn" href="/admin/albums">Anuluj</a>
    </div>

    <div class="muted" style="margin-top:10px;">
      Kod albumu: <code><?= htmlspecialchars((string)($album['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
    </div>
  </form>
</div>
