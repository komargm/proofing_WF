<?php /** @var array<string,mixed> $photo */ ?>
<?php /** @var array<string,mixed> $album */ ?>
<?php /** @var array<string,mixed> $nav */ ?>
<?php /** @var array<int,array<string,mixed>> $comments */ ?>

<?php $sid = $nav['section_id'] ?? null; ?>
<?php $qs = ($sid !== null && (int)$sid > 0) ? ('?section='.(int)$sid) : ''; ?>

<div style="margin: 12px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/photos<?= $qs ?>">← Wróć do zdjęć albumu</a>
  <a class="btn" href="/admin/albums">Lista albumów</a>
  <a class="btn" href="/admin/dashboard">Dashboard</a>
  <form method="post" action="/admin/photo/<?= (int)$photo['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Usunąć to zdjęcie? (usunie wpisy w DB + preview/thumb)');">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>" />
    <button type="submit" class="btn" style="border-color:#ff4d4f; color:#ff4d4f;">Usuń zdjęcie</button>
  </form>

</div>

<h1><?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?></h1>
<p class="muted">Data albumu: <?= htmlspecialchars((string)$album['created_at'], ENT_QUOTES, 'UTF-8') ?></p>

<div class="viewer">
  <div class="viewer-left">
    <div class="viewer-nav">
      <?php if (!empty($nav['prev_id'])): ?>
        <a class="btn" href="/admin/photo/<?= (int)$nav['prev_id'] ?><?= $qs ?>">← Poprzednie</a>
      <?php else: ?>
        <span class="btn ghost disabled">← Poprzednie</span>
      <?php endif; ?>

      <?php if (!empty($nav['next_id'])): ?>
        <a class="btn" href="/admin/photo/<?= (int)$nav['next_id'] ?><?= $qs ?>">Następne →</a>
      <?php else: ?>
        <span class="btn ghost disabled">Następne →</span>
      <?php endif; ?>
    </div>

    <div class="viewer-image">
      <img alt="preview" src="/media/photo/<?= (int)$photo['id'] ?>/preview_800" />
    </div>
  </div>

  <aside class="viewer-right">
    <div class="card">
      <div class="row">
        <span class="pill">ID zdjęcia: <?= (int)$photo['id'] ?></span>
        <?php if (!empty($photo['original_basename'])): ?>
          <span class="pill blue"><?= htmlspecialchars((string)$photo['original_basename'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>

      <p class="muted">Data dodania: <?= htmlspecialchars((string)$photo['photo_created_at'], ENT_QUOTES, 'UTF-8') ?></p>

      <div class="row">
        <span class="pill <?= !empty($photo['client_selected_at']) ? 'green' : '' ?>">
          <?= !empty($photo['client_selected_at']) ? 'Wybrane' : 'Nie wybrane' ?>
        </span>

        <span class="pill">
          Ocena: <?= (int)($photo['client_rating'] ?? 0) ?>
        </span>
      </div>

      <div class="divider"></div>

      <h3>Chat / komentarze</h3>

      <div class="chat" id="js-chat">
        <?php if (empty($comments)): ?>
          <p class="muted">Brak komentarzy.</p>
        <?php else: ?>
          <?php foreach ($comments as $c): ?>
            <?php
              $role = (string)($c['role_name'] ?? '');
              $who = $role === 'admin' ? 'Fotograf' : 'Klient';
              $name = trim(((string)($c['first_name'] ?? '') . ' ' . (string)($c['last_name'] ?? '')));
              $label = $name !== '' ? "{$who} ({$name})" : $who;
            ?>
            <div class="chat-msg <?= $role === 'admin' ? 'from-admin' : 'from-client' ?>">
              <div class="chat-meta">
                <strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="muted"><?= htmlspecialchars((string)$c['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="chat-text"><?= nl2br(htmlspecialchars((string)$c['comment_text'], ENT_QUOTES, 'UTF-8')) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <form class="chat-form" id="js-admin-chat-form" data-photo-id="<?= (int)$photo['id'] ?>">
        <input type="text" name="text" maxlength="2000" placeholder="Odpowiedz jako Fotograf" />
        <button type="submit" class="btn">Wyślij</button>
      </form>

      <div class="divider"></div>

      <div class="row">
        <label class="checkline">
          <input type="checkbox"
                 id="js-download-toggle"
                 data-photo-id="<?= (int)$photo['id'] ?>"
                 <?= !empty($photo['download_allowed_at']) ? 'checked' : '' ?> />
          <span>Pobieranie oryginału: <strong id="js-download-label"><?= !empty($photo['download_allowed_at']) ? 'TAK' : 'NIE' ?></strong></span>
        </label>

        <a class="btn" href="/media/photo/<?= (int)$photo['id'] ?>/original">Pobierz oryginał</a>
      </div>

    </div>
  </aside>
</div>
