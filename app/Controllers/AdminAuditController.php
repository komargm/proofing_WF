<?php
declare(strict_types=1);

final class AdminAuditController {
  public function index(): void {
    $pdo = db();
    $events = [];

    try {
      $events = $pdo->query(
        "SELECT a.id, a.user_id, a.event, a.meta_json, a.ip, a.created_at, u.email\n" .
        "FROM audit_log a\n" .
        "LEFT JOIN users u ON u.id = a.user_id\n" .
        "ORDER BY a.id DESC\n" .
        "LIMIT 200"
      )->fetchAll() ?: [];
    } catch (Throwable $e) {
      $events = [];
    }

    Response::html(View::page('admin/audit', [
      'events' => $events,
    ]));
  }
}
