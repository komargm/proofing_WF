<?php

class ClientPhotoCommentController
{
    public function __construct(private PhotoCommentRepository $comments) {}

    public function store(int $photoId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'UNAUTHENTICATED']);
            return;
        }

        if (!$this->comments->userCanAccessPhoto($userId, $photoId)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'FORBIDDEN']);
            return;
        }

        $body = (string)($_POST['comment_text'] ?? '');

        try {
            $row = $this->comments->addClientComment($photoId, $userId, $body);
            echo json_encode(['ok' => true, 'data' => $row]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
