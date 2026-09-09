<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Controller\Feed;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\Magento2TweakwiseExport\App\Response\FeedContent;
use Tweakwise\Magento2TweakwiseExport\Model\Export as ExportModel;
use Tweakwise\Magento2TweakwiseExport\Model\Logger;
use Tweakwise\Magento2TweakwiseExport\Model\RequestValidator;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\MediaStorage\Model\File\Storage\ResponseFactory;

class Export implements ActionInterface
{
    /**
     * @var Export
     */
    protected $export;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * @var ResponseFactory
     */
    protected $responseFactory;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var RequestValidator
     */
    protected $requestValidator;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Export constructor.
     *
     * @param Context $context
     * @param ExportModel $export
     * @param Logger $log
     * @param RequestValidator $requestValidator
     * @param ResponseFactory $responseFactory
     * @param StoreManagerInterface $storeManager
     * @param File $driver
     */
    public function __construct(
        Context $context,
        ExportModel $export,
        Logger $log,
        RequestValidator $requestValidator,
        ResponseFactory $responseFactory,
        StoreManagerInterface $storeManager,
        protected File $driver
    ) {
        $this->context = $context;
        // @phpstan-ignore-next-line
        $this->export = $export;
        $this->log = $log;
        $this->requestValidator = $requestValidator;
        $this->responseFactory = $responseFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * We return an instance of NotCacheableInterface
     * to make sure that sendVary does not get triggered
     * as that would result in a "headers already sent exception"
     *
     * @see    \Magento\Framework\App\PageCache\NotCacheableInterface
     * @see    \Magento\PageCache\Model\App\Response\HttpPlugin
     * @see    \Magento\MediaStorage\Model\File\Storage\Response
     * @throws NotFoundException
     * phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn
     * @return void
     * phpcs:disable Magento2.Security.LanguageConstruct.ExitUsage
     * @SuppressWarnings("PHPMD.ExitExpression")
     */
    // @phpstan-ignore-next-line
    public function execute()
    {
        $request = $this->context->getRequest();

        if (
            !$this->requestValidator->validateRequestKey($request) ||
            (!$this->requestValidator->validateStoreKey($request)) ||
            (!$this->requestValidator->validateType($request))
        ) {
            throw new NotFoundException(__('Page not found.'));
        }

        $store = null;
        $storeId = $request->getParam('store');
        if (!empty($storeId)) {
            $store = $this->storeManager->getStore($storeId);
        }

        // Discard any output already buffered by other modules (e.g. Stape GTM setting
        // cookies) before we start streaming XML feed. This only removes data still in
        // active buffers; content already flushed cannot be undone at this point.
        $this->clearOutputBuffers();
        $this->sendXmlContentTypeHeader();

        $this->renderFeedContent($store, $request->getParam('type'));
        $this->terminate();
    }

    /**
     * @param StoreInterface|null $store
     * @param string|null $type
     * @return void
     */
    protected function renderFeedContent($store, $type)
    {
        // @phpstan-ignore-next-line
        (new FeedContent($this->export, $this->log, $this->driver, $store, $type))->__toString();
    }

    /**
     * @return void
     * phpcs:disable Magento2.Security.LanguageConstruct.ExitUsage
     * @SuppressWarnings("PHPMD.ExitExpression")
     */
    protected function terminate()
    {
        exit();
    }

    /**
     * @return void
     */
    protected function clearOutputBuffers()
    {
        while ($this->getOutputBufferLevel() > 0) {
            $this->endOutputBuffer();
        }
    }

    /**
     * @return int
     */
    protected function getOutputBufferLevel(): int
    {
        return ob_get_level();
    }

    /**
     * @return void
     */
    protected function endOutputBuffer(): void
    {
        ob_end_clean();
    }

    /**
     * @return void
     */
    protected function sendXmlContentTypeHeader()
    {
        header('Content-Type: application/xml; charset=UTF-8');
    }
}
