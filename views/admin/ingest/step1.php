<?php
  $err = $_SESSION['flash_error'] ?? null;
  unset($_SESSION['flash_error']);
?>

<h1>Kreator albumu — krok 1/4</h1>
<p class="muted">Dane albumu + przypisanie klienta.</p>

<?php if ($err): ?>
  <div class="alert"><?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post" action="/admin/albums/create/step1" class="card" style="max-width:720px;">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />

  <div>
    <label>Nazwa albumu (opcjonalnie)</label>
    <input name="title" value="<?= htmlspecialchars((string)($state['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="np. Sesja rodzinna" />
  </div>

  <div>
    <label>Klient (wymagane)</label>
    <select name="client_user_id" style="padding:10px 12px;border-radius:10px;border:1px solid #2a2a2e;background:#0f0f12;color:#f2f2f2;">
      <option value="">— wybierz —</option>
      <?php foreach ($clients as $c):
        $label = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
        if ($label === '') $label = $c['email'];
        $sel = ((int)($state['client_user_id'] ?? 0) === (int)$c['id']) ? 'selected' : '';
      ?>
        <option value="<?= (int)$c['id'] ?>" <?= $sel ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div>
    <label>Komentarz do albumu (wymagane)</label>
    <input name="album_comment" value="<?= htmlspecialchars((string)($state['album_comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="np. Proszę wybrać 20 zdjęć" />
  </div>

  <div style="display:flex; gap:10px;">
    <button class="btn primary" type="submit">Dalej →</button>
    <a class="btn" href="/admin/dashboard">Anuluj</a>
  </div>
</form>
