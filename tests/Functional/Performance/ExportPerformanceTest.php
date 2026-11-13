<?php

declare(strict_types=1);

namespace Tweakwise\Test\Functional\Performance;

use Tweakwise\Test\Support\Performance\AbstractPerformanceTest;
use Tweakwise\Test\Support\Performance\ExportResult;

class ExportPerformanceTest extends AbstractPerformanceTest
{
    private const CATALOG_MULTIPLIER = 5;

    private const DEFAULT_ATTRIBUTE_TIERS = [50, 150];
    private const ATTRIBUTE_TIERS_ENV = 'TWEAKWISE_PERF_ATTRIBUTES';

    private const DEFAULT_WEBSITE_TIERS = [4, 9];
    private const WEBSITE_TIERS_ENV = 'TWEAKWISE_PERF_WEBSITES';

    private const DEFAULT_MULTIPLIERS = [5, 25, 75];
    private const MULTIPLIERS_ENV = 'TWEAKWISE_PERF_MULTIPLIERS';

    public function testExportPerformance(): void
    {
        $this->cloner->addCatalogCopies(self::CATALOG_MULTIPLIER - 1);

        $base = $this->export('base');
        if ($base->itemCount === 0) {
            $this->fail('There are no products to export, is the sample data installed?');
        }

        $problems = array_merge(
            $this->measureAttributes($base),
            $this->measureWebsites($base),
            $this->measureCatalogSize($base)
        );
        $problems = array_merge($problems, $this->baselineProblems());

        if ($problems === []) {
            return;
        }

        $this->fail("The export has performance problems:\n- " . implode("\n- ", $problems));
    }

    /** @return string[] */
    private function measureAttributes(ExportResult $base): array
    {
        $problems = [];

        $added = 0;
        foreach ($this->tiers(self::ATTRIBUTE_TIERS_ENV, self::DEFAULT_ATTRIBUTE_TIERS, 1) as $tier) {
            $this->attributes->add($tier - $added);
            $added = $tier;

            $result = $this->export('attributes-' . $tier);

            if ($result->itemCount !== $base->itemCount) {
                $problems[] = sprintf('%d extra attributes changed the number of items in the feed', $tier);
            }

            if ($result->feedBytes > $base->feedBytes) {
                continue;
            }

            $problems[] = sprintf('The %d extra attributes are not exported, the feed did not grow', $tier);
        }

        $this->attributes->removeAll();

        return $problems;
    }

    /** @return string[] */
    private function measureWebsites(ExportResult $base): array
    {
        $problems = [];

        $added = 0;
        foreach ($this->tiers(self::WEBSITE_TIERS_ENV, self::DEFAULT_WEBSITE_TIERS, 1) as $tier) {
            $this->websites->add($tier - $added);
            $added = $tier;

            $result = $this->export('websites-' . $tier);

            if ($result->itemCount === $base->itemCount * (1 + $tier)) {
                continue;
            }

            $problems[] = sprintf(
                'The feed with %d extra websites has %d items, expected %d (the products of every website)',
                $tier,
                $result->itemCount,
                $base->itemCount * (1 + $tier)
            );
        }

        $this->websites->removeAll();

        return $problems;
    }

    /** @return string[] */
    private function measureCatalogSize(ExportResult $base): array
    {
        $problems = [];

        $multipliers = $this->tiers(self::MULTIPLIERS_ENV, self::DEFAULT_MULTIPLIERS, self::CATALOG_MULTIPLIER);
        $this->assertGreaterThanOrEqual(2, count($multipliers), 'At least two multipliers are needed to compare');

        $copies = self::CATALOG_MULTIPLIER;
        $results = [];
        foreach ($multipliers as $multiplier) {
            if ($multiplier === self::CATALOG_MULTIPLIER) {
                $results[$multiplier] = $base;
                continue;
            }

            $this->cloner->addCatalogCopies($multiplier - $copies);
            $copies = $multiplier;

            $results[$multiplier] = $this->export('products-' . $multiplier . 'x');
        }

        $first = $multipliers[0];
        $second = $multipliers[1];
        $itemsPerCopy = ($results[$second]->itemCount - $results[$first]->itemCount) / ($second - $first);
        if ($itemsPerCopy <= 0) {
            $problems[] = 'Cloned products were not exported at all';
        }

        foreach ($results as $multiplier => $result) {
            $expected = $results[$first]->itemCount + ($multiplier - $first) * $itemsPerCopy;
            if (abs($result->itemCount - $expected) <= 0.5) {
                continue;
            }

            $problems[] = sprintf(
                'The number of items at %dx is %d, expected %d for the number of cloned catalogs',
                $multiplier,
                $result->itemCount,
                $expected
            );
        }

        $previousMultiplier = $multipliers[count($multipliers) - 2];
        $lastMultiplier = $multipliers[count($multipliers) - 1];
        $previous = $results[$previousMultiplier];
        $last = $results[$lastMultiplier];

        if ($last->millisecondsPerItem() > $previous->millisecondsPerItem() * 1.5) {
            $problems[] = sprintf(
                'Time per item grew from %.2f ms (%dx) to %.2f ms (%dx)',
                $previous->millisecondsPerItem(),
                $previousMultiplier,
                $last->millisecondsPerItem(),
                $lastMultiplier
            );
        }

        if ($last->peakMemoryMb > $previous->peakMemoryMb * 1.25) {
            $problems[] = sprintf(
                'Peak memory grew from %.1f MB (%dx) to %.1f MB (%dx)',
                $previous->peakMemoryMb,
                $previousMultiplier,
                $last->peakMemoryMb,
                $lastMultiplier
            );
        }

        return $problems;
    }

    /**
     * @param int[] $default
     * @return int[]
     */
    private function tiers(string $environmentVariable, array $default, int $minimum): array
    {
        $tiers = $default;
        if (getenv($environmentVariable)) {
            $tiers = array_map('intval', explode(',', (string)getenv($environmentVariable)));
        }

        sort($tiers);

        $this->assertGreaterThanOrEqual(
            $minimum,
            $tiers[0],
            sprintf('The values of %s must be at least %d', $environmentVariable, $minimum)
        );

        return $tiers;
    }
}
