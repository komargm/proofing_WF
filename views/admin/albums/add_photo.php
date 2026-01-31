<?php
  /** @var array<string,mixed> $album */
  $err = $_SESSION['flash_error'] ?? null;
  unset($_SESSION['flash_error']);

  $suggest = (string)($suggest_folder ?? '');
?>

<h1>Dodaj zdjęcie do albumu</h1>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/edit">← Ustawienia albumu</a>
  <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/photos">Podgląd zdjęć</a>
  <a class="btn" href="/admin/albums">Lista albumów</a>
</div>

<div class="card" style="max-width: 980px;">
  <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
    <div>
      <div><strong><?= htmlspecialchars((string)$album['title'], ENT_QUOTES, 'UTF-8') ?></strong></div>
      <div class="muted">Kod: <code><?= htmlspecialchars((string)($album['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></div>
    </div>
    <span class="pill blue">Pick from NAS</span>
  </div>

  <p class="muted" style="margin: 10px 0 0 0;">
    Wskaż <strong>1 plik JPG/JPEG</strong> z katalogu <code><?= htmlspecialchars((string)$root, ENT_QUOTES, 'UTF-8') ?></code>. System:
    dopnie zdjęcie do albumu (DB) i wygeneruje <strong>preview + miniaturę</strong>.
  </p>

  <?php if ($err): ?>
    <div class="alert" style="margin-top:12px;"><?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" action="/admin/album/<?= (int)$album['id'] ?>/add-photo/start" style="display:grid; gap:12px; margin-top:12px;">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>" />

    <label style="display:grid; gap:6px;">
      <div class="muted">Folder (relatywnie do PATH_ORIGINALS)</div>
      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <input class="input" id="folderInput" name="folder" placeholder="np. sesje/2026-01-31" value="<?= htmlspecialchars($suggest, ENT_QUOTES, 'UTF-8') ?>" style="min-width: 320px; flex:1;" />
        <button class="btn" type="button" id="loadBtn">Wczytaj listę JPG</button>
      </div>
      <div class="muted" style="margin-top:4px;">Tip: możesz wkleić ścieżkę folderu (bez początku <code><?= htmlspecialchars((string)$root, ENT_QUOTES, 'UTF-8') ?></code>).</div>
    </label>

    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
      <label style="display:flex; gap:8px; align-items:center;">
        <input type="checkbox" name="watermark" value="1" checked />
        <span>Dodaj watermark (preview)</span>
      </label>
      <span class="muted" id="countInfo">—</span>
    </div>

    <div id="fileList" style="display:grid; gap:8px;"></div>

    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-top:6px;">
      <button class="btn primary" type="submit" onclick="return confirm('Dodać to zdjęcie do albumu i wygenerować podglądy?');">Dodaj zdjęcie + start →</button>
      <a class="btn" href="/admin/album/<?= (int)$album['id'] ?>/edit">Anuluj</a>
    </div>

    <input type="hidden" name="filename" id="filenameInput" value="" />
  </form>
</div>

<script>
(function(){
  const loadBtn = document.getElementById('loadBtn');
  const folderInput = document.getElementById('folderInput');
  const listEl = document.getElementById('fileList');
  const infoEl = document.getElementById('countInfo');
  const filenameInput = document.getElementById('filenameInput');

  function rowRadio(name){
    const row = document.createElement('label');
    row.style.display='flex';
    row.style.gap='10px';
    row.style.alignItems='center';
    row.style.padding='8px 10px';
    row.style.border='1px solid #232329';
    row.style.borderRadius='12px';

    const rb = document.createElement('input');
    rb.type='radio';
    rb.name='__pick_one';
    rb.value=name;

    rb.onchange = () => { filenameInput.value = name; };

    const span = document.createElement('span');
    span.textContent = name;

    row.appendChild(rb);
    row.appendChild(span);
    return row;
  }

  async function load(){
    const folder = (folderInput.value || '').trim().replace(/^\/+/, '');
    listEl.innerHTML = '';
    infoEl.textContent = 'Ładowanie…';
    filenameInput.value = '';

    const res = await fetch(`/admin/album/<?= (int)$album['id'] ?>/add-photo/list?path=` + encodeURIComponent(folder), {headers:{'Accept':'application/json'}});
    const data = await res.json().catch(() => null);
    if (!data || data.ok !== true) {
      const err = data?.error || 'Błąd listowania plików.';
      listEl.innerHTML = '<div class="alert">' + err + '</div>';
      infoEl.textContent = '—';
      return;
    }

    const files = data.files || [];
    infoEl.textContent = 'Znaleziono: ' + files.length + ' JPG';

    if (files.length === 0) {
      listEl.innerHTML = '<div class="muted">Brak JPG w tym folderze.</div>';
      return;
    }

    files.forEach((f, idx) => {
      const row = rowRadio(f);
      listEl.appendChild(row);
      if (idx === 0) {
        const rb = row.querySelector('input[type=radio]');
        if (rb) {
          rb.checked = true;
          filenameInput.value = f;
        }
      }
    });
  }

  loadBtn.addEventListener('click', load);

  // auto-load jeśli mamy sugestię
  if ((folderInput.value || '').trim() !== '') {
    load();
  }
})();
</script>
