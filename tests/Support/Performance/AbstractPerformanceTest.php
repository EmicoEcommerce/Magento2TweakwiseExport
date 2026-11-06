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
    protected FunctionalTester $tester;

    protected ProductCloner $cloner;

    protected ExportRunner $runner;

    private string $feedFile;

    private AdapterInterface $connection;

    private string $configTable;

    /** @var array<string, string|null> */
    private array $originalConfig = [];

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _before(): void
    {
        $objectManager = $this->tester->getObjectManager();

        $resource = $objectManager->get(ResourceConnection::class);
        $this->connection = $resource->getConnection();
        $this->configTable = $resource->getTableName('core_config_data');

        $this->cloner = new ProductCloner($resource);
        $this->cloner->removeAll();
        // The export subprocess reads its config from the database, so it has to be enabled there.
        $this->setConfig('tweakwise/export/enabled', '1');

        $this->runner = new ExportRunner($objectManager->get(DirectoryList::class)->getRoot());
        $this->feedFile = codecept_output_dir('perf-feed-' . uniqid('', true) . '.xml');
    }

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _after(): void
    {
        $this->cloner->removeAll();
        $this->restoreConfig();

        foreach ((array)glob($this->feedFile . '*') as $file) {
            unlink($file);
        }
    }

    /** @param string[] $arguments extra arguments for the command */
    protected function export(string $label, array $arguments = []): ExportResult
    {
        $this->runner->cleanConfigCache();
        $result = $this->runner->run($this->feedFile, $arguments);

        $this->record($label, $result);

        return $result;
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

    private function record(string $label, ExportResult $result): void
    {
        // Printed so the measurements are visible in the CI log, which is where the baseline comes from.
        // phpcs:ignore Magento2.Security.LanguageConstruct.DirectOutput
        fwrite(STDERR, sprintf("\n[performance] %s: %s\n", $label, $result->summary()));

        file_put_contents(
            codecept_output_dir('performance.jsonl'),
            json_encode([
                'test' => static::class . '::' . $this->name(),
                'label' => $label,
                'date' => date('c'),
                'items' => $result->itemCount,
                'seconds' => $result->seconds,
                'ms_per_item' => round($result->millisecondsPerItem(), 3),
                'peak_memory_mb' => $result->peakMemoryMb,
                'feed_bytes' => $result->feedBytes,
            ]) . "\n",
            FILE_APPEND
        );
    }
}
