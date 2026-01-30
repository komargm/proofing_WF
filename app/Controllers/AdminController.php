<?php
declare(strict_types=1);

final class AdminController {
  public function dashboard(): void {
    Response::html(View::page('admin/dashboard', [
      'user_id' => (int)($_SESSION['user_id'] ?? 0),
    ]));
  }
}
