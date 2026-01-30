<?php
// Wklej ten fragment do pętli po zdjęciach w Twoim widoku albumu klienta.
// Zakładam, że $ph to rekord z getClientAlbumView().
?>
<div class="photo-tile" data-photo-id="<?= (int)$ph['photo_id'] ?>">
  <a class="thumb-link" href="javascript:void(0)">
    <img src="/media/thumb.php?path=<?= urlencode($ph['thumb_path'] ?? '') ?>" alt="">
  </a>

  <div class="photo-meta">
    <div class="last-comment" id="last-comment-<?= (int)$ph['photo_id'] ?>">
      <?php if (!empty($ph['last_comment_text'])): ?>
        <span class="snippet"><?= htmlspecialchars(mb_strimwidth($ph['last_comment_text'], 0, 90, '…')) ?></span>
      <?php else: ?>
        <span class="placeholder">Dodaj uwagę…</span>
      <?php endif; ?>
    </div>

    <form class="comment-form" data-photo-id="<?= (int)$ph['photo_id'] ?>">
      <input type="text" name="comment_text" maxlength="2000" placeholder="Napisz krótką uwagę…">
      <button type="submit">Wyślij</button>
    </form>
  </div>
</div>
