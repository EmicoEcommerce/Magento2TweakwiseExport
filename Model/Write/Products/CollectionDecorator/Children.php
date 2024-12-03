<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator;

use Tweakwise\Magento2TweakwiseExport\Exception\InvalidArgumentException;
use Tweakwise\Magento2TweakwiseExport\Model\DbResourceHelper;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Magento2TweakwiseExport\Model\Write\EavIteratorFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CompositeExportEntityInterface;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityChild;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\IteratorInitializer;
use Magento\Bundle\Model\Product\Type as Bundle;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\GroupedProduct\Model\ResourceModel\Product\Link;
use Tweakwise\Magento2TweakwiseExport\Model\ChildOptions;
use Magento\Store\Model\Store;
use Tweakwise\Magento2TweakwiseExport\Model\Config as TweakwiseConfig;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Stock\Collection as StockCollection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Price\Collection as PriceCollection;

class Children implements DecoratorInterface
{
    /**
     * @var ProductType
     */
    protected $productType;

    /**
     * @var EavIteratorFactory
     */
    protected $eavIteratorFactory;

    /**
     * @var ExportEntityFactory
     */
    protected $entityChildFactory;

    /**
     * @var Collection
     */
    protected $childEntities;

    /**
     * @var IteratorInitializer
     */
    protected $iteratorInitializer;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var DbResourceHelper
     */
    protected $dbResource;

    /**
     * @var int
     */
    protected $batchSize;

    /**
     * ChildId constructor.
     *
     * @param ProductType $productType
     * @param EavIteratorFactory $eavIteratorFactory
     * @param IteratorInitializer $iteratorInitializer
     * @param ExportEntityFactory $entityChildFactory
     * @param CollectionFactory $collectionFactory
     * @param Helper $helper
     * @param DbResourceHelper $dbResource
     * @param TweakwiseConfig $config
     */
    public function __construct(
        ProductType $productType,
        EavIteratorFactory $eavIteratorFactory,
        IteratorInitializer $iteratorInitializer,
        ExportEntityFactory $entityChildFactory,
        CollectionFactory $collectionFactory,
        Helper $helper,
        DbResourceHelper $dbResource,
        TweakwiseConfig $config,
        private readonly WebsiteLink $websiteLink
    ) {
        $this->productType = $productType;
        $this->eavIteratorFactory = $eavIteratorFactory;
        $this->entityChildFactory = $entityChildFactory;
        $this->iteratorInitializer = $iteratorInitializer;
        $this->collectionFactory = $collectionFactory;
        $this->helper = $helper;
        $this->dbResource = $dbResource;
        $this->batchSize = $config->getBatchSizeProductsChildren();
    }

    /**
     * Decorate items with extra data or remove items completely
     *
     * @param Collection|StockCollection|PriceCollection $collection
     */
    public function decorate(Collection|StockCollection|PriceCollection $collection): void
    {
        $this->childEntities = $this->collectionFactory->create(['store' => $collection->getStore()]);
        $this->createChildEntities($collection);
        $this->websiteLink->decorate($this->childEntities);
        $this->loadChildAttributes($collection->getStore());
    }

    /**
     * @param Collection|StockCollection|PriceCollection $collection
     */
    protected function createChildEntities(Collection|StockCollection|PriceCollection $collection): void
    {
        foreach ($this->getCompositeEntities($collection) as $typeId => $group) {
            // Create fake product type to trick type factory to use getTypeId
            /** @var Product $fakeProduct */
            $fakeProduct = new DataObject(['type_id' => $typeId]);
            $type = $this->productType->factory($fakeProduct);

            $parentIds = array_keys($group);
            if ($type instanceof Bundle) {
                $this->addBundleChildren($collection, $parentIds);
            } elseif ($type instanceof Grouped) {
                $this->addLinkChildren($collection, $parentIds, Link::LINK_TYPE_GROUPED);
            } elseif ($type instanceof Configurable) {
                $this->addConfigurableChildren($collection, $parentIds);
            } else {
                foreach ($parentIds as $parentId) {
                    foreach ($type->getChildrenIds($parentId, false) as $childId) {
                        $this->addChild($collection, (int) $parentId, (int) $childId);
                    }
                }
            }
        }
    }

    /**
     * @param Collection|StockCollection|PriceCollection $collection
     * @return CompositeExportEntityInterface[][]
     */
    protected function getCompositeEntities(Collection|StockCollection|PriceCollection $collection): array
    {
        $compositeEntities = [];
        foreach ($collection as $entity) {
            if (!$entity instanceof CompositeExportEntityInterface) {
                continue;
            }

            $compositeEntities[$entity->getTypeId()][$entity->getId()] = $entity;
        }

        return $compositeEntities;
    }

    /**
     * @param Collection|StockCollection|PriceCollection $collection
     * @param int[] $parentIds
     */
    protected function addBundleChildren(Collection|StockCollection|PriceCollection $collection, array $parentIds): void
    {
        $connection = $this->dbResource->getConnection();
        $select = $connection->select();

        if ($this->helper->isEnterprise()) {
            $select
                ->from(['product_table' => $this->dbResource->getTableName('catalog_product_entity')])
                ->reset('columns')
                ->columns(['parent_product_id' => 'product_table.entity_id'])
                ->join(
                    ['bundle_selection' => $this->dbResource->getTableName('catalog_product_bundle_selection')],
                    'bundle_selection.parent_product_id = product_table.row_id',
                    ['product_id']
                )
                ->where('product_table.row_id IN (?)', $parentIds);
        } else {
            $select
                ->from(['bundle_selection' => $this->dbResource->getTableName('catalog_product_bundle_selection')])
                ->columns(['product_id', 'parent_product_id'])
                ->where('parent_product_id IN (?)', $parentIds);
        }

        // Add Required bundle option data
        $select->join(
            ['bundle_option' => $this->dbResource->getTableName('catalog_product_bundle_option')],
            'bundle_selection.option_id = bundle_option.option_id',
            ['required' => 'bundle_option.required', 'option_id' => 'bundle_option.option_id']
        );

        $query = $select->query();
        while ($row = $query->fetch()) {
            $bundleOption = new ChildOptions(
                (int) $row['option_id'],
                (bool) $row['required']
            );
            $this->addChild(
                $collection,
                (int) $row['parent_product_id'],
                (int) $row['product_id'],
                $bundleOption
            );
        }
    }

    /**
     * @param Collection|StockCollection|PriceCollection $collection
     * @param int[] $parentIds
     * @param int $typeId
     */
    protected function addLinkChildren(
        Collection|StockCollection|PriceCollection $collection,
        array $parentIds,
        $typeId
    ): void {
        $connection = $this->dbResource->getConnection();
        $select = $connection->select();

        if ($this->helper->isEnterprise()) {
            $select
                ->from(['product_table' => $this->dbResource->getTableName('catalog_product_entity')])
                ->reset('columns')
                ->columns(['product_id' => 'product_table.entity_id'])
                ->join(
                    ['link_table' => $this->dbResource->getTableName('catalog_product_link')],
                    'link_table.product_id = product_table.row_id',
                    ['linked_product_id']
                )
                ->where('product_table.entity_id IN (?)', $parentIds)
                ->where('link_table.link_type_id = ?', $typeId);
        } else {
            $select
                ->from(['link_table' => $this->dbResource->getTableName('catalog_product_link')])
                ->where('link_type_id = ?', $typeId)
                ->where('product_id IN (?)', $parentIds);
        }

        $query = $select->query();
        while ($row = $query->fetch()) {
            $this->addChild($collection, (int) $row['product_id'], (int) $row['linked_product_id']);
        }
    }

    /**
     * @param Collection|StockCollection|PriceCollection $collection
     * @param int[] $parentIds
     */
    protected function addConfigurableChildren(
        Collection|StockCollection|PriceCollection $collection,
        array $parentIds
    ): void {
        $connection = $this->dbResource->getConnection();
        $select = $connection->select();

        if ($this->helper->isEnterprise()) {
            $select
                ->from(['product_table' => $this->dbResource->getTableName('catalog_product_entity')])
                ->reset('columns')
                ->columns(['parent_id' => 'product_table.entity_id'])
                ->join(
                    ['link_table' => $this->dbResource->getTableName('catalog_product_super_link')],
                    'link_table.parent_id = product_table.row_id',
                    ['product_id']
                )
                ->where('product_table.entity_id IN (?)', $parentIds);
        } else {
            $select
                ->from(['link_table' => $this->dbResource->getTableName('catalog_product_super_link')])
                ->columns(['product_id', 'parent_id'])
                ->where('parent_id IN (?)', $parentIds);
        }

        $query = $select->query();
        while ($row = $query->fetch()) {
            $this->addChild($collection, (int) $row['parent_id'], (int) $row['product_id']);
        }
    }

    /**
     * @param Collection|StockCollection|PriceCollection $collection
     * @param int $parentId
     * @param int $childId
     * @param ChildOptions|null $childOptions
     * phpcs:disable Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
     * phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch
     */
    protected function addChild(
        Collection|StockCollection|PriceCollection $collection,
        int $parentId,
        int $childId,
        ChildOptions $childOptions = null
    ): void {
        if (!$this->childEntities->has($childId)) {
            $child = $this->entityChildFactory->createChild(
                [
                    'store' => $collection->getStore(),
                    'data' => ['entity_id' => $childId],
                ]
            );
            $this->childEntities->add($child);
        } else {
            $child = $this->childEntities->get($childId);
        }

        /** @var ExportEntityChild $child */
        if ($childOptions) {
            $child->setChildOptions($childOptions);
        }

        try {
            $parent = $collection->get($parentId);
            if ($parent instanceof CompositeExportEntityInterface) {
                $parent->addChild($child);
            }
        } catch (InvalidArgumentException $exception) {
            // no implementation, parent was not found
        }
    }

    /**
     * Load child attribute data
     * @param Store $store
     */
    protected function loadChildAttributes(Store $store): void
    {
        if ($this->childEntities->count() === 0) {
            return;
        }

        $iterator = $this->eavIteratorFactory->create(
            [
                'entityCode' => Product::ENTITY,
                'attributes' => [],
                'batchSize' => $this->batchSize
            ]
        );
        $iterator->setEntityIds($this->childEntities->getIds());
        $iterator->setStore($store);
        $this->iteratorInitializer->initializeAttributes($iterator);

        foreach ($iterator as $childData) {
            $childId = (int) $childData['entity_id'];
            $childEntity = $this->childEntities->get($childId);
            $childEntity->setFromArray($childData);
        }
    }
}
