<?php

namespace App\Support;

final class ToeicListeningScore
{
    // Conversion table supplied by the administrator.
    public static function fromRaw(int $correct): int
    {
        if ($correct < 0 || $correct > 100) {
            throw new \InvalidArgumentException('Listening raw score must be between 0 and 100.');
        }
        if ($correct === 0) return 0;
        if ($correct <= 75) return $correct * 5 + 10;
        return min(495, $correct * 5 + 15);
    }
}
