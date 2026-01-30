<?php
declare(strict_types=1);

final class AdminPhotoActionsRepository {
  /** Dodaje publiczny komentarz admina pod zdjęciem (widoczny dla klienta). */
  public function addComment(int $adminUserId, int $photoId, string $text): array {
    $text = trim($text);
    if ($photoId <= 0) {
      Response::json(['ok' => false, 'error' => 'INVALID_PHOTO'], 400);
    }
    if ($text === '' || mb_strlen($text) > 2000) {
      Response::json(['ok' => false, 'error' => 'INVALID_COMMENT'], 400);
    }

    $sql = "INSERT INTO photo_comments (photo_id, user_id, comment_text, is_internal)
            VALUES (:pid, :uid, :txt, 0)";
    $stmt = db()->prepare($sql);
    $stmt->execute(['pid' => $photoId, 'uid' => $adminUserId, 'txt' => $text]);

    $id = (int)db()->lastInsertId();
    return [
      'id' => $id,
      'photo_id' => $photoId,
      'comment_text' => $text,
      'created_at' => date('Y-m-d H:i:s'),
    ];
  }
}
