<?php
declare(strict_types=1);

final class Response {
  public static function redirect(string $to): void {
    header('Location: ' . $to, true, 302);
    exit;
  }

  public static function html(string $html, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
  }

  public static function json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
  }
}
