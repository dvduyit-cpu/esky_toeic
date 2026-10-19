<?php

namespace App\Support;

final class ToeicReadingScore
{
    // Conversion table supplied by the administrator.
    public static function fromRaw(int $correct): int
    {
        if ($correct < 0 || $correct > 100) {
            throw new \InvalidArgumentException('Reading raw score must be between 0 and 100.');
        }
        if ($correct === 0) return 0;
        return max(5, $correct * 5 - 5);
    }
}
