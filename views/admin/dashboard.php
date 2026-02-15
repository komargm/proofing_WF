<?php
  /** @var int $user_id */
  /** @var array $stats */
  /** @var array $recent_albums */
  /** @var array $recent_events */

  $fn = trim((string)($_SESSION['user_first_name'] ?? ''));
  $hello = $fn !== '' ? ('Witaj, ' . $fn . '!') : ('Zalogowano jako user_id: ' . (int)$user_id);

  $stats = is_array($stats ?? null) ? $stats : [];
  $recent_albums = is_array($recent_albums ?? null) ? $recent_albums : [];
  $recent_events = is_array($recent_events ?? null) ? $recent_events : [];

  $fmtDate = static function ($v): string {
    if ($v === null || $v === '') return '—';
    try {
      return (new DateTime((string)$v))->format('Y-m-d');
    } catch (Throwable $e) {
      return '—';
    }
  };

  $fmtDateTime = static function ($v): string {
    if ($v === null || $v === '') return '—';
    try {
      return (new DateTime((string)$v))->format('Y-m-d H:i');
    } catch (Throwable $e) {
      return '—';
    }
  };
?>

<div class="dash-head">
  <div>
    <h1>Dashboard</h1>
    <p class="muted"><?= htmlspecialchars($hello, ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <div class="dash-actions">
    <a class="btn" href="/admin/profile">Mój profil</a>
  </div>
</div>

<div class="dash-grid">
  <section class="card">
    <h2>Szybkie akcje</h2>
    <div class="dash-quick">
      <a class="btn primary" href="/admin/albums/create">+ Utwórz album (Ingest)</a>
      <a class="btn" href="/admin/albums">Albumy</a>
      <a class="btn" href="/admin/users">Klienci</a>
      <a class="btn" href="/admin/audit">Logi (audit)</a>
    </div>

    <p class="muted" style="margin-top:10px;">
      Panel startowy — tu są skróty i szybki podgląd stanu systemu.
    </p>
  </section>

  <section class="card">
    <h2>Podsumowanie</h2>

    <div class="stats-grid">
      <div class="stat">
        <div class="stat-val"><?= (int)($stats['albums_total'] ?? 0) ?></div>
        <div class="stat-lbl">Albumy</div>
      </div>

      <div class="stat">
        <div class="stat-val"><?= (int)($stats['photos_total'] ?? 0) ?></div>
        <div class="stat-lbl">Zdjęcia</div>
      </div>

      <div class="stat">
        <div class="stat-val"><?= (int)($stats['users_active'] ?? 0) ?></div>
        <div class="stat-lbl">Klienci aktywni</div>
      </div>

      <div class="stat">
        <div class="stat-val"><?= (int)($stats['client_selected_total'] ?? 0) ?></div>
        <div class="stat-lbl">Wybrane (serduszko)</div>
      </div>

      <div class="stat">
        <div class="stat-val"><?= (int)($stats['download_enabled_total'] ?? 0) ?></div>
        <div class="stat-lbl">Odblokowane do pobrania</div>
      </div>

      <div class="stat">
        <div class="stat-val"><?= (int)($stats['comments_7d'] ?? 0) ?></div>
        <div class="stat-lbl">Komentarze (7 dni)</div>
      </div>
    </div>
  </section>


  <section class="card">
    <div class="dash-section-head">
      <h2>Ostatnie albumy</h2>
      <a class="btn mini" href="/admin/albums">Zobacz wszystkie</a>
    </div>

    <?php if (empty($recent_albums)): ?>
      <p class="muted">Brak danych (albo baza jeszcze pusta).</p>
    <?php else: ?>
      <div class="dash-list">
        <?php foreach ($recent_albums as $a): ?>
          <a class="dash-item" href="/admin/album/<?= (int)$a['id'] ?>/photos">
            <div class="dash-item-main">
              <div class="dash-item-title"><?= htmlspecialchars((string)($a['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="muted">Data: <?= htmlspecialchars($fmtDate($a['event_date'] ?? null), ENT_QUOTES, 'UTF-8') ?> • Utw.: <?= htmlspecialchars($fmtDateTime($a['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <span class="pill">ID: <?= (int)$a['id'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="dash-section-head">
      <h2>Ostatnie zdarzenia</h2>
      <a class="btn mini" href="/admin/audit">Pełny log</a>
    </div>

    <?php if (empty($recent_events)): ?>
      <p class="muted">Brak wpisów w audit_log.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Data</th>
              <th>Zdarzenie</th>
              <th>Użytkownik</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_events as $e): ?>
              <tr>
                <td><?= htmlspecialchars($fmtDateTime($e['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><code><?= htmlspecialchars((string)($e['event'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                <td class="muted"><?= htmlspecialchars((string)($e['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="muted"><?= htmlspecialchars((string)($e['ip'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
