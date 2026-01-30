<?php
declare(strict_types=1);

final class Password {
  public static function generate(int $length = 12): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i=0; $i<$length; $i++) {
      $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
  }
}
