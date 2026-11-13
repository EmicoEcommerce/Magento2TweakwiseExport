<?php

declare(strict_types=1);

namespace Tweakwise\Test\Support\Performance;

use Emico\CodeCept\Test\Unit;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Tweakwise\Test\Support\FunctionalTester;

abstract class AbstractPerformanceTest extends Unit
{
    private const ENABLE_ENV = 'PERFORMANCE_TESTS';
    private const BASELINE_ENV = 'PERFORMANCE_BASELINE_ENV';
    private const DEFAULT_ALLOWED_SLOWDOWN_PERCENT = 30;
    private const ALLOWED_SLOWDOWN_ENV = 'PERFORMANCE_ALLOWED_SLOWDOWN';
    private const MIN_SECONDS_TO_COMPARE = 5;

    protected FunctionalTester $tester;

    protected ProductCloner $cloner;

    protected AttributeGenerator $attributes;

    protected WebsiteGenerator $websites;

    protected ExportRunner $runner;

    private string $feedFile;

    private AdapterInterface $connection;

    private ResourceConnection $resource;

    private bool $hasLogged = false;

    private ?ExportResult $base = null;

    /** @var array<string, mixed>|null */
    private ?array $baselines = null;

    private string $configTable;

    /** @var array<string, string|null> */
    private array $originalConfig = [];

    /** @var array<string, float> */
    private array $measurements = [];

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _before(): void
    {
        if (getenv(self::ENABLE_ENV) !== 'true') {
            $this->markTestSkipped('Performance tests only run when ' . self::ENABLE_ENV . ' is "true"');
        }

        $objectManager = $this->tester->getObjectManager();

        $resource = $objectManager->get(ResourceConnection::class);
        $this->resource = $resource;
        $this->connection = $resource->getConnection();
        $this->configTable = $resource->getTableName('core_config_data');

        $this->cloner = new ProductCloner($resource);
        $this->attributes = new AttributeGenerator($resource);
        $this->websites = new WebsiteGenerator($resource);
        $this->setConfig('tweakwise/export/enabled', '1');

        $this->runner = new ExportRunner($objectManager->get(DirectoryList::class)->getRoot());
        $this->feedFile = codecept_output_dir('perf-feed-' . uniqid('', true) . '.xml');
    }

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _after(): void
    {
        if (!isset($this->cloner)) {
            return;
        }

        $this->attributes->removeAll();
        $this->websites->removeAll();
        $this->restoreConfig();

        if (!$this->isCi()) {
            $this->cloner->removeAll();
        }

        foreach ((array)glob($this->feedFile . '*') as $file) {
            unlink($file);
        }
    }

    /** @param string[] $arguments */
    protected function export(string $label, array $arguments = []): ExportResult
    {
        $this->runner->cleanConfigCache();
        $result = $this->runner->run($this->feedFile, $arguments);

        $this->base ??= $result;
        $this->measurements[$label] = $result->seconds;
        $this->record($label, $result, $this->catalogStats());

        return $result;
    }

    /** @return string[] */
    protected function baselineProblems(): array
    {
        $environment = $this->baselineEnvironment();
        $comparable = array_filter(
            $this->measurements,
            static fn(float $seconds): bool => $seconds >= self::MIN_SECONDS_TO_COMPARE
        );

        $this->log(sprintf(
            "\nBaseline for \"%s\", for tests/_data/performance-baseline.json "
            . "(total export time in seconds, only measurements of at least %d s):\n%s",
            $environment,
            self::MIN_SECONDS_TO_COMPARE,
            json_encode(
                [$environment => array_map(static fn(float $seconds): float => round($seconds, 1), $comparable)],
                JSON_PRETTY_PRINT
            )
        ));

        $allowedPercent = $this->allowedPercent();
        $problems = [];
        foreach ($comparable as $label => $seconds) {
            $baseline = $this->baselineSeconds($label);
            if ($baseline === null || $seconds <= $baseline * (1 + $allowedPercent / 100)) {
                continue;
            }

            $problems[] = sprintf(
                'The export "%s" took %.1fs, the baseline for %s is %.1fs (at most %d%% slower is allowed)',
                $label,
                $seconds,
                $environment,
                $baseline,
                $allowedPercent
            );
        }

        return $problems;
    }

    protected function setConfig(string $path, string $value): void
    {
        if (!array_key_exists($path, $this->originalConfig)) {
            $original = $this->connection->fetchOne(
                $this->connection->select()
                    ->from($this->configTable, ['value'])
                    ->where('scope = ?', 'default')
                    ->where('scope_id = ?', 0)
                    ->where('path = ?', $path)
            );
            $this->originalConfig[$path] = $original === false ? null : (string)$original;
        }

        $this->connection->insertOnDuplicate(
            $this->configTable,
            ['scope' => 'default', 'scope_id' => 0, 'path' => $path, 'value' => $value],
            ['value']
        );
    }

    /** @return array<string, mixed> */
    private function baselines(): array
    {
        if ($this->baselines === null) {
            $decoded = json_decode((string)file_get_contents(codecept_data_dir('performance-baseline.json')), true);
            $this->baselines = is_array($decoded) ? $decoded : [];
        }

        return $this->baselines;
    }

    private function baselineSeconds(string $label): ?float
    {
        $seconds = $this->baselines()[$this->baselineEnvironment()][$label] ?? null;

        return $seconds === null ? null : (float)$seconds;
    }

    private function allowedPercent(): float
    {
        return getenv(self::ALLOWED_SLOWDOWN_ENV) !== false
            ? (float)getenv(self::ALLOWED_SLOWDOWN_ENV)
            : self::DEFAULT_ALLOWED_SLOWDOWN_PERCENT;
    }

    private function describeIncrease(ExportResult $result): string
    {
        $base = $this->base;
        if ($base === null || $result === $base) {
            return 'base';
        }

        if ($base->seconds <= 0 || $base->millisecondsPerItem() <= 0) {
            return 'no base';
        }

        return sprintf(
            '%+.0f%% (%+.0f%% per item)',
            ($result->seconds / $base->seconds - 1) * 100,
            ($result->millisecondsPerItem() / $base->millisecondsPerItem() - 1) * 100
        );
    }

    private function describeBaseline(string $label, ExportResult $result): string
    {
        $baseline = $this->baselineSeconds($label);
        if ($baseline === null || $baseline <= 0) {
            return 'no baseline';
        }

        $percent = ($result->seconds / $baseline - 1) * 100;
        $description = sprintf('%+.1f%%', $percent);
        if ($result->seconds >= self::MIN_SECONDS_TO_COMPARE && $percent > $this->allowedPercent()) {
            $description .= '(!)';
        }

        return $description;
    }

    private function isCi(): bool
    {
        return getenv('CI') === 'true';
    }

    private function baselineEnvironment(): string
    {
        $override = getenv(self::BASELINE_ENV);
        if ($override !== false) {
            return $override;
        }

        return getenv('MAGENTO_VERSION') . '-' . getenv('PHP_VERSION');
    }

    private function restoreConfig(): void
    {
        foreach ($this->originalConfig as $path => $original) {
            if ($original === null) {
                $this->connection->delete(
                    $this->configTable,
                    ['scope = ?' => 'default', 'scope_id = ?' => 0, 'path = ?' => $path]
                );
                continue;
            }

            $this->connection->insertOnDuplicate(
                $this->configTable,
                ['scope' => 'default', 'scope_id' => 0, 'path' => $path, 'value' => $original],
                ['value']
            );
        }

        $this->originalConfig = [];
        $this->runner->cleanConfigCache();
    }

    /** @param array<string, int> $catalog */
    private function record(string $label, ExportResult $result, array $catalog): void
    {
        $perWebsite = $catalog['websites'] > 1
            ? sprintf('%d per website, ', intdiv($result->itemCount, $catalog['websites']))
            : '';

        $this->log(sprintf(
            '[Test %d: %d Items (%s%d Products) - %d Attributes - %d Websites]: '
            . 'Export time: %.1fs (%.1f ms/item) | Peak memory: %.1f MB | Feed size: %.1f MB '
            . '| Base test: %s | Baseline: %s',
            count($this->measurements),
            $result->itemCount,
            $perWebsite,
            $catalog['products'],
            $catalog['attributes'],
            $catalog['websites'],
            $result->seconds,
            $result->millisecondsPerItem(),
            $result->peakMemoryMb,
            $result->feedBytes / 1024 / 1024,
            $this->describeIncrease($result),
            $this->describeBaseline($label, $result)
        ));

        file_put_contents(
            codecept_output_dir('performance.jsonl'),
            json_encode([
                'test' => static::class,
                'label' => $label,
                'date' => date('c'),
                'products' => $catalog['products'],
                'attributes' => $catalog['attributes'],
                'store_views' => $catalog['storeViews'],
                'websites' => $catalog['websites'],
                'items' => $result->itemCount,
                'seconds' => $result->seconds,
                'ms_per_item' => round($result->millisecondsPerItem(), 3),
                'peak_memory_mb' => $result->peakMemoryMb,
                'feed_bytes' => $result->feedBytes,
            ]) . "\n",
            FILE_APPEND
        );
    }

    /** @return array<string, int> */
    private function catalogStats(): array
    {
        $table = fn(string $name): string => $this->resource->getTableName($name);

        $exportedAttributes = sprintf(
            'SELECT COUNT(*) FROM `%s` a JOIN `%s` c USING (attribute_id) WHERE a.entity_type_id = 4 '
            . "AND a.attribute_code <> 'category_ids' AND (c.used_in_product_listing = 1 OR c.is_filterable = 1 "
            . 'OR c.is_filterable_in_search = 1 OR c.is_searchable = 1 OR c.is_visible_in_advanced_search = 1 '
            . 'OR c.used_for_sort_by = 1)',
            $table('eav_attribute'),
            $table('catalog_eav_attribute')
        );

        return [
            'products' => (int)$this->connection->fetchOne('SELECT COUNT(*) FROM ' . $table('catalog_product_entity')),
            'attributes' => (int)$this->connection->fetchOne($exportedAttributes),
            'storeViews' => (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $table('store') . ' WHERE store_id > 0'
            ),
            'websites' => (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $table('store_website') . ' WHERE website_id > 0'
            ),
        ];
    }

    private function log(string $message): void
    {
        $prefix = $this->hasLogged ? '' : "\n";
        $this->hasLogged = true;

        // phpcs:ignore Magento2.Security.LanguageConstruct.DirectOutput
        fwrite(STDERR, $prefix . $message . "\n");
    }
}
