<?php

declare(strict_types=1);

namespace Tweakwise\Test\Functional\Performance;

use Tweakwise\Test\Support\Performance\AbstractPerformanceTest;
use Tweakwise\Test\Support\Performance\ExportResult;

class ExportScalabilityTest extends AbstractPerformanceTest
{
    // Times the catalog is multiplied after the 1x run. 75x is about 150.000 catalog products.
    // Can be overridden for a quick run, for example "2,3".
    private const DEFAULT_MULTIPLIERS = [5, 25, 75];
    private const MULTIPLIERS_ENV = 'TWEAKWISE_PERF_MULTIPLIERS';

    // The export time at this multiplier is compared with tests/_data/performance-baseline.json.
    // The baseline has the seconds per environment, for example {"2.4.8-8.3": 36.0} (Magento version, PHP version).
    // Environments without a baseline (and local runs) are not checked.
    private const BASELINE_MULTIPLIER = 25;
    private const ALLOWED_SLOWDOWN = 0.30;

    public function testExportScalesWithCatalogSize(): void
    {
        $base = $this->export('1x');

        $copies = 1;
        $results = [];
        foreach ($this->multipliers() as $multiplier) {
            $this->cloner->addCatalogCopies($multiplier - $copies);
            $copies = $multiplier;

            $results[$multiplier] = $this->export($multiplier . 'x');
        }

        $multipliers = array_keys($results);
        $firstMultiplier = $multipliers[0];
        $itemsPerCopy = ($results[$firstMultiplier]->itemCount - $base->itemCount) / ($firstMultiplier - 1);
        $this->assertGreaterThan(0, $itemsPerCopy, 'Cloned products were not exported at all');

        foreach ($results as $multiplier => $result) {
            $this->assertEqualsWithDelta(
                $base->itemCount + ($multiplier - 1) * $itemsPerCopy,
                $result->itemCount,
                0.5,
                sprintf('The number of items at %dx does not match the number of cloned catalogs', $multiplier)
            );
        }

        $previousMultiplier = $multipliers[count($multipliers) - 2];
        $lastMultiplier = $multipliers[count($multipliers) - 1];
        $previous = $results[$previousMultiplier];
        $last = $results[$lastMultiplier];

        $this->assertLessThanOrEqual(
            $previous->millisecondsPerItem() * 1.5,
            $last->millisecondsPerItem(),
            sprintf(
                'Time per item grew from %.2f ms (%dx) to %.2f ms (%dx)',
                $previous->millisecondsPerItem(),
                $previousMultiplier,
                $last->millisecondsPerItem(),
                $lastMultiplier
            )
        );

        $this->assertLessThanOrEqual(
            $previous->peakMemoryMb * 1.25,
            $last->peakMemoryMb,
            sprintf(
                'Peak memory grew from %.1f MB (%dx) to %.1f MB (%dx)',
                $previous->peakMemoryMb,
                $previousMultiplier,
                $last->peakMemoryMb,
                $lastMultiplier
            )
        );

        $this->assertNotSlowerThanBaseline($results[self::BASELINE_MULTIPLIER] ?? null);
    }

    private function assertNotSlowerThanBaseline(?ExportResult $result): void
    {
        if ($result === null) {
            return;
        }

        $environment = getenv('MAGENTO_VERSION') . '-' . getenv('PHP_VERSION');
        $baselines = json_decode((string)file_get_contents(codecept_data_dir('performance-baseline.json')), true);

        if (!isset($baselines[$environment])) {
            return;
        }

        $baseline = (float)$baselines[$environment];
        $this->assertLessThanOrEqual(
            $baseline * (1 + self::ALLOWED_SLOWDOWN),
            $result->seconds,
            sprintf(
                'The export at %dx took %.1fs, the baseline for %s is %.1fs (at most %d%% slower is allowed)',
                self::BASELINE_MULTIPLIER,
                $result->seconds,
                $environment,
                $baseline,
                self::ALLOWED_SLOWDOWN * 100
            )
        );
    }

    /** @return int[] */
    private function multipliers(): array
    {
        $multipliers = self::DEFAULT_MULTIPLIERS;
        if (getenv(self::MULTIPLIERS_ENV)) {
            $multipliers = array_map('intval', explode(',', (string)getenv(self::MULTIPLIERS_ENV)));
        }

        sort($multipliers);

        $this->assertGreaterThanOrEqual(2, count($multipliers), 'At least two multipliers are needed to compare');
        $this->assertGreaterThan(1, $multipliers[0], 'The multipliers must be higher than 1, 1x always runs first');

        return $multipliers;
    }
}
