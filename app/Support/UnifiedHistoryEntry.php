<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final readonly class UnifiedHistoryEntry
{
    public function __construct(
        public CarbonImmutable $date,
        public string $title,
        public string $amountLabel,
        public string $statusLabel,
        public string $icon,
    ) {}
}
