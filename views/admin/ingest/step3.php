<?php
  $err = $_SESSION['flash_error'] ?? null;
  unset($_SESSION['flash_error']);
  $folder = (string)($state['folder'] ?? '');
?>

<h1>Kreator albumu — krok 3/4</h1>
<p class="muted">Selekcja JPG z folderu: <strong><?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?></strong></p>

<?php if ($err): ?>
  <div class="alert"><?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post" action="/admin/albums/create/finalize" class="card" style="max-width: 980px;">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />

  <div style="display:flex; gap:10px; flex-wrap: wrap; align-items:center; justify-content: space-between;">
    <div style="display:flex; gap:10px; flex-wrap: wrap; align-items:center;">
      <a class="btn" href="/admin/albums/create/source">← Wstecz</a>
      <span class="pill blue">Krok 3</span>
      <span class="muted" id="countInfo">Ładowanie listy…</span>
    </div>
    <div style="display:flex; gap:10px; flex-wrap: wrap; align-items:center;">
      <label style="display:flex; gap:8px; align-items:center;">
        <input type="checkbox" name="watermark" value="1" checked />
        <span>Dodaj watermark (preview)</span>
      </label>
      <label style="display:flex; gap:8px; align-items:center;">
        <input type="checkbox" name="delete_unselected" value="1" />
        <span>Usuń niezatwierdzone podglądy (tymczasowe)</span>
      </label>
    </div>
  </div>

  <div style="display:flex; gap:10px; align-items:center; flex-wrap: wrap;">
    <button class="btn" type="button" id="selectAll">Zaznacz wszystkie</button>
    <button class="btn" type="button" id="selectNone">Odznacz wszystkie</button>
    <button class="btn primary" type="submit">Utwórz album + start →</button>
  </div>

  <div id="fileList" style="display:grid; gap:8px; margin-top: 10px;"></div>
</form>

<script>
(function(){
  const folder = <?= json_encode($folder, JSON_UNESCAPED_SLASHES) ?>;
  const listEl = document.getElementById('fileList');
  const infoEl = document.getElementById('countInfo');

  function makeRow(name){
    const row = document.createElement('label');
    row.style.display='flex';
    row.style.gap='10px';
    row.style.alignItems='center';
    row.style.padding='8px 10px';
    row.style.border='1px solid #232329';
    row.style.borderRadius='12px';

    const cb = document.createElement('input');
    cb.type='checkbox';
    cb.name='selected[]';
    cb.value=name;
    cb.checked=true;

    const span = document.createElement('span');
    span.textContent=name;

    row.appendChild(cb);
    row.appendChild(span);
    return row;
  }

  async function load(){
    const res = await fetch('/admin/albums/create/select/list?folder=' + encodeURIComponent(folder), {headers:{'Accept':'application/json'}});
    const data = await res.json();
    if (!data.ok) {
      listEl.innerHTML = '<div class="alert">' + (data.error || 'Błąd') + '</div>';
      infoEl.textContent = 'Błąd listowania plików';
      return;
    }
    const files = data.files || [];
    infoEl.textContent = 'Znaleziono: ' + files.length + ' JPG';
    listEl.innerHTML = '';
    files.forEach(f => listEl.appendChild(makeRow(f)));
  }

  document.getElementById('selectAll').onclick = () => {
    listEl.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = true);
  };
  document.getElementById('selectNone').onclick = () => {
    listEl.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);
  };

  load();
})();
</script>
