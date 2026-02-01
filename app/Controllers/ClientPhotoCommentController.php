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

        // app.js wysyła pole "text"; wspieramy też starsze "comment_text"
        $body = (string)($_POST['text'] ?? ($_POST['comment_text'] ?? ''));

        try {
            $row = $this->comments->addClientComment($photoId, $userId, $body);
            // Ujednolicamy format odpowiedzi z endpointem admina (/admin/photo/:id/comment)
            $row['author_name'] = (string)($_SESSION['user_first_name'] ?? '');
            $row['role_name'] = 'client';
            echo json_encode(['ok' => true, 'comment' => $row]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
