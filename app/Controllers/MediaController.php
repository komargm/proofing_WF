<?php
declare(strict_types=1);

final class MediaController {
  public function __construct(private PhotoRepository $photos) {}

  public function photoFile(array $params): void {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $role = (string)($_SESSION['user_role'] ?? '');
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    $kind = (string)($params['kind'] ?? '');

    $allowed = ['thumb', 'preview_800'];
    if ($photoId <= 0 || !in_array($kind, $allowed, true)) {
      Response::html('Bad Request', 400);
    }

    if ($role === 'admin') {
      $path = $this->photos->filePathForAdmin($photoId, $kind);
    } else {
      $path = $this->photos->filePathForUser($userId, $photoId, $kind);
    }
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


  
  public function downloadOriginal(array $params): void {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $role = (string)($_SESSION['user_role'] ?? '');
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($photoId <= 0) {
      Response::html('Bad Request', 400);
    }

    // --- KLUCZ: nie blokuj sesji i nie daj się ubić timeoutem ---
    @set_time_limit(0);
    @ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close(); // uwalnia lock sesji (ważne przy wielu requestach)
    }
    while (ob_get_level() > 0) { @ob_end_clean(); } // usuń bufory, żeby bajty leciały od razu

    if ($role === 'admin') {
      $path = $this->photos->filePathForAdmin($photoId, 'original_jpg');
    } else {
      $path = $this->photos->originalPathIfAllowedForUser($userId, $photoId);
    }
    if (!$path) {
      Response::html('Forbidden', 403);
    }

    $real = $this->safeRealPath($path);
    if (!$real || !is_file($real)) {
      Response::html('Not Found', 404);
    }

    $name = basename($real);
    $size = @filesize($real);

    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
    header('Cache-Control: private, no-transform, max-age=0');
    header('X-Accel-Buffering: no');
    if ($size !== false) {
      header('Content-Length: ' . $size);
    }

    $fp = fopen($real, 'rb');
    if ($fp === false) {
      Response::html('Not Found', 404);
    }

    // --- stabilny streaming w kawałkach (DSM proxy to lubi) ---
    $chunk = 1024 * 1024; // 1MB
    while (!feof($fp)) {
      $buf = fread($fp, $chunk);
      if ($buf === false) break;
      echo $buf;
      flush();
    }
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
