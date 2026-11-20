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
    private const BASELINE_PREFIX = 'PERFORMANCE_BASELINE_';
    private const DEFAULT_ALLOWED_SLOWDOWN_PERCENT = 10;
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

    /** @var string[]|null */
    private ?array $envFile = null;

    private string $configTable;

    /** @var (string|null)[] */
    private array $originalConfig = [];

    /** @var float[] */
    private array $measurements = [];

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _before(): void
    {
        if ($this->setting(self::ENABLE_ENV) !== 'true') {
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

        $this->cloner->removeAll();

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
        $comparable = array_filter(
            $this->measurements,
            static fn(float $seconds): bool => $seconds >= self::MIN_SECONDS_TO_COMPARE
        );

        $allowedPercent = $this->allowedPercent();
        $problems = [];
        foreach ($comparable as $label => $seconds) {
            $baseline = $this->baselineSeconds($label);
            if ($baseline === null || $seconds <= $baseline * (1 + $allowedPercent / 100)) {
                continue;
            }

            $problems[] = sprintf(
                'The export "%s" took %.1fs, the baseline is %.1fs (at most %d%% slower is allowed)',
                $label,
                $seconds,
                $baseline,
                $allowedPercent
            );
        }

        return $problems;
    }

    protected function setting(string $name): ?string
    {
        $fromEnvironment = getenv($name);
        if ($fromEnvironment !== false && $fromEnvironment !== '') {
            return $fromEnvironment;
        }

        $fromFile = $this->envFile()[$name] ?? '';

        return $fromFile === '' ? null : $fromFile;
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

    /** @return string[] */
    private function envFile(): array
    {
        if ($this->envFile !== null) {
            return $this->envFile;
        }

        $this->envFile = [];
        $path = codecept_root_dir('tests/.env');
        if (!is_file($path)) {
            return $this->envFile;
        }

        foreach ((array)file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $this->envFile[trim($name)] = trim($value, " \t\"'");
        }

        return $this->envFile;
    }

    private function baselineKey(string $label): string
    {
        return self::BASELINE_PREFIX . strtoupper((string)preg_replace('/[^a-z0-9]+/i', '_', $label));
    }

    private function baselineSeconds(string $label): ?float
    {
        $seconds = $this->setting($this->baselineKey($label));

        return $seconds === null ? null : (float)$seconds;
    }

    private function allowedPercent(): float
    {
        $percent = $this->setting(self::ALLOWED_SLOWDOWN_ENV);

        return $percent === null ? self::DEFAULT_ALLOWED_SLOWDOWN_PERCENT : (float)$percent;
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

    /** @param int[] $catalog */
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

    /** @return int[] */
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
