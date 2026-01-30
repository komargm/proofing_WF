<?php
declare(strict_types=1);

final class Router {
  /** @var array<string, array<int, array{pattern:string, handler:callable, middlewares:array}>> */
  private array $routes = ['GET' => [], 'POST' => []];

  public function get(string $pattern, callable $handler, array $middlewares = []): void {
    $this->routes['GET'][] = compact('pattern', 'handler', 'middlewares');
  }

  public function post(string $pattern, callable $handler, array $middlewares = []): void {
    $this->routes['POST'][] = compact('pattern', 'handler', 'middlewares');
  }

  public function dispatch(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    foreach ($this->routes[$method] ?? [] as $route) {
      $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
      if (preg_match($regex, $uri, $matches)) {
        $params = array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);

        foreach ($route['middlewares'] as $mw) {
          $mw($method, $uri, $params);
        }

        ($route['handler'])($params);
        return;
      }
    }

    Response::html('Not Found', 404);
  }
}
