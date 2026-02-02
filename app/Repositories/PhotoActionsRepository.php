<?php
declare(strict_types=1);

final class PhotoActionsRepository {

  private function assertClientAccessOrFail(int $userId, int $photoId): void {
    $sql = "
      SELECT 1
      FROM user_album_access uaa
      JOIN albums a ON a.id = uaa.album_id AND a.is_visible = 1
      JOIN photos p ON p.album_id = uaa.album_id
      WHERE uaa.user_id = :uid AND p.id = :pid
        AND p.is_visible = 1
      LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId, 'pid' => $photoId]);
    if (!$stmt->fetchColumn()) {
      Response::html('Forbidden', 403);
    }
  }

  /** Toggle client_selected_at. Returns new state (selected: bool). */
  public function toggleSelected(int $userId, int $photoId): bool {
    $this->assertClientAccessOrFail($userId, $photoId);

    // Toggle: if NULL -> NOW(), else NULL
    $sql = "UPDATE photos
            SET client_selected_at = IF(client_selected_at IS NULL, NOW(), NULL)
            WHERE id = :pid";
    $stmt = db()->prepare($sql);
    $stmt->execute(['pid' => $photoId]);

    $sql2 = "SELECT client_selected_at FROM photos WHERE id = :pid LIMIT 1";
    $stmt2 = db()->prepare($sql2);
    $stmt2->execute(['pid' => $photoId]);
    $val = $stmt2->fetchColumn();

    return !empty($val);
  }

  /** Set rating 1..6 or null (0). Returns new rating (int|null). */
  public function setRating(int $userId, int $photoId, int $rating): ?int {
    $this->assertClientAccessOrFail($userId, $photoId);

    $new = ($rating >= 1 && $rating <= 6) ? $rating : null;

    $sql = "UPDATE photos SET client_rating = :r WHERE id = :pid";
    $stmt = db()->prepare($sql);
    $stmt->bindValue('r', $new, $new === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue('pid', $photoId, PDO::PARAM_INT);
    $stmt->execute();

    return $new;
  }

  /**
   * Pobiera admin_rating i imię fotografa (created_by -> users.first_name)
   * dla danego zdjęcia z walidacją dostępu klienta.
   *
   * @return array{admin_rating:int, photographer_first_name:string}
   */
  public function adminRatingAndPhotographerName(int $userId, int $photoId): array {
    $this->assertClientAccessOrFail($userId, $photoId);

    $sql = "
      SELECT
        COALESCE(p.admin_rating, 0) AS admin_rating,
        COALESCE(u.first_name, '') AS photographer_first_name
      FROM photos p
      JOIN albums a ON a.id = p.album_id
      LEFT JOIN users u ON u.id = a.created_by
      WHERE p.id = :pid
      LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['pid' => $photoId]);
    $row = $stmt->fetch();

    return [
      'admin_rating' => (int)($row['admin_rating'] ?? 0),
      'photographer_first_name' => (string)($row['photographer_first_name'] ?? ''),
    ];
  }

  /** Add public comment (is_internal=0). Returns comment payload. */
  public function addComment(int $userId, int $photoId, string $text): array {
    $this->assertClientAccessOrFail($userId, $photoId);

    $text = trim($text);
    if ($text === '' || mb_strlen($text) > 2000) {
      Response::json(['ok' => false, 'error' => 'Komentarz jest pusty albo za długi.'], 400);
    }

    $sql = "INSERT INTO photo_comments (photo_id, user_id, comment_text, is_internal)
            VALUES (:pid, :uid, :txt, 0)";
    $stmt = db()->prepare($sql);
    $stmt->execute(['pid' => $photoId, 'uid' => $userId, 'txt' => $text]);

    $id = (int)db()->lastInsertId();

    return [
      'id' => $id,
      'photo_id' => $photoId,
      'comment_text' => $text,
      'created_at' => date('Y-m-d H:i:s'),
    ];
  }
}
