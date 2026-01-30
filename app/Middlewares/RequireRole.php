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

  /** Pozwala na dostęp dla jednej z wielu ról (np. admin lub client). */
  public static function handleAny(array $roles): callable {
    return function () use ($roles): void {
      $cur = (string)($_SESSION['user_role'] ?? '');
      if ($cur === '' || !in_array($cur, $roles, true)) {
        Response::html('Forbidden', 403);
      }
    };
  }
}
