<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator;

// phpcs:disable Magento2.Legacy.RestrictedCode.ZendDbSelect
use Magento\Bundle\Model\Product\Type;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\Store;
use Magento\Tax\Model\Calculation;
use Tweakwise\Magento2TweakwiseExport\Exception\InvalidArgumentException;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Price\Collection as PriceCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntity;
use Zend_Db_Select;
use Magento\Tax\Model\TaxCalculation;
use Magento\Framework\Data\Collection as DataCollection;
use Magento\Store\Model\ScopeInterface;

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
     * @param Calculation $taxCalculation
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        Config $config,
        private readonly Calculation $taxCalculation,
        private readonly ScopeConfigInterface $scopeConfig
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
        $websiteId = $collection->getStore()->getWebsiteId();

        $priceSelect = $this->createPriceSelect($collection->getIds(), $websiteId);
        $priceQuery = $priceSelect->getSelect()->query();

        $currency = $collection->getStore()->getCurrentCurrency();
        if ($collection->getStore()->getCurrentCurrencyRate() > 0.00001) {
            $exchangeRate = (float)$collection->getStore()->getCurrentCurrencyRate();
        }

        $this->exchangeRate = $exchangeRate ?? 1.0;

        $priceFields = $this->config->getPriceFields($collection->getStore()->getId());

        while ($row = $priceQuery->fetch()) {
            $entityId = $row['entity_id'];
            $row['currency'] = $currency->getCurrencyCode();
            $row['price'] = $this->getPriceValue($row, $priceFields);

            $product = $this->collectionFactory->create()->getItemById($entityId);
            $taxClassId = $this->getTaxClassId($collection->get($entityId));

            if ($this->config->calculateCombinedPrices($store) && $this->isGroupedProduct($product)) {
                $row['price'] = $this->calculateGroupedProductPrice($entityId, $store, $taxClassId);
            } elseif ($this->config->calculateCombinedPrices($store) && $this->isBundleProduct($product)) {
                $row['price'] = $this->calculateBundleProductPrice($entityId, $store, $taxClassId);
            } else {
                foreach ($priceFields as $priceField) {
                    $row[$priceField] = $this->calculatePrice((float)$row[$priceField], $taxClassId, $store);
                }
            }

            $collection->get($entityId)->setFromArray($row);
        }
    }

    /**
     * @param float $value
     * @return float
     */
    private function applyRoundingMethod(float $value): float
    {
        return round($value, 2);
    }

    /**
     * @param float $price
     * @param int|null $taxClassId
     * @param Store $store
     * @return float
     */
    private function calculatePrice(float $price, ?int $taxClassId, Store $store): float
    {
        if ($this->config->addVat($store)) {
            $price = $this->addVat($price, $taxClassId, $store);
        }

        $price = $this->calculateExchangeRate($price);

        return $price;
    }

    /**
     * @param float $price
     * @param int|null $taxClassId
     * @param Store $store
     * @return float
     */
    private function addVat(float $price, ?int $taxClassId, Store $store): float
    {
        $rateRequest = $this->taxCalculation->getRateRequest(null, null, null, $store);
        $taxRate = $this->taxCalculation->getRate($rateRequest->setProductClassId($taxClassId));

        $price = $this->applyRoundingMethod(
            $price * (1 + $taxRate / 100)
        );

        return $price;
    }

    /**
     * @param float $price
     * @return float
     */
    private function calculateExchangeRate(float $price): float
    {
        return $this->applyRoundingMethod(
            $price * $this->exchangeRate
        );
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
     * @param ProductInterface $product
     * @return int|null
     */
    protected function getTaxClassId(ExportEntity $product): ?int
    {
        try {
            if (isset($product->getAttribute('tax_class_id')[0])) {
                return $product->getAttribute('tax_class_id')[0];
            }

            return null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param ProductInterface $product
     * @return bool
     */
    protected function isGroupedProduct(ProductInterface $product): bool
    {
        return $product?->getTypeId() === Grouped::TYPE_CODE;
    }

    /**
     * @param ProductInterface $product
     * @return bool
     */
    protected function isBundleProduct(ProductInterface $product): bool
    {
        return $product?->getTypeId() === Type::TYPE_CODE;
    }

    /**
     * @param int $entityId
     * @param callable $getAssociatedItems
     * @param Store $store
     * @param int|null $taxClassId
     * @return float
     */
    protected function calculateProductPrice(
        int $entityId,
        callable $getAssociatedItems,
        Store $store,
        ?int $taxClassId
    ): float {
        $product = $this->collectionFactory->create()->getItemById($entityId);
        $associatedItems = $getAssociatedItems($product);

        // Convert collection to array if necessary
        if ($associatedItems instanceof DataCollection) {
            $associatedItems = $associatedItems->getItems();
        }

        return array_reduce(
            $associatedItems,
            function ($total, $item) use ($store, $taxClassId) {
                $basePrice = $item->getPrice();
                $price = $this->calculatePrice(
                    $basePrice,
                    $taxClassId,
                    $store
                );
                return $total + ($price * $item->getQty());
            },
            0
        );
    }

    /**
     * @param int $entityId
     * @param Store $store
     * @param int|null $taxClassId
     * @return float
     */
    protected function calculateGroupedProductPrice(int $entityId, Store $store, ?int $taxClassId): float
    {
        return $this->calculateProductPrice(
            $entityId,
            function ($product) {
                return $product->getTypeInstance()->getAssociatedProducts($product);
            },
            $store,
            $taxClassId
        );
    }

    /**
     * @param int $entityId
     * @param Store $store
     * @param int|null $taxClassId
     * @return float
     */
    protected function calculateBundleProductPrice(int $entityId, Store $store, ?int $taxClassId): float
    {
        return $this->calculateProductPrice(
            $entityId,
            function ($product) {
                return $product->getTypeInstance()->getSelectionsCollection(
                    $product->getTypeInstance()->getOptionsIds($product),
                    $product
                );
            },
            $store,
            $taxClassId
        );
    }
}
