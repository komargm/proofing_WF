<a class="btn" href="/client/dashboard">← Wróć</a>

<h1><?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?></h1>
<p class="muted">Utworzono: <?= htmlspecialchars((string)$album['created_at'], ENT_QUOTES, 'UTF-8') ?></p>

<?php
  /** @var ?bool $filter_selected */
  /** @var null|int|'none' $filter_rating */
  $qp = [];
  if (!empty($section_id)) $qp['section'] = (int)$section_id;
  if ($filter_selected === true) $qp['selected'] = 1;
  if ($filter_rating === 'none') $qp['rating'] = 'none';
  if (is_int($filter_rating) && $filter_rating >= 1 && $filter_rating <= 6) $qp['rating'] = $filter_rating;
  $qs = !empty($qp) ? ('?' . http_build_query($qp)) : '';
?>

<div class="toolbar">
  <div class="muted">Zdjęcia: <?= (int)$count ?></div>

  <div style="margin-left:auto; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <label class="muted" for="js-section-filter">Sekcja:</label>
    <select id="js-section-filter" class="input" style="max-width:220px;">
      <option value="">Wszystko</option>
      <?php foreach (($sections ?? []) as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= (!empty($section_id) && (int)$section_id === (int)$s['id']) ? 'selected' : '' ?>>
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

  <div class="muted">Kliknij ♥, ustaw ocenę, dodaj komentarz.</div>
</div>

<?php if (empty($photos)): ?>
  <p class="muted">Brak zdjęć w albumie (albo nie są jeszcze widoczne).</p>
<?php else: ?>
  <div class="photo-grid">
    <?php
      $photographer = trim((string)($album['photographer_first_name'] ?? ''));
      if ($photographer === '') $photographer = 'Fotograf';
    ?>
    <?php foreach ($photos as $p): ?>
      <?php
        $pid = (int)$p['id'];
        $cr = (int)($p['client_rating'] ?? 0);
        $ar = (int)($p['admin_rating'] ?? 0);
        $match = ($cr >= 2 && $ar >= 2);
      ?>
      <div class="photo-tile"
           data-photo-id="<?= $pid ?>"
           data-admin-rating="<?= $ar ?>"
           data-photographer-name="<?= htmlspecialchars($photographer, ENT_QUOTES, 'UTF-8') ?>">
        <a class="photo-img" href="/client/photo/<?= $pid ?><?= $qs ?>">
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

            <span class="pill green js-match-pill" <?= $match ? '' : 'style="display:none"' ?>>
              👍 <?= htmlspecialchars($photographer, ENT_QUOTES, 'UTF-8') ?> też lubi
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

<script>
  (() => {
    const base = `/client/album/<?= (int)$album['id'] ?>`;
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
  })();
</script>
