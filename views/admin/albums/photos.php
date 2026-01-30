<?php /** @var array<string,mixed> $album */ ?>
<?php /** @var array<int,array<string,mixed>> $photos */ ?>

<h1>Podgląd albumu</h1>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/albums">← Lista albumów</a>
  <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/edit">Ustawienia</a>
  <a class="btn" href="/admin/dashboard">Dashboard</a>
</div>

<div class="card" style="margin-bottom:12px;">
  <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
    <div><strong><?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?></strong></div>
    <div class="muted">Zdjęcia: <?= count($photos) ?></div>
  </div>
  <?php if (!empty($album['album_comment'])): ?>
    <div class="muted" style="margin-top:8px; white-space:pre-wrap;">
      <?= htmlspecialchars((string)$album['album_comment'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>
</div>

<?php if (empty($photos)): ?>
  <p class="muted">Brak zdjęć w albumie.</p>
<?php else: ?>
  <div class="photo-grid">
    <?php foreach ($photos as $p): ?>
      <?php $pid = (int)$p['id']; ?>
      <div class="photo-tile" data-photo-id="<?= $pid ?>">
        <a class="photo-img" href="/media/photo/<?= $pid ?>/preview_800" target="_blank" rel="noopener">
          <?php if (!empty($p['thumb_path'])): ?>
            <img loading="lazy" alt="thumb" src="/media/photo/<?= $pid ?>/thumb" />
          <?php else: ?>
            <div class="thumb-missing">Brak miniatury</div>
          <?php endif; ?>

          <?php if (!empty($p['client_selected_at'])): ?>
            <div class="heart is-on" aria-label="Wybrane" style="pointer-events:none;">♥</div>
          <?php else: ?>
            <div class="heart" aria-label="Nie wybrane" style="pointer-events:none; opacity:.35;">♥</div>
          <?php endif; ?>
        </a>

        <div class="photo-meta">
          <div class="meta-row">
            <span class="pill js-selected-pill <?= !empty($p['client_selected_at']) ? 'green' : '' ?>">
              <?= !empty($p['client_selected_at']) ? 'Wybrane' : 'Nie wybrane' ?>
            </span>

            <span class="pill blue js-rating-pill" <?= empty($p['client_rating']) ? 'style="display:none"' : '' ?>>
              ★ <span class="js-rating-val"><?= (int)($p['client_rating'] ?? 0) ?></span>/6
            </span>

            <?php if (isset($p['is_visible']) && (int)$p['is_visible'] !== 1): ?>
              <span class="pill" style="border-color:#6b6b75; color:#bdbdc6;">Ukryte</span>
            <?php endif; ?>
          </div>

          <div class="comment-line js-last-comment" id="last-comment-<?= $pid ?>">
            <?php if (!empty($p['last_comment_text'])): ?>
              <?= htmlspecialchars((string)$p['last_comment_text'], ENT_QUOTES, 'UTF-8') ?>
            <?php else: ?>
              <span class="muted">Brak komentarza</span>
            <?php endif; ?>
          </div>

          <form class="admin-comment-form" data-photo-id="<?= $pid ?>" style="display:flex; gap:8px;">
            <input class="input" type="text" name="text" maxlength="2000" placeholder="Odpowiedz / dodaj uwagę (Enter = wyślij)" />
            <button type="submit" class="btn mini">Wyślij</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
    (() => {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!form.classList.contains('admin-comment-form')) return;
        e.preventDefault();

        const pid = form.getAttribute('data-photo-id');
        const input = form.querySelector('input[name="text"]');
        const btn = form.querySelector('button[type="submit"]');
        const text = (input?.value || '').trim();
        if (!pid || !text) return;

        try {
          if (btn) btn.disabled = true;
          const res = await fetch(`/admin/photo/${pid}/comment`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ text })
          });

          const json = await res.json().catch(() => null);
          if (!res.ok || !json || json.ok !== true) {
            alert('Nie udało się dodać komentarza.');
            return;
          }

          const box = document.getElementById(`last-comment-${pid}`);
          if (box) box.textContent = json.comment?.comment_text || text;
          if (input) input.value = '';
        } finally {
          if (btn) btn.disabled = false;
        }
      });
    })();
  </script>
<?php endif; ?>
