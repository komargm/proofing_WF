<?php
declare(strict_types=1);

final class RequireAuth {
  public static function handle(): callable {
    return function (): void {
      if (empty($_SESSION['user_id'])) {
        Response::redirect('/login');
      }
    };
  }
}
