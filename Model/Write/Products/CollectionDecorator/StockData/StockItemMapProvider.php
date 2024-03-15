<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\StockData;

use Tweakwise\Magento2TweakwiseExport\Model\StockItem;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockItemCriteriaInterfaceFactory;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Tweakwise\Magento2TweakwiseExport\Model\StockItemFactory as TweakwiseStockItemFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Stock\Collection as StockCollection;

class StockItemMapProvider implements StockMapProviderInterface
{
    /**
     * @var StockItemRepositoryInterface
     */
    protected $stockItemRepository;

    /**
     * @var StockItemCriteriaInterfaceFactory
     */
    protected $criteriaFactory;

    /**
     * @var TweakwiseStockItemFactory
     */
    protected $tweakwiseStockItemFactory;

    /**
     * StockData constructor.
     *
     * @param StockItemRepositoryInterface $stockItemRepository
     * @param StockItemCriteriaInterfaceFactory $criteriaFactory
     * @param TweakwiseStockItemFactory $tweakwiseStockItemFactory
     */
    public function __construct(
        StockItemRepositoryInterface $stockItemRepository,
        StockItemCriteriaInterfaceFactory $criteriaFactory,
        TweakwiseStockItemFactory $tweakwiseStockItemFactory
    ) {
        $this->stockItemRepository = $stockItemRepository;
        $this->criteriaFactory = $criteriaFactory;
        $this->tweakwiseStockItemFactory = $tweakwiseStockItemFactory;
    }

    /**
     * @param Collection|StockCollection $collection
     * @return StockItemInterface[]
     */
    public function getStockItemMap(Collection|StockCollection $collection): array
    {
        if ($collection->count() === 0) {
            return [];
        }

        $entityIds = $collection->getAllIds();

        if (count($entityIds) === 0) {
            return [];
        }

        $criteria = $this->criteriaFactory->create();
        $criteria->setProductsFilter([$entityIds]);
        $items = $this->stockItemRepository->getList($criteria)->getItems();

        $map = [];
        foreach ($items as $item) {
            $productId = (int) $item->getProductId();
            $tweakwiseStockItem = $this->getTweakwiseStockItem($item);
            $map[$productId] = $tweakwiseStockItem;
        }

        return $map;
    }

    /**
     * @param StockItemInterface $item
     * @return StockItem
     */
    protected function getTweakwiseStockItem(StockItemInterface $item): StockItem
    {
        $tweakwiseStockItem = $this->tweakwiseStockItemFactory->create();
        $tweakwiseStockItem->setQty((int)$item->getQty());
        $stockStatus = (int) ($item->getIsInStock() || !$item->getManageStock());
        $tweakwiseStockItem->setIsInStock($stockStatus);

        return $tweakwiseStockItem;
    }
}
