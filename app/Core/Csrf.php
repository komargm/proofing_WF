<?php
declare(strict_types=1);

final class Csrf {
  public static function token(): string {
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
  }

  public static function verifyOrFail(?string $token): void {
    $ok = is_string($token)
      && isset($_SESSION['csrf_token'])
      && hash_equals((string)$_SESSION['csrf_token'], $token);

    if (!$ok) {
      Response::html('Bad Request', 400);
    }
  }

  /**
   * Skrót używany w kontrolerach.
   * Obsługuje pole POST _csrf albo nagłówek X-CSRF-Token.
   */
  public static function validate(): void {
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    self::verifyOrFail(is_string($token) ? $token : null);
  }
}
