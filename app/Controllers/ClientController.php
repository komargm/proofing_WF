<?php
declare(strict_types=1);

final class ClientController {
  public function dashboard(): void {
    Response::html(View::page('client/dashboard', [
      'user_id' => (int)($_SESSION['user_id'] ?? 0),
    ]));
  }
}
