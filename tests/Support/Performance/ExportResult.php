<?php

declare(strict_types=1);

namespace Tweakwise\Test\Support\Performance;

class ExportResult
{
    public function __construct(
        public readonly float $seconds,
        public readonly float $wallSeconds,
        public readonly float $peakMemoryMb,
        public readonly int $feedBytes,
        public readonly int $itemCount,
        public readonly string $output
    ) {
    }

    public function millisecondsPerItem(): float
    {
        return $this->itemCount > 0 ? $this->seconds * 1000 / $this->itemCount : 0.0;
    }
}
