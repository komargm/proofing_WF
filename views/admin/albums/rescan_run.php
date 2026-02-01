<h1>Rescan albumu — logi na żywo</h1>
<p class="muted">
  Album ID: <strong><?= (int)$album_id ?></strong>
  · Job: <strong><?= htmlspecialchars((string)$job_id, ENT_QUOTES, 'UTF-8') ?></strong>
</p>

<div class="card" style="max-width: 980px;">
  <div style="display:flex; gap:10px; flex-wrap: wrap; align-items:center;">
    <a class="btn" href="/admin/album/<?= (int)$album_id ?>/edit">← Ustawienia albumu</a>
    <a class="btn" href="/admin/album/<?= (int)$album_id ?>/photos">Podgląd zdjęć</a>
    <span class="pill green" id="statusPill">W trakcie…</span>
    <span class="muted" id="hint">Jeśli okno jest puste, odśwież stronę po kilku sekundach.</span>
  </div>

  <pre id="logBox" style="white-space:pre-wrap; background:#0f0f12; border:1px solid #232329; border-radius: 14px; padding: 12px; max-height: 60vh; overflow:auto;"></pre>
</div>

<script>
(function(){
  const box = document.getElementById('logBox');
  const pill = document.getElementById('statusPill');
  const es = new EventSource('/admin/album/<?= (int)$album_id ?>/rescan/stream/<?= htmlspecialchars((string)$job_id, ENT_QUOTES, 'UTF-8') ?>');

  es.onmessage = (e) => {
    if (!e.data) return;
    const line = e.data;
    box.textContent += line + "\n";
    box.scrollTop = box.scrollHeight;

    if (line.includes('[WF] DONE')) {
      pill.textContent = 'Gotowe';
      pill.classList.remove('green');
      pill.classList.add('blue');
      es.close();
    }
    if (line.includes('[WF] ERROR')) {
      pill.textContent = 'Błąd';
    }
  };
})();
</script>
