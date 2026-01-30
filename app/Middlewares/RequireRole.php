<?php
declare(strict_types=1);

final class RequireRole {
  public static function handle(string $role): callable {
    return function () use ($role): void {
      if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== $role) {
        Response::html('Forbidden', 403);
      }
    };
  }
}
