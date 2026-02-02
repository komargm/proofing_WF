<?php

class PhotoCommentRepository
{
    public function __construct(private PDO $db) {}

    public function userCanAccessPhoto(int $userId, int $photoId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM photos p
            INNER JOIN albums a
                ON a.id = p.album_id AND a.is_visible = 1
            INNER JOIN user_album_access uaa
                ON uaa.album_id = p.album_id AND uaa.user_id = :user_id
            WHERE p.id = :photo_id
              AND p.is_visible = 1
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId, ':photo_id' => $photoId]);
        return (bool)$stmt->fetchColumn();
    }

    public function addClientComment(int $photoId, int $userId, string $text): array
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 2000) {
            throw new InvalidArgumentException('INVALID_COMMENT');
        }

        $stmt = $this->db->prepare("
            INSERT INTO photo_comments (photo_id, user_id, comment_text, is_admin_note, created_at)
            VALUES (:photo_id, :user_id, :text, 0, NOW())
        ");
        $stmt->execute([':photo_id' => $photoId, ':user_id' => $userId, ':text' => $text]);

        return [
            'photo_id' => $photoId,
            'comment_text' => $text,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
