<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing


declare(strict_types=1);

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator;

// phpcs:disable Magento2.Legacy.RestrictedCode.ZendDbSelect
use Magento\Bundle\Model\Product\Type;
use Magento\Framework\DataObject;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\Store;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Price\Collection as PriceCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Db_Select;
use Magento\Framework\Data\Collection as DataCollection;

class Price implements DecoratorInterface
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var float
     */
    private float $exchangeRate = 1.0;

    /**
     * Price constructor.
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        Config $config,
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->config = $config;
    }

    /**
     * @param Collection|PriceCollection $collection
     * @throws \Zend_Db_Statement_Exception
     */
    public function decorate(Collection|PriceCollection $collection): void
    {
        $store = $collection->getStore();
        $websiteId = $store->getWebsiteId();

        $priceSelect = $this->createPriceSelect($collection->getIds(), (int)$websiteId);
        // @phpstan-ignore-next-line
        $priceQueryResult = $priceSelect->getSelect()->query()->fetchAll();

        $currency = $store->getCurrentCurrency();
        $this->exchangeRate = $store->getCurrentCurrencyRate() > 0.00001
            ? (float)$store->getCurrentCurrencyRate()
            : 1.0;

        $priceFields = $this->config->getPriceFields($store->getId());
        $priceProductIds = array_column($priceQueryResult, 'entity_id');
        $productCollection = $this->collectionFactory->create()
            ->addFieldToFilter('entity_id', ['in' => $priceProductIds]);

        foreach ($priceQueryResult as $row) {
            $entityId = (int)$row['entity_id'];
            $row['currency'] = $currency->getCurrencyCode();
            $product = $productCollection->getItemById($entityId);

            $row = $this->applyCombinedPrices($row, $product, $store);
            $row = $this->applyPriceFields($row, $priceFields);
            $row['price'] = $this->getPriceValue($row, $priceFields);

            $collection->get($entityId)->setFromArray($row);
        }
    }

    /**
     * @param array $row
     * @param DataObject $product
     * @param Store $store
     * @return array
     */
    private function applyCombinedPrices(array $row, DataObject $product, Store $store): array
    {
        if (!$this->config->calculateCombinedPrices($store)) {
            return $row;
        }

        if ($this->isGroupedProduct($product)) {
            $prices = $this->calculateGroupedProductPrice($product);
            foreach ($prices as $price => $value) {
                $row[$price] = $value;
            }

            return $row;
        }

        if ($this->isBundleProduct($product)) {
            $prices = $this->calculateBundleProductPrice($product);
            foreach ($prices as $price => $value) {
                $row[$price] = $value;
            }
        }

        return $row;
    }

    /**
     * @param array $row
     * @param array $priceFields
     * @return array
     */
    private function applyPriceFields(array $row, array $priceFields): array
    {
        foreach ($priceFields as $priceField) {
            $row[$priceField] = $this->calculatePrice((float)$row[$priceField]);
        }

        return $row;
    }

    /**
     * @param float $price
     * @return float
     */
    private function calculatePrice(float $price): float
    {
        return $price * $this->exchangeRate;
    }

    /**
     * @param array $ids
     * @param int $websiteId
     * @return ProductCollection
     * phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
     */
    protected function createPriceSelect(array $ids, int $websiteId): ProductCollection
    {
        $priceSelect = $this->collectionFactory->create();
        $priceSelect
            ->addAttributeToFilter('entity_id', ['in' => $ids])
            ->addPriceData(0, $websiteId)
            ->getSelect()
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(
                [
                    'entity_id',
                    'price' => 'price_index.price',
                    'final_price' => 'price_index.final_price',
                    'min_price' => 'price_index.min_price',
                    'max_price' => 'price_index.max_price'
                ]
            );

        return $priceSelect;
    }

    /**
     * @param array $priceData
     * @param array $priceFields
     * @return float
     */
    protected function getPriceValue(array $priceData, array $priceFields): float
    {
        foreach ($priceFields as $field) {
            $value = isset($priceData[$field]) ? (float)$priceData[$field] : 0;
            if ($value > 0.00001) {
                return $value;
            }
        }

        return 0;
    }

    /**
     * @param DataObject $product
     * @return bool
     */
    protected function isGroupedProduct(DataObject $product): bool
    {
        return $product->getTypeId() === Grouped::TYPE_CODE;
    }

    /**
     * @param DataObject $product
     * @return bool
     */
    protected function isBundleProduct(DataObject $product): bool
    {
        return $product->getTypeId() === Type::TYPE_CODE;
    }

    /**
     * @param DataObject $product
     * @param callable $getAssociatedItems
     * @return array
     */
    protected function calculateProductPrice(
        DataObject $product,
        callable $getAssociatedItems
    ): array {
        $product = $this->collectionFactory->create()->getItemById($product);
        $associatedItems = $getAssociatedItems($product);

        // Convert collection to array if necessary
        if ($associatedItems instanceof DataCollection) {
            $associatedItems = $associatedItems->getItems();
        }

        $price = [
            'min_price' => 0.0,
            'max_price' => 0.0,
            'final_price' => 0.0,
        ];

        return array_reduce(
            $associatedItems,
            function ($result, $item) {
                $result['min_price'] += min([$item->getPrice(), $item->getFinalPrice()]) * $item->getQty();
                $result['max_price'] += max([$item->getPrice(), $item->getFinalPrice()]) * $item->getQty();
                $result['final_price'] += $item->getFinalPrice() * $item->getQty();
                return $result;
            },
            $price
        );
    }

    /**
     * @param DataObject $product
     * @return array
     */
    protected function calculateGroupedProductPrice(DataObject $product): array
    {
        return $this->calculateProductPrice(
            $product,
            function ($product) {
                return $product->getTypeInstance()->getAssociatedProducts($product);
            }
        );
    }

    /**
     * @param DataObject $product
     * @return array
     */
    protected function calculateBundleProductPrice(DataObject $product): array
    {
        $price = [
            'min_price' => 0.0,
            'max_price' => 0.0,
            'final_price' => 0.0,
        ];

        // @phpstan-ignore-next-line
        $selections = $product->getTypeInstance()->getSelectionsCollection(
            $product->getTypeInstance()->getOptionsIds($product), // @phpstan-ignore-line
            $product
        );

        if ($selections instanceof \Magento\Framework\Data\Collection) {
            $selections = $selections->getItems();
        }

        $groupedSelections = $this->groupSelectionsByOption($selections);

        foreach ($groupedSelections as $selectionsForOption) {
            $price = $this->accumulateBundleOptionPrices($price, $selectionsForOption);
        }

        return $price;
    }

    /**
     * Groups bundle selections by their option ID.
     *
     * @param array $selections
     * @return array
     */
    private function groupSelectionsByOption(array $selections): array
    {
        $grouped = [];
        foreach ($selections as $selection) {
            $optionId = $selection->getOptionId();
            $grouped[$optionId][] = $selection;
        }

        return $grouped;
    }

    /**
     * Accumulates the price information for a single bundle option.
     *
     * @param array $price
     * @param array $selectionsForOption
     * @return array
     */
    private function accumulateBundleOptionPrices(array $price, array $selectionsForOption): array
    {
        if (empty($selectionsForOption)) {
            return $price;
        }

        $selectionQty = (int)$selectionsForOption[0]->getDataByKey('selection_qty');
        $minPrice = min(array_map(fn($option) => $option->getPrice(), $selectionsForOption));
        $maxPrice = max(array_map(fn($option) => $option->getPrice(), $selectionsForOption));
        $finalPrice = $selectionsForOption[0]->getFinalPrice();

        $price['min_price'] += $minPrice * $selectionQty;
        $price['max_price'] += $maxPrice * $selectionQty;
        $price['final_price'] += $finalPrice * $selectionQty;

        return $price;
    }
}
