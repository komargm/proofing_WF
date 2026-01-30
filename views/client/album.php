<a class="btn" href="/client/dashboard">← Wróć</a>

<h1><?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?></h1>
<p class="muted">Utworzono: <?= htmlspecialchars((string)$album['created_at'], ENT_QUOTES, 'UTF-8') ?></p>

<div class="card">
  <p class="muted">Faza 3: tutaj będzie grid zdjęć + serca + komentarze pod miniaturami + lightbox.</p>
</div>
