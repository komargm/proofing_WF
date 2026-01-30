<h1>Panel admina</h1>

<p>Zalogowano jako user_id: <?= (int)$user_id ?></p>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/albums/create">+ Utwórz album (Ingest Wizard)</a>
  <a class="btn" href="/client/dashboard">Podgląd jako klient</a>
</div>

<p class="muted">
  Ten dashboard jest na razie prosty — pełny panel (users/albums/manage) dojdzie w kolejnych fazach.
</p>
