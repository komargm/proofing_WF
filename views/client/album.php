<a class="btn" href="/client/dashboard">← Wróć</a>

<h1><?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?></h1>
<p class="muted">Utworzono: <?= htmlspecialchars((string)$album['created_at'], ENT_QUOTES, 'UTF-8') ?></p>

<div class="toolbar">
  <div class="muted">Zdjęcia: <?= (int)$count ?></div>
  <div class="muted">Kliknij ♥, ustaw ocenę, dodaj komentarz.</div>
</div>

<?php if (empty($photos)): ?>
  <p class="muted">Brak zdjęć w albumie (albo nie są jeszcze widoczne).</p>
<?php else: ?>
  <div class="photo-grid">
    <?php foreach ($photos as $p): ?>
      <?php $pid = (int)$p['id']; ?>
      <div class="photo-tile" data-photo-id="<?= $pid ?>">
        <a class="photo-img" href="/client/photo/<?= $pid ?>">
          <?php if (!empty($p['thumb_path'])): ?>
            <img loading="lazy" alt="thumb" src="/media/photo/<?= $pid ?>/thumb" />
          <?php else: ?>
            <div class="thumb-missing">Brak miniatury</div>
          <?php endif; ?>

          <button class="heart <?= !empty($p['client_selected_at']) ? 'is-on' : '' ?>" type="button" aria-label="Wybierz">
            ♥
          </button>
        </a>

        <div class="photo-meta">
          <div class="meta-row">
            <span class="pill js-selected-pill <?= !empty($p['client_selected_at']) ? 'green' : '' ?>">
              <?= !empty($p['client_selected_at']) ? 'Wybrane' : 'Nie wybrane' ?>
            </span>

            <span class="pill blue js-rating-pill" <?= empty($p['client_rating']) ? 'style="display:none"' : '' ?>>
              ★ <span class="js-rating-val"><?= (int)($p['client_rating'] ?? 0) ?></span>/6
            </span>
          </div>

          <div class="rating-row" aria-label="Ocena">
            <?php for ($i=1; $i<=6; $i++): ?>
              <button type="button"
                      class="rate-btn <?= ((int)($p['client_rating'] ?? 0) === $i) ? 'is-on' : '' ?>"
                      data-rating="<?= $i ?>">★</button>
            <?php endfor; ?>
            <button type="button" class="rate-clear" title="Wyczyść ocenę">×</button>
          </div>

          <div class="comment-line js-last-comment">
            <?php if (!empty($p['last_comment_text'])): ?>
              <?= htmlspecialchars((string)$p['last_comment_text'], ENT_QUOTES, 'UTF-8') ?>
            <?php else: ?>
              <span class="muted">Dodaj uwagę…</span>
            <?php endif; ?>
          </div>

          <form class="comment-form">
            <input type="text" name="text" maxlength="2000" placeholder="Napisz uwagę (Enter = wyślij)"/>
            <button type="submit" class="btn mini">Wyślij</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
