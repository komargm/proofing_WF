<?php
declare(strict_types=1);

final class AdminPhotoActionsController {
  public function __construct(private AdminPhotoActionsRepository $repo) {}

  public function setAdminRating(array $params): void {
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    Csrf::validate();

    $rating = (int)($_POST['rating'] ?? 0);
    $new = $this->repo->setAdminRating($adminId, $photoId, $rating);
    Response::json(['ok' => true, 'rating' => $new]);
  }

  public function addComment(array $params): void {
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    Csrf::validate();

    $payload = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $text = (string)($payload['text'] ?? ($_POST['text'] ?? ''));

    $comment = $this->repo->addComment($adminId, $photoId, $text);
    $comment['author_name'] = (string)($_SESSION['user_first_name'] ?? '');
    $comment['role_name'] = 'admin';
    Response::json(['ok' => true, 'comment' => $comment]);
  }
}
