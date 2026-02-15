<?php
declare(strict_types=1);

final class AdminController {
  public function dashboard(): void {
    $userId = (int)($_SESSION['user_id'] ?? 0);

    // Dashboard ma być "lekki" i bezpieczny: wyłącznie SELECT-y.
    $pdo = db();

    $stats = [
      'users_total' => 0,
      'users_active' => 0,
      'albums_total' => 0,
      'photos_total' => 0,
      'client_selected_total' => 0,
      'download_enabled_total' => 0,
      'comments_7d' => 0,
    ];

    try {
      $stats['users_total'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
      $stats['users_active'] = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
      $stats['albums_total'] = (int)$pdo->query('SELECT COUNT(*) FROM albums')->fetchColumn();
      $stats['photos_total'] = (int)$pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn();
      $stats['client_selected_total'] = (int)$pdo->query('SELECT COUNT(*) FROM photos WHERE client_selected_at IS NOT NULL')->fetchColumn();
      $stats['download_enabled_total'] = (int)$pdo->query('SELECT COUNT(*) FROM photos WHERE download_allowed_at IS NOT NULL')->fetchColumn();

      $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM photo_comments WHERE is_admin_note = 0 AND created_at >= (NOW() - INTERVAL 7 DAY)'
      );
      $stmt->execute();
      $stats['comments_7d'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
      // Nie blokuj widoku, jeśli DB chwilowo nie odpowie.
    }

    $recentAlbums = [];
    $recentEvents = [];

    try {
      $recentAlbums = $pdo
        ->query('SELECT id, title, event_date, created_at FROM albums ORDER BY id DESC LIMIT 6')
        ->fetchAll() ?: [];

      $recentEvents = $pdo
        ->query(
          "SELECT a.id, a.event, a.ip, a.created_at, u.email\n" .
          "FROM audit_log a\n" .
          "LEFT JOIN users u ON u.id = a.user_id\n" .
          "ORDER BY a.id DESC\n" .
          "LIMIT 10"
        )
        ->fetchAll() ?: [];
    } catch (Throwable $e) {
      // j.w.
    }

    Response::html(View::page('admin/dashboard', [
      'user_id' => $userId,
      'stats' => $stats,
      'recent_albums' => $recentAlbums,
      'recent_events' => $recentEvents,
    ]));
  }
}
