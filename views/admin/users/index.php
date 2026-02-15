<?php
  /** @var array<int,array<string,mixed>> $users */
  /** @var null|array $flash */
?>

<h1>Zarządzanie klientami</h1>
<p class="muted">Dodawanie kont jest po stronie admina. Klient nie ma rejestracji ani resetu hasła.</p>

<div class="toolbar">
  <div></div>
  <div class="toolbar-actions">
    <a class="btn primary" href="/admin/users/create">+ Dodaj klienta</a>
    <a class="btn" href="/admin/dashboard">← Panel admina</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="card flash-card">
    <?php if (!empty($flash['user_id'])): ?>
      <div><strong>Utworzono klienta</strong> (user_id: <?= (int)$flash['user_id'] ?>)</div>
      <div class="flash-grid">
        <div><span class="muted">Login (email):</span> <code><?= htmlspecialchars((string)$flash['email'], ENT_QUOTES, 'UTF-8') ?></code></div>
        <div><span class="muted">Hasło (pokaże się tylko raz):</span> <code><?= htmlspecialchars((string)$flash['password'], ENT_QUOTES, 'UTF-8') ?></code></div>
      </div>
    <?php elseif (!empty($flash['updated_user_id'])): ?>
      <div><strong>Zapisano zmiany klienta</strong> (user_id: <?= (int)$flash['updated_user_id'] ?>)</div>
      <div class="muted flash-sub">Email: <?= htmlspecialchars((string)($flash['updated_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= !empty($flash['password_changed']) ? ' • hasło zmienione' : '' ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="users-desktop">
  <div class="table-wrap admin-users-table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Klient</th>
          <th>Email</th>
          <th>Telefon</th>
          <th>Status</th>
          <th>Utworzono</th>
          <th class="admin-users-col-actions">Akcje</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$users): ?>
          <tr>
            <td colspan="7" class="muted">Brak klientów.</td>
          </tr>
        <?php endif; ?>

        <?php foreach ($users as $u):
          $name = trim((string)($u['first_name'] ?? '') . ' ' . (string)($u['last_name'] ?? ''));
          if ($name === '') $name = '—';
        ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($u['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php if (((int)($u['is_active'] ?? 0) === 1)): ?>
                <span class="badge badge--ok">aktywny</span>
              <?php else: ?>
                <span class="badge badge--muted">nieaktywny</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)($u['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="admin-users-col-actions">
              <a class="btn" href="/admin/users/<?= (int)$u['id'] ?>/edit">Edytuj</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="users-mobile">
  <div class="admin-users-cards">
    <?php if (!$users): ?>
      <div class="card admin-user-card">
        <div class="muted">Brak klientów.</div>
      </div>
    <?php endif; ?>

    <?php foreach ($users as $u):
      $name = trim((string)($u['first_name'] ?? '') . ' ' . (string)($u['last_name'] ?? ''));
      if ($name === '') $name = '—';
      $isActive = ((int)($u['is_active'] ?? 0) === 1);
    ?>
      <div class="card admin-user-card admin-card-hover">
        <div class="admin-user-head admin-card-head--highlight">
          <div class="admin-user-title"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="admin-user-id muted">#<?= (int)$u['id'] ?></div>
        </div>

        <div class="admin-user-meta">
          <div class="admin-kv">
            <span class="muted">Email</span>
            <span><code><?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?></code></span>
          </div>
          <div class="admin-kv">
            <span class="muted">Telefon</span>
            <span><?= htmlspecialchars((string)($u['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="admin-kv">
            <span class="muted">Utworzono</span>
            <span><?= htmlspecialchars((string)($u['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="admin-user-badges">
            <?php if ($isActive): ?>
              <span class="badge badge--ok">aktywny</span>
            <?php else: ?>
              <span class="badge badge--muted">nieaktywny</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="admin-user-actions">
          <a class="btn" href="/admin/users/<?= (int)$u['id'] ?>/edit">Edytuj</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
