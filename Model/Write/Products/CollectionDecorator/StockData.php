<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator;

use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\StockData\StockMapProviderInterface;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CompositeExportEntityInterface;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntity;
use Magento\Framework\App\ProductMetadataInterface;
use Tweakwise\Magento2TweakwiseExport\Model\StockItemFactory as TweakwiseStockItemFactory;
use Magento\Framework\Module\Manager;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Stock\Collection as StockCollection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Stock\ExportEntity as StockExportEntity;

class StockData implements DecoratorInterface
{
    /**
     * @var ProductMetadataInterface
     */
    protected $metaData;

    /**
     * @var DecoratorInterface[]
     */
    protected $stockMapProviders = [];

    /**
     * @var TweakwiseStockItemFactory
     */
    protected $stockItemFactory;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * StockData constructor.
     *
     * @param ProductMetadataInterface $metaData
     * @param TweakwiseStockItemFactory $stockItemFactory
     * @param Config $config
     * @param Manager $moduleManager
     * @param StockMapProviderInterface[] $stockMapProviders
     */
    public function __construct(
        ProductMetadataInterface $metaData,
        TweakwiseStockItemFactory $stockItemFactory,
        Config $config,
        Manager $moduleManager,
        array $stockMapProviders
    ) {
        $this->metaData = $metaData;
        $this->stockMapProviders = $stockMapProviders;
        $this->stockItemFactory = $stockItemFactory;
        $this->config = $config;
        $this->moduleManager = $moduleManager;
    }

    /**
     * Decorate items with extra data or remove items completely
     *
     * @param Collection|StockCollection $collection
     */
    public function decorate(Collection|StockCollection $collection): void
    {
        // This has to be called before setting the stock items.
        // This way the composite products are not filtered since they mostly have 0 stock.
        $toBeCombinedEntities = $collection->getAllEntities();
        $storeId = $collection->getStore()->getId();

        $this->addStockItems($storeId, $collection);
        foreach ($toBeCombinedEntities as $item) {
            $this->addStockPercentage($item);
        }
    }

    /**
     * This registers stock items to export entities, they will be combined later to "final" stock items
     *
     *
     * @param int $storeId
     * @param Collection|StockCollection $collection
     */
    protected function addStockItems(int $storeId, Collection|StockCollection $collection): void
    {
        if ($collection->count() === 0) {
            return;
        }

        $stockMapProvider = $this->resolveStockMapProvider();
        $stockItemMap = $stockMapProvider->getStockItemMap($collection, $storeId);

        foreach ($collection as $entity) {
            $this->assignStockItem($stockItemMap, $entity);

            if ($entity instanceof CompositeExportEntityInterface) {
                foreach ($entity->getAllChildren() as $childEntity) {
                    $this->assignStockItem($stockItemMap, $childEntity);
                }
            }
        }
    }

    /**
     * @param array $stockItemMap
     * @param ExportEntity|StockExportEntity $entity
     */
    protected function assignStockItem(array $stockItemMap, ExportEntity|StockExportEntity $entity): void
    {
        $entityId = $entity->getId();
        if (isset($stockItemMap[$entityId])) {
            $stockItem = $stockItemMap[$entityId];
        } else {
            $stockItem = $this->stockItemFactory->create();
        }

        $entity->setStockItem($stockItem);
    }

    /**
     * @param ExportEntity|StockExportEntity $entity
     */
    protected function addStockPercentage(ExportEntity|StockExportEntity $entity): void
    {
        $entity->addAttribute('stock_percentage', $this->calculateStockPercentage($entity));
    }

    /**
     * @param ExportEntity|StockExportEntity $entity
     * @return float
     */
    protected function calculateStockPercentage(ExportEntity|StockExportEntity $entity): float
    {
        if (!$entity instanceof CompositeExportEntityInterface) {
            return (int) $this->isInStock($entity) * 100;
        }

        $children = $entity->getEnabledChildren();
        $childrenCount = \count($children);
        // Just to be sure we dont divide by 0, we really should not get here
        if ($childrenCount <= 0) {
            return (int) $this->isInStock($entity) * 100;
        }

        $inStockChildrenCount = \count(\array_filter($children, [$this, 'isInStock']));
        return round(($inStockChildrenCount / $childrenCount) * 100, 2);
    }

    /**
     * @param ExportEntity|StockExportEntity $entity
     * @return bool
     */
    protected function isInStock(ExportEntity|StockExportEntity $entity): bool
    {
        $stockItem = $entity->getStockItem();
        return (int)(!$stockItem || $stockItem->getIsInStock());
    }

    /**
     * This method determines which inventory implementation is used
     * the options are the old magento stock items
     * or the new magento MSI with source items and reservations
     *
     * @return StockMapProviderInterface
     */
    protected function resolveStockMapProvider(): StockMapProviderInterface
    {
        $version = $this->metaData->getVersion();
        // In case of magento 2.2.X use magento stock items
        if (version_compare($version, '2.3.0', '<')) {
            return $this->stockMapProviders['stockItemMapProvider'];
        }
        // If 2.3.X but MSI is disabled also use stock items
        if (!$this->moduleManager->isEnabled('Magento_Inventory') || !$this->moduleManager->isEnabled('Magento_InventoryApi')) {
            return $this->stockMapProviders['stockItemMapProvider'];
        }

        // Use sourceItems to determine stock
        return $this->stockMapProviders['sourceItemMapProvider'];
    }
}
