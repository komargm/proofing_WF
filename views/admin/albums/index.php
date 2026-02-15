<h1>Albumy</h1>

<div class="admin-albums-actions">
  <a class="btn" href="/admin/dashboard">← Dashboard</a>
  <a class="btn" href="/admin/albums/create">+ Utwórz album (Ingest Wizard)</a>
</div>

<div class="albums-desktop">
  <!-- DESKTOP/TABLET: tabela (bez zmian w układzie) -->
  <div class="card admin-albums-table">
    <div class="table-wrap admin-albums-table-wrap">
      <table class="admin-albums-table-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Kod</th>
            <th>Tytuł</th>
            <th>Utworzono</th>
            <th>Widoczność</th>
            <th>Status</th>
            <th class="admin-albums-col-actions">Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($albums)): ?>
            <tr>
              <td colspan="7" class="muted">Brak albumów w bazie.</td>
            </tr>
          <?php endif; ?>

          <?php foreach ($albums as $a): ?>
            <tr>
              <td><?= (int)$a['id'] ?></td>
              <td><code><?= htmlspecialchars((string)($a['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
              <td><?= htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$a['created_at'], ENT_QUOTES, 'UTF-8') ?></td>

              <td>
                <?php if (!empty($a['is_visible'])): ?>
                  <span class="badge badge--ok">widoczny</span>
                <?php else: ?>
                  <span class="badge badge--danger">ukryty</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if (!empty($a['is_archived'])): ?>
                  <span class="badge badge--muted">archiwum</span>
                <?php else: ?>
                  <span class="badge badge--ok">aktywny</span>
                <?php endif; ?>
              </td>

              <td class="admin-albums-col-actions">
                <a class="btn" href="/admin/album/<?= (int)$a['id'] ?>/edit">Ustawienia</a>
                <a class="btn" href="/admin/album/<?= (int)$a['id'] ?>/photos">Podgląd</a>

                <form method="post" action="/admin/album/<?= (int)$a['id'] ?>/delete" class="inline">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                  <button
                    type="submit"
                    class="btn danger"
                    onclick="return confirm('Na pewno usunąć ten album?\n\nUsunie to wpisy w DB oraz pliki preview/thumb.');"
                  >
                    Usuń
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="albums-mobile">
  <!-- MOBILE: karty (zero przewijania w poziomie) -->
  <div class="admin-albums-cards">
    <?php if (empty($albums)): ?>
      <div class="card admin-album-card">
        <div class="muted">Brak albumów w bazie.</div>
      </div>
    <?php endif; ?>

    <?php foreach ($albums as $a): ?>
      <div class="card admin-album-card">
        <div class="admin-album-head admin-album-head--highlight">
          <div class="admin-album-title">
            <?= htmlspecialchars((string)$a['title'], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="admin-album-id muted">#<?= (int)$a['id'] ?></div>
        </div>

        <div class="admin-album-meta">
          <div class="admin-album-row">
            <span class="muted">Kod</span>
            <code><?= htmlspecialchars((string)($a['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
          </div>
          <div class="admin-album-row">
            <span class="muted">Utworzono</span>
            <span><?= htmlspecialchars((string)$a['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>

          <div class="admin-album-badges">
            <?php if (!empty($a['is_visible'])): ?>
              <span class="badge badge--ok">widoczny</span>
            <?php else: ?>
              <span class="badge badge--danger">ukryty</span>
            <?php endif; ?>

            <?php if (!empty($a['is_archived'])): ?>
              <span class="badge badge--muted">archiwum</span>
            <?php else: ?>
              <span class="badge badge--ok">aktywny</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="admin-album-actions">
          <a class="btn" href="/admin/album/<?= (int)$a['id'] ?>/edit">Ustawienia</a>
          <a class="btn" href="/admin/album/<?= (int)$a['id'] ?>/photos">Podgląd</a>

          <form method="post" action="/admin/album/<?= (int)$a['id'] ?>/delete" class="inline">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <button
              type="submit"
              class="btn danger"
              onclick="return confirm('Na pewno usunąć ten album?\n\nUsunie to wpisy w DB oraz pliki preview/thumb.');"
            >
              Usuń
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
