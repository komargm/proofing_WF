<?php
  /** @var array $events */
  $events = is_array($events ?? null) ? $events : [];

  $fmtDateTime = static function ($v): string {
    if ($v === null || $v === '') return '—';
    try {
      return (new DateTime((string)$v))->format('Y-m-d H:i');
    } catch (Throwable $e) {
      return '—';
    }
  };
?>

<div class="toolbar">
  <div>
    <h1>Audit log</h1>
    <p class="muted">Ostatnie 200 zdarzeń. Filtrowanie i wyszukiwanie dołożymy w kolejnej fazie.</p>
  </div>
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a class="btn" href="/admin/dashboard">← Dashboard</a>
    <a class="btn" href="/admin/albums">Albumy</a>
  </div>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Data</th>
        <th>Zdarzenie</th>
        <th>Użytkownik</th>
        <th>IP</th>
        <th>Meta</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($events)): ?>
        <tr>
          <td colspan="6" class="muted">Brak danych.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($events as $e): ?>
          <?php
            $meta = (string)($e['meta_json'] ?? '');
            $len = function_exists('mb_strlen') ? mb_strlen($meta) : strlen($meta);
            if ($len > 140) {
              $meta = (function_exists('mb_substr') ? mb_substr($meta, 0, 140) : substr($meta, 0, 140)) . '…';
            }
          ?>
          <tr>
            <td><?= (int)($e['id'] ?? 0) ?></td>
            <td><?= htmlspecialchars($fmtDateTime($e['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
            <td><code><?= htmlspecialchars((string)($e['event'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
            <td class="muted"><?= htmlspecialchars((string)($e['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="muted"><?= htmlspecialchars((string)($e['ip'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="muted"><?= htmlspecialchars($meta !== '' ? $meta : '—', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
