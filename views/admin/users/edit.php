<?php
  /** @var string|null $err */
  /** @var array $user */
  /** @var array $old */
?>

<h1>Edytuj klienta (ID: <?= (int)$user['id'] ?>)</h1>
<p class="muted">Możesz zaktualizować dane kontaktowe, status konta oraz (opcjonalnie) hasło.</p>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/users">← Wróć do listy</a>
  <a class="btn" href="/admin/dashboard">Panel admina</a>
</div>

<?php if (!empty($err)): ?>
  <div class="alert"><?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post" action="/admin/users/<?= (int)$user['id'] ?>/edit" class="card" style="max-width: 820px;">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

  <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
    <div>
      <label>Email (login) *</label>
      <input type="email" name="email" required value="<?= htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
    </div>

    <div>
      <label>Nowe hasło (opcjonalnie)</label>
      <input type="text" name="password" value="" placeholder="Zostaw puste, aby nie zmieniać" />
      <p class="muted" style="margin:6px 0 0;">Jeśli wpiszesz hasło – zostanie ustawione od razu (nie pokazujemy go ponownie).</p>
    </div>
  </div>

  <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
    <div>
      <label>Imię</label>
      <input name="first_name" value="<?= htmlspecialchars((string)($old['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
    </div>
    <div>
      <label>Nazwisko</label>
      <input name="last_name" value="<?= htmlspecialchars((string)($old['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
    </div>
  </div>

  <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
    <div>
      <label>Telefon</label>
      <input name="phone" value="<?= htmlspecialchars((string)($old['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
    </div>
    <div>
      <label>Messenger / kontakt (opcjonalnie)</label>
      <input name="messenger" value="<?= htmlspecialchars((string)($old['messenger'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="np. IG: @..." />
    </div>
  </div>

  <div>
    <label>Notatki kontaktowe</label>
    <input name="contact_notes" value="<?= htmlspecialchars((string)($old['contact_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="np. preferencje kontaktu" />
  </div>

  <label style="display:flex; align-items:center; gap:10px;">
    <input type="checkbox" name="is_active" value="1" <?= ((int)($old['is_active'] ?? 1) === 1) ? 'checked' : '' ?> />
    <span>Aktywne konto (może się logować)</span>
  </label>

  <div style="display:flex; gap:10px; margin-top:6px;">
    <button class="btn primary" type="submit">Zapisz zmiany</button>
    <a class="btn" href="/admin/users">Anuluj</a>
  </div>
</form>
