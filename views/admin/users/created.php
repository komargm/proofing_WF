<h1>Utworzono konto klienta</h1>

<div class="alert alert-ok">
  <p><strong>Gotowe.</strong> Poniższe hasło jest widoczne tylko teraz – skopiuj je i przekaż klientowi.</p>
</div>

<div class="card">
  <p><strong>ID:</strong> <?= (int)$user_id ?></p>
  <p><strong>Email:</strong> <?= htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8') ?></p>
  <p><strong>Hasło:</strong> <code style="font-size: 16px;"><?= htmlspecialchars((string)$password, ENT_QUOTES, 'UTF-8') ?></code></p>
</div>

<div style="margin: 16px 0;">
  <a class="btn" href="/admin/users">← Wróć do listy klientów</a>
  <a class="btn" href="/admin/albums/create">+ Utwórz album (Ingest)</a>
</div>
