<?php /** @var array<string,mixed> $photo */ ?>
<?php /** @var array<string,mixed> $album */ ?>
<?php /** @var array<string,mixed> $nav */ ?>
<?php /** @var array<int,array<string,mixed>> $sections */ ?>
<?php /** @var array<int,array<string,mixed>> $comments */ ?>

<?php $sid = $nav['section_id'] ?? null; ?>
<?php $qs = ($sid !== null && (int)$sid > 0) ? ('?section='.(int)$sid) : ''; ?>
<?php
  $secTitle = '';
  if ($sid !== null && (int)$sid > 0) {
    foreach ($sections as $s) {
      if ((int)$s['id'] === (int)$sid) {
        $secTitle = (string)($s['title'] ?? '');
        break;
      }
    }
  }

  $photographer = trim((string)($album['photographer_first_name'] ?? ''));
  if ($photographer === '') $photographer = 'Fotograf';
?>
<a class="btn" href="/client/album/<?= (int)$album['id'] ?><?= $qs ?>">← Wróć do albumu</a>

<h1><?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?></h1>
<p class="muted">Data albumu: <?= htmlspecialchars((string)$album['created_at'], ENT_QUOTES, 'UTF-8') ?></p>

<div class="viewer">
  <div class="viewer-left">
    <div class="viewer-nav">
      <?php if (!empty($nav['prev_id'])): ?>
        <a class="btn" href="/client/photo/<?= (int)$nav['prev_id'] ?><?= $qs ?>">← Poprzednie</a>
      <?php else: ?>
        <span class="btn ghost disabled">← Poprzednie</span>
      <?php endif; ?>

      <?php if (!empty($nav['next_id'])): ?>
        <a class="btn" href="/client/photo/<?= (int)$nav['next_id'] ?><?= $qs ?>">Następne →</a>
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
        <?php if ($secTitle !== ''): ?>
          <span class="pill" style="border-color:#3b82f6; color:#93c5fd;">Sekcja: <?= htmlspecialchars($secTitle, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>

      <p class="muted">Data dodania: <?= htmlspecialchars((string)$photo['photo_created_at'], ENT_QUOTES, 'UTF-8') ?></p>

      <div class="row">
        <button class="btn heart-big <?= !empty($photo['client_selected_at']) ? 'is-on' : '' ?>"
                type="button"
                data-photo-id="<?= (int)$photo['id'] ?>"
                id="js-heart">♥</button>

        <div class="rating-row"
             data-photo-id="<?= (int)$photo['id'] ?>"
             data-admin-rating="<?= (int)($photo['admin_rating'] ?? 0) ?>"
             data-photographer-name="<?= htmlspecialchars($photographer ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <?php for ($i=1; $i<=6; $i++): ?>
            <button type="button"
                    class="rate-btn <?= ((int)($photo['client_rating'] ?? 0) === $i) ? 'is-on' : '' ?>"
                    data-rating="<?= $i ?>">★</button>
          <?php endfor; ?>
          <button type="button" class="rate-clear" title="Wyczyść ocenę">×</button>
        </div>
      </div>

      <?php
        $cr = (int)($photo['client_rating'] ?? 0);
        $ar = (int)($photo['admin_rating'] ?? 0);
        $match = ($cr >= 2 && $ar >= 2);
      ?>
      <div class="row" style="margin-top:8px;">
        <span class="pill green" id="js-match-pill" style="<?= $match ? '' : 'display:none;' ?>">
          👍 <?= htmlspecialchars($photographer, ENT_QUOTES, 'UTF-8') ?> też lubi
        </span>
      </div>

      <div class="divider"></div>

      <h3>Chat / komentarze</h3>

      <div class="chat" id="js-chat">
        <?php if (empty($comments)): ?>
          <p class="muted">Brak komentarzy. Napisz pierwszą uwagę.</p>
        <?php else: ?>
          <?php foreach ($comments as $c): ?>
            <?php
              $role = (string)($c['role_name'] ?? '');
              $label = (string)($c['first_name'] ?? '');
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

      <form class="chat-form" id="js-chat-form" data-photo-id="<?= (int)$photo['id'] ?>">
        <input type="text" name="text" maxlength="2000" placeholder="Napisz wiadomość (Enter = wyślij)" />
        <button type="submit" class="btn">Wyślij</button>
      </form>

      <div class="divider"></div>

      <div class="row">
        <label class="checkline">
          <input type="checkbox" disabled <?= !empty($photo['download_allowed_at']) ? 'checked' : '' ?> />
          <span>Pobieranie oryginału: <?= !empty($photo['download_allowed_at']) ? 'TAK' : 'NIE' ?></span>
        </label>

        <?php if (!empty($photo['download_allowed_at'])): ?>
          <a class="btn" href="/media/photo/<?= (int)$photo['id'] ?>/original">Pobierz oryginał</a>
        <?php else: ?>
          <span class="btn ghost disabled">Pobierz oryginał</span>
        <?php endif; ?>
      </div>

    </div>
  </aside>
</div>
