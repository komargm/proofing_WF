<h1>Mój profil (Admin)</h1>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/dashboard">← Wróć do panelu</a>
</div>

<?php if (!empty($ok)): ?>
  <div class="card" style="border-color: rgba(60,200,120,.35);">
    ✅ <?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php if (!empty($err)): ?>
  <div class="card" style="border-color: rgba(255,120,120,.45);">
    ⚠️ <?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<div style="display:grid; gap:16px; margin-top:16px;">
  <section class="card">
    <h2 style="margin:0;">Dane kontaktowe</h2>

    <form method="post" action="/admin/profile/update" style="display:grid; gap:10px;">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

      <label>Imię</label>
      <input type="text" name="first_name" value="<?= htmlspecialchars((string)($old['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="given-name" />

      <label>Nazwisko</label>
      <input type="text" name="last_name" value="<?= htmlspecialchars((string)($old['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="family-name" />

      <label>Telefon</label>
      <input type="text" name="phone" value="<?= htmlspecialchars((string)($old['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="tel" />

      <label>Messenger</label>
      <input type="text" name="messenger" value="<?= htmlspecialchars((string)($old['messenger'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />

      <label>Notatki</label>
      <textarea name="contact_notes"><?= htmlspecialchars((string)($old['contact_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn" type="submit">Zapisz profil</button>
      </div>

      <p class="muted" style="margin:0;">
        Email logowania: <b><?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></b>
      </p>
    </form>
  </section>

  <section class="card">
    <h2 style="margin:0;">Zmiana hasła (tylko admin)</h2>

    <form method="post" action="/admin/profile/password" style="display:grid; gap:10px;">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

      <label>Aktualne hasło</label>
      <input type="password" name="current_password" autocomplete="current-password" />

      <label>Nowe hasło</label>
      <input type="password" name="new_password" autocomplete="new-password" />

      <label>Powtórz nowe hasło</label>
      <input type="password" name="new_password2" autocomplete="new-password" />

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn" type="submit">Zmień hasło</button>
      </div>

      <p class="muted" style="margin:0;">Minimalnie 8 znaków. Klienci nie mają opcji zmiany hasła.</p>
    </form>
  </section>
</div>
