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

    public function _after(): void
    {
        Mockery::close();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testClearOutputBuffersRemovesAllActiveBuffers(): void
    {
        ob_start();
        ob_start();

        $this->assertGreaterThanOrEqual(2, ob_get_level());

        $this->createSubject()->clearOutputBuffersProxy();

        $this->assertSame(0, ob_get_level());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExecuteCallsXmlHeaderAndClearsOutputBuffers(): void
    {
        ob_start();
        ob_start();

        $this->assertGreaterThanOrEqual(2, ob_get_level());

        $subject = $this->createSubject();
        try {
            $subject->executeProxy();
            $this->fail('Controller did not terminate execution.');
        } catch (RuntimeException $e) {
            $this->assertSame('stop-controller', $e->getMessage());
        }

        $this->assertSame(0, ob_get_level());
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
            $driver
        ) extends Export {
            public function __construct(
                Context $context,
                ExportModel $export,
                Logger $log,
                RequestValidator $requestValidator,
                ResponseFactory $responseFactory,
                StoreManagerInterface $storeManager,
                File $driver
            ) {
                parent::__construct($context, $export, $log, $requestValidator, $responseFactory, $storeManager, $driver);
            }

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

            protected function renderFeedContent($store, $type)
            {
                return;
            }

            protected function terminate()
            {
                throw new RuntimeException('stop-controller');
            }
        };
    }
}
