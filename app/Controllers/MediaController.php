<?php
declare(strict_types=1);

final class MediaController {
  public function __construct(private PhotoRepository $photos) {}

  public function photoFile(array $params): void {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    $kind = (string)($params['kind'] ?? '');

    $allowed = ['thumb', 'preview_800'];
    if ($photoId <= 0 || !in_array($kind, $allowed, true)) {
      Response::html('Bad Request', 400);
    }

    $path = $this->photos->filePathForUser($userId, $photoId, $kind);
    if (!$path) {
      Response::html('Forbidden', 403);
    }

    $real = $this->safeRealPath($path);
    if (!$real || !is_file($real)) {
      Response::html('Not Found', 404);
    }

    // Basic headers
    header('Content-Type: image/jpeg');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');

    // Stream
    $fp = fopen($real, 'rb');
    if ($fp === false) {
      Response::html('Not Found', 404);
    }
    fpassthru($fp);
    fclose($fp);
    exit;
  }

  /**
   * Minimalna ochrona ścieżek: dopuszczamy tylko pliki wewnątrz mountów w kontenerze.
   * Dostosuj jeśli trzymasz inne mounty.
   */
  private function safeRealPath(string $path): ?string {
    $real = realpath($path);
    if (!$real) return null;

    $allowedRoots = [
      '/var/www/photos/previews',
      '/var/www/photos/originals',
    ];

    foreach ($allowedRoots as $root) {
      $rootReal = realpath($root);
      if ($rootReal && str_starts_with($real, $rootReal . DIRECTORY_SEPARATOR)) {
        return $real;
      }
    }
    return null;
  }
}
