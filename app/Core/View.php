<?php
declare(strict_types=1);

final class View {
  private static function basePath(): string {
    // __DIR__ = .../src/app/Core
    // Chcemy .../src/views
    return realpath(__DIR__ . '/../../views') ?: (__DIR__ . '/../../views');
  }

  public static function render(string $view, array $data = []): string {
    $viewFile = self::basePath() . '/' . $view . '.php';

    if (!file_exists($viewFile)) {
      return 'View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8')
        . ' (looking for: ' . htmlspecialchars($viewFile, ENT_QUOTES, 'UTF-8') . ')';
    }

    extract($data, EXTR_SKIP);

    ob_start();
    require $viewFile;
    return (string)ob_get_clean();
  }

  public static function page(string $view, array $data = []): string {
    $content = self::render($view, $data);
    return self::render('layout', ['content' => $content] + $data);
  }
}
