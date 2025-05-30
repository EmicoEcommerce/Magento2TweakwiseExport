<?php

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model\Write;

use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\Framework\UrlInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Magento2TweakwiseExport\Model\Logger;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Categories\Iterator;
use Magento\Framework\Profiler;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;

class Categories implements WriterInterface
{
    /**
     * @var Iterator
     */
    protected $iterator;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * Categories constructor.
     *
     * @param Iterator $iterator
     * @param StoreManager $storeManager
     * @param Config $config
     * @param Helper $helper
     * @param Logger $log
     * @param UrlFinderInterface $urlFinder
     * @param UrlInterface $url
     */
    public function __construct(
        Iterator $iterator,
        StoreManager $storeManager,
        Config $config,
        Helper $helper,
        Logger $log,
        private readonly UrlFinderInterface $urlFinder,
        private readonly UrlInterface $url
    ) {
        $this->iterator = $iterator;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->helper = $helper;
        $this->log = $log;
    }

    /**
     * @param Writer $writer
     * @param XMLWriter $xml
     * @param StoreInterface|null $store
     */
    public function write(Writer $writer, XMLWriter $xml, StoreInterface  $store = null): void
    {
        $xml->startElement('categories');
        $writer->flush();

        $this->writeCategory($xml, 0, ['entity_id' => 1, 'name' => 'Root', 'position' => 0]);

        $stores = [];
        if ($store) {
            $stores[] = $store;
        } else {
            $stores = $this->storeManager->getStores();
        }

        /** @var Store $store */
        foreach ($stores as $store) {
            if ($this->config->isEnabled($store)) {
                $profileKey = 'categories::' . $store->getCode();
                try {
                    Profiler::start($profileKey);
                    $this->exportStore($writer, $xml, $store);
                } finally {
                    Profiler::stop($profileKey);
                }

                $this->log->debug(sprintf('Export categories for store %s', $store->getName()));
            } else {
                $this->log->debug(sprintf('Skip categories for store %s (disabled)', $store->getName()));
            }
        }

        $xml->endElement(); // categories
        $writer->flush();
    }

    /**
     * @param Writer $writer
     * @param XMLWriter $xml
     * @param Store $store
     * @param int[] $entityIds
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function exportStore(Writer $writer, XMLWriter $xml, Store $store, array $entityIds = []): void
    {
        // Set root category as exported
        $exportedCategories = [1 => true];
        $storeId = $store->getId();
        $storeRootCategoryId = (int) $store->getRootCategoryId();
        $this->iterator->setStore($store);
        // Purge iterator entity ids for the new store
        $this->iterator->setEntityIds($entityIds);

        foreach ($this->iterator as $index => $data) {
            // Skip magento root since we injected our fake root
            if ($data['entity_id'] === 1) {
                continue;
            }

            $parentId = (int) $data['parent_id'];
            // Store root category extend name so it is clear in tweakwise
            // Always export store root category whether it is enabled or not
            if ($parentId === 1) {
                // Skip category if not root of current store
                if ((int) $data['entity_id'] !== $storeRootCategoryId) {
                    continue;
                }

                if (!isset($data['name'])) {
                    $data['name'] = 'Root Category';
                }

                $data['name'] = $store->getName() . ' - ' . $data['name'];
            } elseif (!isset($data['is_active']) || !$data['is_active']) {
                continue;
            }

            if (!isset($exportedCategories[$parentId])) {
                continue;
            }

            $categoryUrl = $this->getCategoryUrl((int)$data['entity_id'], $store);
            if ($categoryUrl) {
                $data['url'] = $categoryUrl;
            }

            // Set category as exported
            $exportedCategories[$data['entity_id']] = true;
            $this->writeCategory($xml, $storeId, $data);
            // Flush every so often
            if ($index % 100 === 0) {
                $writer->flush();
            }
        }

        // Flush any remaining categories
        $writer->flush();
    }

    /**
     * @param XMLWriter $xml
     * @param int $storeId
     * @param array $data
     */
    protected function writeCategory(XMLWriter $xml, int $storeId, array $data): void
    {
        $tweakwiseId = $this->helper->getTweakwiseId($storeId, $data['entity_id']);
        $xml->addCategoryExport($tweakwiseId);

        $xml->startElement('category');
        $xml->writeElement('categoryid', $tweakwiseId);
        $xml->writeElement('rank', $data['position']);
        $xml->writeElement('name', $data['name']);

        if (isset($data['url'])) {
            $xml->writeElement('url', $data['url']);
        }

        if (isset($data['parent_id']) && $data['parent_id']) {
            $xml->startElement('parents');

            $parentId = (int) $data['parent_id'];
            if ($parentId !== 1) {
                $parentId = $this->helper->getTweakwiseId($storeId, $parentId);
            }

            $xml->writeElement('categoryid', $parentId);
            $xml->endElement(); // </parents>

            $this->log->debug(sprintf('Export category [%s] %s (parent: %s)', $tweakwiseId, $data['name'], $parentId));
        } else {
            $this->log->debug(sprintf('Export category [%s] %s (root)', $tweakwiseId, $data['name']));
        }

        $xml->endElement(); // </category>
    }

    /**
     * @param int $categoryId
     * @param Store $store
     * @return string|null
     */
    private function getCategoryUrl(int $categoryId, Store $store): ?string
    {
        $rewrite = $this->urlFinder->findOneByData(
            [
                UrlRewrite::ENTITY_ID => $categoryId,
                UrlRewrite::ENTITY_TYPE => CategoryUrlRewriteGenerator::ENTITY_TYPE,
                UrlRewrite::STORE_ID => $store->getId(),
                UrlRewrite::REDIRECT_TYPE => 0
            ]
        );
        if ($rewrite) {
            return $this->url->getDirectUrl($rewrite->getRequestPath());
        }

        return null;
    }
}
