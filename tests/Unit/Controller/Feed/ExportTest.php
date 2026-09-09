<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Controller\Feed;

use Emico\CodeCept\Test\Unit;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\MediaStorage\Model\File\Storage\ResponseFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mockery;
use RuntimeException;
use Tweakwise\Magento2TweakwiseExport\Controller\Feed\Export;
use Tweakwise\Magento2TweakwiseExport\Model\Export as ExportModel;
use Tweakwise\Magento2TweakwiseExport\Model\Logger;
use Tweakwise\Magento2TweakwiseExport\Model\RequestValidator;
use Tweakwise\Test\Support\UnitTester;

class ExportTest extends Unit
{
    protected UnitTester $tester;

    private int $simulatedOutputBufferLevel = 0;

    private int $endedBufferCount = 0;

    public function getSimulatedOutputBufferLevel(): int
    {
        return $this->simulatedOutputBufferLevel;
    }

    public function endSimulatedOutputBuffer(): void
    {
        if ($this->simulatedOutputBufferLevel <= 0) {
            return;
        }

        $this->simulatedOutputBufferLevel--;
        $this->endedBufferCount++;
    }

    public function _after(): void
    {
        Mockery::close();
    }

    public function testClearOutputBuffersRemovesAllActiveBuffers(): void
    {
        $this->simulatedOutputBufferLevel = 2;
        $subject = $this->createSubject();

        $subject->clearOutputBuffersProxy();

        $this->assertSame(0, $this->simulatedOutputBufferLevel);
        $this->assertSame(2, $this->endedBufferCount);
    }

    public function testExecuteCallsXmlHeaderAndClearsOutputBuffers(): void
    {
        $this->simulatedOutputBufferLevel = 3;
        $subject = $this->createSubject();

        try {
            $subject->executeProxy();
            $this->fail('Controller did not terminate execution.');
        } catch (RuntimeException $e) {
            $this->assertSame('stop-controller', $e->getMessage());
        }

        $this->assertSame(0, $this->simulatedOutputBufferLevel);
        $this->assertSame(3, $this->endedBufferCount);
        $this->assertSame('Content-Type: application/xml; charset=UTF-8', $subject->getSentHeader());
    }

    private function createSubject()
    {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getParam')->with('store')->andReturn(null);
        $request->shouldReceive('getParam')->with('type')->andReturn(null);

        $context = Mockery::mock(Context::class);
        $context->shouldReceive('getRequest')->andReturn($request);

        $export = Mockery::mock(ExportModel::class);
        $logger = Mockery::mock(Logger::class);

        $requestValidator = Mockery::mock(RequestValidator::class);
        $requestValidator->shouldReceive('validateRequestKey')->with($request)->andReturn(true);
        $requestValidator->shouldReceive('validateStoreKey')->with($request)->andReturn(true);
        $requestValidator->shouldReceive('validateType')->with($request)->andReturn(true);

        $responseFactory = Mockery::mock(ResponseFactory::class);
        $storeManager = Mockery::mock(StoreManagerInterface::class);
        $driver = Mockery::mock(File::class);

        return new class (
            $context,
            $export,
            $logger,
            $requestValidator,
            $responseFactory,
            $storeManager,
            $driver,
            $this
        ) extends Export {
            public function clearOutputBuffersProxy(): void
            {
                $this->clearOutputBuffers();
            }

            public function executeProxy(): void
            {
                $this->execute();
            }

            public function getSentHeader(): ?string
            {
                return $this->sentHeader;
            }

            private ?string $sentHeader = null;

            protected function sendXmlContentTypeHeader()
            {
                $this->sentHeader = 'Content-Type: application/xml; charset=UTF-8';
            }

            protected function getOutputBufferLevel(): int
            {
                return $this->testCase->getSimulatedOutputBufferLevel();
            }

            protected function endOutputBuffer(): void
            {
                $this->testCase->endSimulatedOutputBuffer();
            }

            protected function renderFeedContent($store, $type)
            {
                unset($store, $type);
            }

            protected function terminate()
            {
                throw new RuntimeException('stop-controller');
            }

            /**
             * @var ExportTest
             */
            private $testCase;

            public function __construct(
                Context $context,
                ExportModel $export,
                Logger $log,
                RequestValidator $requestValidator,
                ResponseFactory $responseFactory,
                StoreManagerInterface $storeManager,
                File $driver,
                ExportTest $testCase
            ) {
                parent::__construct($context, $export, $log, $requestValidator, $responseFactory, $storeManager, $driver);
                $this->testCase = $testCase;
            }
        };
    }
}
