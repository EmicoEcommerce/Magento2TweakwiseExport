<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\StockData;

use Tweakwise\Magento2TweakwiseExport\Model\StockItem;
use Tweakwise\Magento2TweakwiseExport\Model\StockItemFactory as TweakwiseStockItemFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\DbResourceHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Tweakwise\Magento2TweakwiseExport\Model\StockSourceProviderFactory;
use Tweakwise\Magento2TweakwiseExport\Model\StockResolverFactory;
use Tweakwise\Magento2TweakwiseExport\Model\DefaultStockProviderInterfaceFactory;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Zend_Db_Expr;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Stock\Collection as StockCollection;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolver;

/**
 * Class DefaultImplementation
 */
class SourceItemMapProvider implements StockMapProviderInterface
{
    /**
     * @var TweakwiseStockItemFactory
     */
    protected $tweakwiseStockItemFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var StockResolverInterface
     */
    protected $stockResolver;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface
     */
    protected $stockSourceProvider;

    /**
     * @var StockSourceProviderFactory
     */
    protected $stockSourceProviderFactory;

    /**
     * @var StockResolverFactory
     */
    protected $stockResolverFactory;

    /**
     * @var DbResourceHelper
     */
    protected $dbResource;

    /**
     * @var DefaultStockProviderInterfaceFactory
     */
    protected $defaultStockProviderFactory;

    /**
     * @var DefaultStockProviderInterface
     */
    protected $defaultStockProvider;

    /**
     * @var StockIndexTableNameResolver
     */
    protected $stockIndexTableNameResolver;

    /**
     * StockData constructor.
     *
     * @param DbResourceHelper $dbResource
     * @param StockSourceProviderFactory $stockSourceProviderFactory
     * @param TweakwiseStockItemFactory $tweakwiseStockItemFactory
     * @param StoreManagerInterface $storeManager
     * @param StockResolverFactory $stockResolverFactory
     * @param DefaultStockProviderInterfaceFactory $defaultStockProviderFactory
     * @param DbResourceHelper $resourceHelper
     */
    public function __construct(
        DbResourceHelper $dbResource,
        StockSourceProviderFactory $stockSourceProviderFactory,
        TweakwiseStockItemFactory $tweakwiseStockItemFactory,
        StoreManagerInterface $storeManager,
        StockResolverFactory $stockResolverFactory,
        DefaultStockProviderInterfaceFactory $defaultStockProviderFactory,
        DbResourceHelper $resourceHelper,
        StockIndexTableNameResolver $stockIndexTableNameResolver
    ) {
        $this->dbResource = $dbResource;
        $this->stockSourceProviderFactory = $stockSourceProviderFactory;
        $this->tweakwiseStockItemFactory = $tweakwiseStockItemFactory;
        $this->storeManager = $storeManager;
        $this->stockResolverFactory = $stockResolverFactory;
        $this->dbResource = $resourceHelper;
        $this->defaultStockProviderFactory = $defaultStockProviderFactory;
        $this->stockIndexTableNameResolver = $stockIndexTableNameResolver;
    }

    /**
     * @param Collection|StockCollection $collection
     * @return StockItem[]
     * @throws LocalizedException
     * @throws \Zend_Db_Statement_Exception
     * phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getStockItemMap(Collection|StockCollection $collection): array
    {
        if ($collection->count() === 0) {
            return [];
        }

        $entityIds = $collection->getAllIds();

        $store = $collection->getStore();
        $stockId = $this->getStockIdForStoreId($store);

        $dbConnection = $this->dbResource->getConnection();

        $stockIndexTableName = $this->stockIndexTableNameResolver->execute($stockId);
        $reservationTableName = $this->dbResource->getTableName('inventory_reservation');
        $productTableName = $this->dbResource->getTableName('catalog_product_entity');

        $reservationSelect = $dbConnection
            ->select()
            ->from($reservationTableName)
            ->where('stock_id = ?', $stockId)
            ->reset('columns')
            ->columns(
                [
                    'sku',
                    'stock_id',
                    'r_quantity' => "SUM(`$reservationTableName`.`quantity`)"
                ]
            )
            ->group("$reservationTableName.sku");

        // Todo We should check if we can use magento's api for this as this is feeling rather sensitive.
        $sourceItemSelect = $dbConnection
            ->select()
            ->from($stockIndexTableName)
            ->reset('columns')
            ->columns(
                [
                    'sku',
                    's_quantity' => "$stockIndexTableName.quantity",
                    's_is_salable' => "$stockIndexTableName.is_salable"
                ]
            );

        $select = $dbConnection
            ->select()
            ->from($productTableName)
            ->reset('columns');

        $select->joinLeft(
            ['s' => $sourceItemSelect],
            "s.sku = $productTableName.sku",
            []
        );

        $select->joinLeft(
            ['r' => $reservationSelect],
            "r.sku = $productTableName.sku AND r.stock_id = $stockId",
            []
        )
        ->where("$productTableName.entity_id IN (?)", $entityIds)
        ->columns(
            [
                'product_entity_id' => "$productTableName.entity_id",
                'qty' => new Zend_Db_Expr('COALESCE(s.s_quantity,0) + COALESCE(r.r_quantity,0)'),
                'is_in_stock' => 'COALESCE(s.s_is_salable,0)'
            ]
        );

        $result = $select->query();
        $map = [];

        while ($row = $result->fetch()) {
            $map[$row['product_entity_id']] = $this->getTweakwiseStockItem($row);
        }

        return $map;
    }

    /**
     * This is necessary to remain compatible with Magento 2.2.X
     * setup:di:compile fails when there is a reference to a non existing Interface or Class in the constructor
     *
     * @return GetSourcesAssignedToStockOrderedByPriorityInterface
     */
    protected function getStockSourceProvider(): GetSourcesAssignedToStockOrderedByPriorityInterface
    {
        if (!$this->stockSourceProvider) {
            $this->stockSourceProvider = $this->stockSourceProviderFactory->create();
        }

        return $this->stockSourceProvider;
    }

    /**
     * @return DefaultStockProviderInterface
     */
    protected function getDefaultStockProvider(): DefaultStockProviderInterface
    {
        if (!$this->defaultStockProvider) {
            $this->defaultStockProvider = $this->defaultStockProviderFactory->create();
        }

        return $this->defaultStockProvider;
    }

    /**
     * @param Store $store
     * @return int|null
     * @throws LocalizedException
     */
    protected function getStockIdForStoreId(Store $store): ?int
    {
        $websiteCode = $store->getWebsite()->getCode();
        return $this->getStockResolver()->execute('website', $websiteCode)->getStockId();
    }

    /**
     * This is necessary to remain compatible with Magento 2.2.X
     * setup:di:compile fails when there is a reference to a non existing Interface or Class in the constructor
     *
     * @return StockResolverInterface
     */
    protected function getStockResolver(): StockResolverInterface
    {
        if (!$this->stockResolver) {
            $this->stockResolver = $this->stockResolverFactory->create();
        }

        return $this->stockResolver;
    }

    /**
     * @param array $item
     * @return StockItem
     */
    protected function getTweakwiseStockItem(array $item): StockItem
    {
        $tweakwiseStockItem = $this->tweakwiseStockItemFactory->create();

        $qty = (int)$item['qty'];
        $isInStock = (int)$item['is_in_stock'];

        $tweakwiseStockItem->setQty($qty);
        $tweakwiseStockItem->setIsInStock($isInStock);

        return $tweakwiseStockItem;
    }
}
