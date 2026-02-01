<?php
declare(strict_types=1);

final class AdminPhotoActionsRepository {
  /** Ustawia ocenę admina (1-6) albo czyści (0/NULL). */
  public function setAdminRating(int $adminUserId, int $photoId, int $rating): ?int {
    if ($photoId <= 0) {
      Response::json(['ok' => false, 'error' => 'INVALID_PHOTO'], 400);
    }

    // rating: 0 = usuń ocenę
    if ($rating < 0 || $rating > 6) {
      Response::json(['ok' => false, 'error' => 'INVALID_RATING'], 400);
    }

    $val = $rating === 0 ? null : $rating;
    $sql = "UPDATE photos SET admin_rating = :r WHERE id = :pid";
    $stmt = db()->prepare($sql);
    $stmt->bindValue('r', $val, $val === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue('pid', $photoId, PDO::PARAM_INT);
    $stmt->execute();
    return $val;
  }

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
