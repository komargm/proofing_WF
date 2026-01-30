<h1>Albumy</h1>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn" href="/admin/dashboard">← Dashboard</a>
  <a class="btn" href="/admin/albums/create">+ Utwórz album (Ingest Wizard)</a>
</div>

<div class="card" style="padding:0; overflow:auto;">
  <table style="width:100%; border-collapse: collapse; min-width: 980px;">
    <thead>
      <tr style="background:#101014;">
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">ID</th>
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">Kod</th>
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">Tytuł</th>
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">Utworzono</th>
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">Status</th>
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">Akcje</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($albums)): ?>
        <tr>
          <td colspan="6" style="padding:12px; border-bottom:1px solid #2a2a2e;" class="muted">Brak albumów w bazie.</td>
        </tr>
      <?php endif; ?>

      <?php foreach ($albums as $a): ?>
        <tr>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;"><?= (int)$a['id'] ?></td>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;"><code><?= htmlspecialchars((string)($a['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;">
            <?= htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8') ?>
          </td>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;">
            <?= htmlspecialchars((string)$a['created_at'], ENT_QUOTES, 'UTF-8') ?>
          </td>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;">
            <?php if (!empty($a['is_archived'])): ?>
              <span class="badge" style="background:#2a2a2e;">archiwum</span>
            <?php else: ?>
              <span class="badge" style="background:#163;">aktywny</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e; white-space:nowrap;">
            <a class="btn" href="/admin/album/<?= (int)$a['id'] ?>/edit">Ustawienia</a>
            <a class="btn" href="/admin/album/<?= (int)$a['id'] ?>/photos">Podgląd</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
