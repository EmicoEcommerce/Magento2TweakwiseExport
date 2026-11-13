<?php

declare(strict_types=1);

namespace Tweakwise\Test\Support\Performance;

use RuntimeException;
use Symfony\Component\Process\Process;
use XMLReader;

class ExportRunner
{
    private const TIMEOUT_SECONDS = 3600;

    public function __construct(private readonly string $magentoRoot)
    {
    }

    /** @param string[] $arguments */
    public function run(string $feedFile, array $arguments = []): ExportResult
    {
        $command = array_merge(
            [PHP_BINARY, '-d', 'memory_limit=-1', $this->magentoRoot . '/bin/magento', 'tweakwise:export'],
            ['--file=' . $feedFile],
            $arguments
        );

        $process = new Process($command, $this->magentoRoot, null, null, self::TIMEOUT_SECONDS);

        $start = microtime(true);
        $process->run();
        $wallSeconds = microtime(true) - $start;

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                sprintf(
                    "tweakwise:export failed with exit code %s\n%s\n%s",
                    (string)$process->getExitCode(),
                    $process->getOutput(),
                    $process->getErrorOutput()
                )
            );
        }

        $output = $process->getOutput();
        if (!preg_match('/in ([\d.]+)s using ([\d.]+)Mb memory/', $output, $matches)) {
            throw new RuntimeException('Could not read time and memory from the export output: ' . $output);
        }

        if (!is_file($feedFile)) {
            throw new RuntimeException(sprintf('Export finished but "%s" was not created', $feedFile));
        }

        return new ExportResult(
            seconds: (float)$matches[1],
            wallSeconds: $wallSeconds,
            peakMemoryMb: (float)$matches[2],
            feedBytes: (int)filesize($feedFile),
            itemCount: $this->countItems($feedFile),
            output: $output
        );
    }

    public function cleanConfigCache(): void
    {
        $process = new Process(
            [PHP_BINARY, $this->magentoRoot . '/bin/magento', 'cache:clean', 'config', 'eav'],
            $this->magentoRoot,
            null,
            null,
            self::TIMEOUT_SECONDS
        );
        $process->mustRun();
    }

    private function countItems(string $feedFile): int
    {
        $reader = new XMLReader();
        if (!$reader->open($feedFile)) {
            throw new RuntimeException(sprintf('Could not open feed "%s"', $feedFile));
        }

        $count = 0;
        $insideItems = false;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth === 1) {
                $insideItems = $reader->name === 'items';
            }

            $isItem = $reader->nodeType === XMLReader::ELEMENT && $reader->depth === 2 && $reader->name === 'item';
            if (!$insideItems || !$isItem) {
                continue;
            }

            $count++;
        }

        $reader->close();

        return $count;
    }
}
