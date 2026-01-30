<?php
  /** @var array<int,array<string,mixed>> $users */
  /** @var null|array $flash */
?>

<h1>Zarządzanie klientami</h1>
<p class="muted">Dodawanie kont jest po stronie admina. Klient nie ma rejestracji ani resetu hasła.</p>

<div style="margin: 16px 0; display:flex; gap:12px; flex-wrap:wrap;">
  <a class="btn primary" href="/admin/users/create">+ Dodaj klienta</a>
  <a class="btn" href="/admin/dashboard">← Panel admina</a>
</div>

<?php if ($flash): ?>
  <div class="card" style="border:1px solid #2b6cff;">
    <div><strong>Utworzono klienta</strong> (user_id: <?= (int)$flash['user_id'] ?>)</div>
    <div style="display:grid; gap:6px; margin-top:8px;">
      <div><span class="muted">Login (email):</span> <code><?= htmlspecialchars((string)$flash['email'], ENT_QUOTES, 'UTF-8') ?></code></div>
      <div><span class="muted">Hasło (pokaże się tylko raz):</span> <code><?= htmlspecialchars((string)$flash['password'], ENT_QUOTES, 'UTF-8') ?></code></div>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="padding:0; overflow:auto;">
  <table style="width:100%; border-collapse: collapse; min-width: 860px;">
    <thead>
      <tr style="background:#101014;">
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">ID</th>
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">Klient</th>
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">Email</th>
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">Telefon</th>
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">Status</th>
        <th style="text-align:left; padding:12px; border-bottom:1px solid #2a2a2e;">Utworzono</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$users): ?>
        <tr>
          <td colspan="6" style="padding:12px; border-bottom:1px solid #2a2a2e;" class="muted">Brak klientów.</td>
        </tr>
      <?php endif; ?>

      <?php foreach ($users as $u):
        $name = trim((string)($u['first_name'] ?? '') . ' ' . (string)($u['last_name'] ?? ''));
        if ($name === '') $name = '—';
        $status = ((int)($u['is_active'] ?? 0) === 1) ? 'Aktywny' : 'Nieaktywny';
      ?>
        <tr>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;"><?= (int)$u['id'] ?></td>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;"><?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?></td>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;"><?= htmlspecialchars((string)($u['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></td>
          <td style="padding:12px; border-bottom:1px solid #2a2a2e;"><?= htmlspecialchars((string)($u['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
