<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products;

use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;
use Tweakwise\Magento2TweakwiseExport\Exception\InvalidArgumentException;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Magento2TweakwiseExport\Model\Write\EavIterator;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\DecoratorInterface;
use Generator;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Event\Manager;
use Magento\Framework\Model\ResourceModel\Db\Context as DbContext;
use Tweakwise\Magento2TweakwiseExport\Model\Config as TweakwiseConfig;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityConfigurable;
use Traversable;

class Iterator extends EavIterator
{
    /**
     * @var ExportEntityFactory
     */
    protected $entityFactory;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var DecoratorInterface[]
     */
    protected $collectionDecorators;

    /**
     * Attribute codes currently selected for brand and image.
     *
     * @var array{brand: string, image: string}
     */
    private array $activeStoreAttributes = ['brand' => '', 'image' => ''];

    /**
     * Attributes selected by IteratorInitializer and always required for export.
     *
     * @var array<string, true>
     */
    private array $defaultAttributeCodes = [];

    /**
     * Reference counter for dynamically-selected attributes (brand/image).
     *
     * @var array<string, int>
     */
    private array $dynamicAttributeRefCounts = [];

    /**
     * Iterator constructor.
     *
     * @param Helper $helper
     * @param EavConfig $eavConfig
     * @param DbContext $dbContext
     * @param Manager $eventManager
     * @param ExportEntityFactory $entityFactory
     * @param CollectionFactory $collectionFactory
     * @param IteratorInitializer $iteratorInitializer
     * @param DecoratorInterface[] $collectionDecorators
     * @param TweakwiseConfig $config
     */
    public function __construct(
        Helper $helper,
        EavConfig $eavConfig,
        DbContext $dbContext,
        Manager $eventManager,
        ExportEntityFactory $entityFactory,
        CollectionFactory $collectionFactory,
        IteratorInitializer $iteratorInitializer,
        array $collectionDecorators,
        private readonly TweakwiseConfig $config
    ) {
        parent::__construct(
            $helper,
            $eavConfig,
            $dbContext,
            $eventManager,
            $config,
            Product::ENTITY,
            [],
            $config->getBatchSizeProducts()
        );

        $this->entityFactory = $entityFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionDecorators = $collectionDecorators;

        $iteratorInitializer->initializeAttributes($this);
        $this->defaultAttributeCodes = array_fill_keys(array_keys($this->attributesByCode), true);
    }

    /**
     * Override setStore to dynamically select the brand and image EAV attributes.
     * Dynamic attributes are reference-counted to avoid removing attributes still
     * needed by default export set or by the other dynamic field.
     *
     * @param Store $store
     * @return void
     */
    public function setStore(Store $store): void
    {
        parent::setStore($store);

        $newBrand = $this->config->getBrandAttribute($store);
        $newImage = $this->config->getImageAttribute($store);

        $this->syncStoreAttribute('brand', $newBrand);
        $this->syncStoreAttribute('image', $newImage);
    }

    /**
     * Add the new attribute code to the EAV query and release the previous one when
     * either the code changed or it was cleared.
     *
     * @param string $type
     * @param string $next
     * @return void
     */
    private function syncStoreAttribute(string $type, string $next): void
    {
        $previous = $this->activeStoreAttributes[$type];

        if ($previous === $next) {
            return;
        }

        if ($previous !== '') {
            $this->releaseDynamicAttribute($previous);
        }

        if ($next !== '') {
            $this->acquireDynamicAttribute($next);
        }

        $this->activeStoreAttributes[$type] = $next;
    }

    /**
     * @param string $attributeCode
     * @return void
     */
    private function acquireDynamicAttribute(string $attributeCode): void
    {
        if (!isset($this->dynamicAttributeRefCounts[$attributeCode])) {
            $this->dynamicAttributeRefCounts[$attributeCode] = 0;
            if (!isset($this->attributesByCode[$attributeCode])) {
                $this->selectAttribute($attributeCode);
            }
        }

        $this->dynamicAttributeRefCounts[$attributeCode]++;
    }

    /**
     * @param string $attributeCode
     * @return void
     */
    private function releaseDynamicAttribute(string $attributeCode): void
    {
        if (!isset($this->dynamicAttributeRefCounts[$attributeCode])) {
            return;
        }

        $this->dynamicAttributeRefCounts[$attributeCode]--;
        if ($this->dynamicAttributeRefCounts[$attributeCode] > 0) {
            return;
        }

        unset($this->dynamicAttributeRefCounts[$attributeCode]);

        if (isset($this->defaultAttributeCodes[$attributeCode])) {
            return;
        }

        if (isset($this->attributesByCode[$attributeCode])) {
            $this->removeAttribute($attributeCode);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        $batch = $this->collectionFactory->create(['store' => $this->store]);

        foreach (parent::getIterator() as $entityData) {
            $entity = $this->entityFactory->create(
                [
                    'store' => $this->store,
                    'data' => $entityData
                ]
            );

            if (!$entity->shouldProcess()) {
                continue;
            }

            $batch->add($entity);

            if ($batch->count() !== $this->batchSize) {
                continue;
            }

            // After PHP7+ we can use yield from
            foreach ($this->processBatch($batch) as $processedEntity) {
                yield $processedEntity;
            }

            $batch = $this->collectionFactory->create(['store' => $this->store]);
        }

        // After PHP7+ we can use yield from
        foreach ($this->processBatch($batch) as $processedEntity) {
            yield $processedEntity;
        }
    }

    /**
     * @param Collection $collection
     * @return Generator
     */
    protected function processBatch(Collection $collection)
    {
        if ($collection->count()) {
            foreach ($this->collectionDecorators as $decorator) {
                $decorator->decorate($collection);
            }
        }

        $brandAttribute = $this->config->getBrandAttribute($this->store);
        $imageAttribute = $this->config->getImageAttribute($this->store);

        foreach ($collection->getExported() as $entity) {
            if ($this->config->isGroupedExport($this->store) && $entity instanceof ExportEntityConfigurable) {
                continue;
            }

            yield [
                'entity_id' => $entity->getId(),
                'name' => $entity->getName(),
                'price' => $entity->getPrice(),
                'stock' => (int) round($entity->getStockQty()),
                'groupcode' => $entity->getGroupCode(),
                'url_key' => $this->getEntityAttributeScalar($entity, 'url_key'),
                'brand' => $brandAttribute !== '' ? $this->getEntityAttributeScalar($entity, $brandAttribute) : null,
                'image' => $imageAttribute !== '' ? $this->getEntityAttributeScalar($entity, $imageAttribute) : null,
                'categories' => $entity->getCategories(),
                'attributes' => $entity->getAttributes(),
            ];
        }
    }

    /**
     * Safely retrieve the first scalar value of an attribute from an entity, returning null when not set.
     *
     * @param ExportEntity $entity
     * @param string $attributeCode
     * @return string|null
     */
    protected function getEntityAttributeScalar(ExportEntity $entity, string $attributeCode): ?string
    {
        try {
            $value = $entity->getAttribute($attributeCode, false);
        } catch (InvalidArgumentException $e) {
            return null;
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        return $value !== false && $value !== null ? (string) $value : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function addStoreFilter(Select $select): void
    {
        $storeTable = $this->getResources()->getTableName('store');
        $cpwTable = $this->getResources()->getTableName('catalog_product_website');

        $subSelect = $this->getConnection()->select()
            ->from($storeTable, ['website_id'])
            ->where('store_id = ?', $this->store->getId());

        $select->join(['cpw' => $cpwTable], 'cpw.product_id = ' . $this->getEntityType()->getEntityTable() . '.entity_id')
            ->where('cpw.website_id = (' . $subSelect . ')');
    }
}
