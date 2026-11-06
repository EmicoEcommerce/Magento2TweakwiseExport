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

    public function summary(): string
    {
        return sprintf(
            '%d items, %.2fs (%.2f ms/item), peak %.1f MB, feed %.1f MB',
            $this->itemCount,
            $this->seconds,
            $this->millisecondsPerItem(),
            $this->peakMemoryMb,
            $this->feedBytes / 1024 / 1024
        );
    }
}
