<?php /** @var array<string,mixed> $album */ ?>
<?php /** @var array<int,array<string,mixed>> $photos */ ?>
<?php /** @var array<int,array<string,mixed>> $sections */ ?>
<?php /** @var ?int $section_id */ ?>
<?php /** @var ?bool $filter_selected */ ?>
<?php /** @var null|int|'none' $filter_rating */ ?>

<?php
  $qp = [];
  if (!empty($section_id)) $qp['section'] = (int)$section_id;
  if ($filter_selected === true) $qp['selected'] = 1;
  if ($filter_rating === 'none') $qp['rating'] = 'none';
  if (is_int($filter_rating) && $filter_rating >= 1 && $filter_rating <= 6) $qp['rating'] = $filter_rating;
  $qs = !empty($qp) ? ('?' . http_build_query($qp)) : '';
?>

<h1>Podgląd albumu</h1>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/albums">← Lista albumów</a>
  <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/edit">Ustawienia</a>
  <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/sections">Sekcje</a>
  <a class="btn" href="/admin/dashboard">Dashboard</a>
</div>

<div class="card" style="margin-bottom:12px;">
  <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
    <div><strong><?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?></strong></div>
    <div class="muted">Zdjęcia: <?= count($photos) ?></div>
    <div style="margin-left:auto; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
      <label class="muted" for="js-section-filter">Sekcja:</label>
      <select id="js-section-filter" class="input" style="max-width:220px;">
        <option value="">Wszystko</option>
        <?php foreach ($sections as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (isset($section_id) && (int)$section_id === (int)$s['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)$s['title'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label style="display:flex; gap:6px; align-items:center; cursor:pointer;">
        <input id="js-selected-filter" type="checkbox" <?= ($filter_selected === true) ? 'checked' : '' ?> />
        <span class="muted">♥</span>
      </label>

      <label class="muted" for="js-rating-filter">Ocena:</label>
      <select id="js-rating-filter" class="input" style="max-width:170px;">
        <option value="">Wszystkie</option>
        <option value="none" <?= ($filter_rating === 'none') ? 'selected' : '' ?>>Brak oceny</option>
        <?php for ($i=1; $i<=6; $i++): ?>
          <option value="<?= $i ?>" <?= (is_int($filter_rating) && (int)$filter_rating === $i) ? 'selected' : '' ?>><?= $i ?></option>
        <?php endfor; ?>
      </select>

      <button type="button" class="btn mini" id="js-filter-reset">Reset</button>
    </div>
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
        <a class="photo-img" href="/admin/photo/<?= $pid ?><?= $qs ?>">
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

            <span class="pill js-admin-rating-pill" style="border-color:#7c3aed; color:#d8b4fe; <?= empty($p['admin_rating']) ? 'display:none;' : '' ?>">
              Twoja ocena: ★ <?= (int)($p['admin_rating'] ?? 0) ?>/6
            </span>

            <?php if (isset($p['is_visible']) && (int)$p['is_visible'] !== 1): ?>
              <span class="pill" style="border-color:#6b6b75; color:#bdbdc6;">Ukryte</span>
            <?php endif; ?>
          
            <form method="post" action="/admin/photo/<?= $pid ?>/delete<?= $qs ?>" class="delete-photo-form" style="margin-left:auto;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>" />
              <button type="submit" class="btn mini" style="border-color:#ff4d4f; color:#ff4d4f;">Usuń</button>
            </form>

          </div>

          <div class="rating-row admin-rating-row" data-photo-id="<?= $pid ?>" aria-label="Ocena admin">
            <?php for ($i=1; $i<=6; $i++): ?>
              <button type="button"
                      class="rate-btn admin-rate-btn <?= ((int)($p['admin_rating'] ?? 0) === $i) ? 'is-on' : '' ?>"
                      data-rating="<?= $i ?>">★</button>
            <?php endfor; ?>
            <button type="button" class="rate-clear admin-rate-clear" title="Wyczyść ocenę">×</button>
          </div>

          <div style="margin:8px 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <label class="muted" style="min-width:70px;">Sekcja:</label>
            <select class="input js-photo-section" data-photo-id="<?= $pid ?>" style="max-width:220px;">
              <option value="">— brak —</option>
              <?php foreach ($sections as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= (!empty($p['section_id']) && (int)$p['section_id'] === (int)$s['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars((string)$s['title'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="muted" style="font-size:12px; margin-top:-2px;">Zmiana zapisuje się od razu.</div>
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

      const base = `/admin/album/<?= (int)$album['id'] ?>/photos`;
      const sectionSel = document.getElementById('js-section-filter');
      const selectedCb = document.getElementById('js-selected-filter');
      const ratingSel  = document.getElementById('js-rating-filter');
      const resetBtn   = document.getElementById('js-filter-reset');

      const apply = () => {
        const p = new URLSearchParams();
        const sid = sectionSel?.value || '';
        const selected = !!selectedCb?.checked;
        const rating = ratingSel?.value || '';

        if (sid) p.set('section', sid);
        if (selected) p.set('selected', '1');
        if (rating) p.set('rating', rating);

        const qs = p.toString();
        window.location.href = qs ? `${base}?${qs}` : base;
      };

      sectionSel?.addEventListener('change', apply);
      selectedCb?.addEventListener('change', apply);
      ratingSel?.addEventListener('change', apply);
      resetBtn?.addEventListener('click', () => { window.location.href = base; });

      // szybkie przypisanie sekcji do zdjęcia
      document.addEventListener('change', async (e) => {
        const el = e.target;
        if (!el || !el.classList || !el.classList.contains('js-photo-section')) return;
        const pid = el.getAttribute('data-photo-id');
        const sid = el.value;
        if (!pid) return;

        try {
          el.disabled = true;
          const res = await fetch(`/admin/photo/${pid}/set-section`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ section_id: sid === '' ? null : parseInt(sid, 10) })
          });
          const json = await res.json().catch(() => null);
          if (!res.ok || !json || json.ok !== true) {
            alert('Nie udało się zapisać sekcji.');
          }
        } finally {
          el.disabled = false;
        }
      });

      document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (form.classList.contains('delete-photo-form')) {
          if (!confirm('Usunąć to zdjęcie z albumu? (usunie wpisy w DB + preview/thumb)')) {
            e.preventDefault();
          }
          return;
        }
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
