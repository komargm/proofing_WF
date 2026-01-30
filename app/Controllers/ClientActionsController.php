<?php
declare(strict_types=1);

final class ClientActionsController {
  public function __construct(private PhotoActionsRepository $actions) {}

  private function requirePostAndCsrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      Response::html('Method Not Allowed', 405);
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
    Csrf::verifyOrFail(is_string($token) ? $token : null);
  }

  public function toggleSelect(array $params): void {
    $this->requirePostAndCsrf();

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($photoId <= 0) Response::json(['ok' => false, 'error' => 'Bad id'], 400);

    $selected = $this->actions->toggleSelected($userId, $photoId);
    Response::json(['ok' => true, 'selected' => $selected]);
  }

  public function setRating(array $params): void {
    $this->requirePostAndCsrf();

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    $rating = (int)($_POST['rating'] ?? 0);

    if ($photoId <= 0) Response::json(['ok' => false, 'error' => 'Bad id'], 400);

    $new = $this->actions->setRating($userId, $photoId, $rating);
    Response::json(['ok' => true, 'rating' => $new]);
  }

  public function addComment(array $params): void {
    $this->requirePostAndCsrf();

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    $text = (string)($_POST['text'] ?? '');

    if ($photoId <= 0) Response::json(['ok' => false, 'error' => 'Bad id'], 400);

    $comment = $this->actions->addComment($userId, $photoId, $text);
    Response::json(['ok' => true, 'comment' => $comment]);
  }
}
