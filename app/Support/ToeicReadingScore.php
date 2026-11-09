<?php

namespace App\Support;

final class ToeicReadingScore
{
    // Administrator-supplied Reading table: 0–2 correct = 5; 3–100 = raw × 5 − 5.
    public static function fromRaw(int $correct): int
    {
        if ($correct < 0 || $correct > 100) {
            throw new \InvalidArgumentException('Reading raw score must be between 0 and 100.');
        }
        return max(5, $correct * 5 - 5);
    }
}
