<?php
  $err = $_SESSION['flash_error'] ?? null;
  unset($_SESSION['flash_error']);
?>

<h1>Kreator albumu — krok 2/4</h1>
<p class="muted">Wybierz folder źródłowy z <?= htmlspecialchars((string)$root, ENT_QUOTES, 'UTF-8') ?>.</p>

<?php if ($err): ?>
  <div class="alert"><?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card" style="max-width: 860px;">
  <div style="display:flex; gap:10px; flex-wrap: wrap; align-items:center;">
    <a class="btn" href="/admin/albums/create">← Wstecz</a>
    <span class="pill blue">Krok 2</span>
    <span class="muted">Aktualnie: <strong id="curPath">/</strong></span>
  </div>

  <div id="dirList" style="display:grid; gap:8px;"></div>

  <form method="post" action="/admin/albums/create/source" id="pickForm" style="display:flex; gap:10px; align-items:center; flex-wrap: wrap;">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="folder" id="folderInput" value="<?= htmlspecialchars((string)($state['folder'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
    <button class="btn primary" type="submit">Dalej →</button>
  </form>
</div>

<script>
(function(){
  const curPathEl = document.getElementById('curPath');
  const listEl = document.getElementById('dirList');
  const folderInput = document.getElementById('folderInput');

  let curRel = '';

  function render(items){
    listEl.innerHTML = '';

    const header = document.createElement('div');
    header.className = 'muted';
    header.textContent = 'Kliknij folder, aby wejść. Kliknij “Wybierz”, aby zatwierdzić.';
    listEl.appendChild(header);

    if (curRel !== '') {
      const up = document.createElement('button');
      up.type = 'button';
      up.className = 'btn';
      up.textContent = '↑ .. (wyżej)';
      up.onclick = () => {
        const parts = curRel.split('/').filter(Boolean);
        parts.pop();
        curRel = parts.join('/');
        load();
      };
      listEl.appendChild(up);
    }

    items.forEach(name => {
      const row = document.createElement('div');
      row.style.display = 'flex';
      row.style.gap = '10px';
      row.style.alignItems = 'center';

      const go = document.createElement('button');
      go.type = 'button';
      go.className = 'btn';
      go.textContent = '📁 ' + name;
      go.onclick = () => {
        curRel = curRel ? (curRel + '/' + name) : name;
        load();
      };

      const pick = document.createElement('button');
      pick.type = 'button';
      pick.className = 'btn primary';
      pick.textContent = 'Wybierz';
      pick.onclick = () => {
        const selected = curRel ? (curRel + '/' + name) : name;
        folderInput.value = selected;
        document.getElementById('pickForm').submit();
      };

      row.appendChild(go);
      row.appendChild(pick);
      listEl.appendChild(row);
    });
  }

  async function load(){
    curPathEl.textContent = '/' + curRel;
    const res = await fetch('/admin/albums/create/source/list?path=' + encodeURIComponent(curRel), {headers:{'Accept':'application/json'}});
    const data = await res.json();
    if (!data.ok) {
      listEl.innerHTML = '<div class="alert">' + (data.error || 'Błąd') + '</div>';
      return;
    }
    render(data.dirs || []);
  }

  load();
})();
</script>
