<h1>Logowanie</h1>

<?php if (!empty($error)): ?>
  <div class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post" action="/login" class="card">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />

  <label>Email</label>
  <input name="email" type="email" autocomplete="username" required />

  <label>Hasło</label>
  <input name="password" type="password" autocomplete="current-password" required />

  <button class="btn primary" type="submit">Zaloguj</button>

  <p class="muted">Brak resetu hasła — w razie potrzeby skontaktuj się z fotografem.</p>
</form>
