<?php /** @var array<string,mixed> $album */ ?>

<h1>Ustawienia albumu</h1>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/albums">← Lista albumów</a>
  <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/photos">Podgląd zdjęć</a>
</div>

<div class="card" style="max-width: 860px; margin-top: 16px; border:1px solid #6b1f1f;">
  <h3 style="margin:0 0 8px 0; color:#ffb4b4;">Strefa niebezpieczna</h3>
  <p class="muted" style="margin-top:0;">Usunięcie albumu kasuje wpisy w bazie (album, zdjęcia, pliki, komentarze, dostępy, sekcje) oraz pliki preview/thumb w folderze proofingu. Oryginałów na NAS nie ruszamy.</p>

  <form method="post" action="/admin/album/<?= (int)$album['id'] ?>/delete" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>" />
    <button class="btn" type="submit" style="background:#4a1414; border-color:#6b1f1f;" onclick="return confirm('Na pewno usunąć TEN album?\n\nOperacji nie da się cofnąć.');">Usuń album</button>
  </form>
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

    <label style="display:flex; gap:10px; align-items:center; padding:10px 12px; border:1px solid #2a2a2e; border-radius:10px;">
      <input type="hidden" name="is_visible" value="0" />
      <input type="checkbox" name="is_visible" value="1" <?= !empty($album['is_visible']) ? 'checked' : '' ?> />
      <div>
        <div style="font-weight:600;">Widoczny dla klienta</div>
        <div class="muted" style="margin-top:2px;">Gdy odznaczysz, album zniknie z pulpitu klienta i nie da się do niego wejść po URL.</div>
      </div>
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

<div class="card" style="max-width: 860px; margin-top: 16px;">
  <h3 style="margin:0 0 8px 0;">Narzędzia</h3>
  <p class="muted" style="margin-top:0;">Rescan porównuje oryginały z metadanymi w bazie i regeneruje tylko te preview + miniaturki, które faktycznie się zmieniły (np. po poprawce 2 z 10 zdjęć).</p>

  <form method="post" action="/admin/album/<?= (int)$album['id'] ?>/rescan" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="watermark" value="1" />
    <button class="btn" type="submit" onclick="return confirm('Uruchomić rescan albumu? Zostaną nadpisane preview i miniaturki dla zmienionych plików.');">Rescan albumu</button>
  </form>

  <div style="margin-top:12px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
    <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/add-photo">Dodaj zdjęcie do albumu</a>
    <span class="muted">Wskaż 1 JPG z NAS (PATH_ORIGINALS) → system dopnie do albumu i wygeneruje miniaturę + preview.</span>
  </div>
</div>
