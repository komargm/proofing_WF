<?php
declare(strict_types=1);

final class AdminPhotoActionsController {
  public function __construct(private AdminPhotoActionsRepository $repo) {}

  public function addComment(array $params): void {
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    Csrf::validate();

    $payload = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $text = (string)($payload['text'] ?? '');

    $comment = $this->repo->addComment($adminId, $photoId, $text);
    Response::json(['ok' => true, 'comment' => $comment]);
  }
}
