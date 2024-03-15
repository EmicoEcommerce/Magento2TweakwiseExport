<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator;

// phpcs:disable Magento2.Legacy.RestrictedCode.ZendDbSelect
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Price\Collection as PriceCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Db_Select;

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
     * Price constructor.
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        Config $config
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
        $websiteId = $collection->getStore()->getWebsiteId();
        $priceSelect = $this->createPriceSelect($collection->getIds(), $websiteId);

        $priceQuery = $priceSelect->getSelect()->query();
        $currency = $collection->getStore()->getCurrentCurrency();
        $exchangeRate = 1;

        if ($collection->getStore()->getCurrentCurrencyRate() > 0.00001) {
            $exchangeRate = (float)$collection->getStore()->getCurrentCurrencyRate();
        }

        $priceFields = $this->config->getPriceFields($collection->getStore()->getId());

        while ($row = $priceQuery->fetch()) {
            $entityId = $row['entity_id'];
            $row['currency'] = $currency->getCurrencyCode();
            $row['price'] = $this->getPriceValue($collection->getStore()->getId(), $row);

            //do all prices * exchange rate
            foreach ($priceFields as $priceField) {
                $row[$priceField] = (float) ($row[$priceField] * $exchangeRate);
            }

            $collection->get($entityId)->setFromArray($row);
        }
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
     * @param int $storeId
     * @param array $priceData
     * @return float
     */
    protected function getPriceValue(int $storeId, array $priceData): float
    {
        $priceFields = $this->config->getPriceFields($storeId);
        foreach ($priceFields as $field) {
            $value = isset($priceData[$field]) ? (float)$priceData[$field] : 0;
            if ($value > 0.00001) {
                return $value;
            }
        }

        return 0;
    }
}
