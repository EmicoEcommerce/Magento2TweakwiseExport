<?php
/**
 * @author Emico <info@emico.nl>
 * @copyright (c) Emico B.V. 2017
 */

namespace Emico\TweakwiseExport\Model\Write\Products\CollectionDecorator;

use Emico\TweakwiseExport\Model\Config;
use Emico\TweakwiseExport\Model\Helper;
use Emico\TweakwiseExport\Model\Write\Products\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Db_Select;

class Price implements DecoratorInterface
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * Price constructor.
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(CollectionFactory $collectionFactory, StoreManagerInterface $storeManager, Config $config, Helper $helper)
    {
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->helper = $helper;
    }

    /**
     * {@inheritdoc}
     */
    public function decorate(Collection $collection)
    {
        $identifier = $this->helper->getIdentifierField();
        $collectionSelect = $this->collectionFactory->create()
            ->addAttributeToFilter($identifier, ['in' => $collection->getIds()])
            ->addPriceData(0, $this->storeManager->getStore($collection->getStoreId())->getWebsiteId())
            ->getSelect()
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns([
                $identifier,
                'price' => 'price_index.price',
                'final_price' => 'price_index.final_price',
                'old_price' => 'price_index.price',
                'min_price' => 'price_index.min_price',
                'max_price' => 'price_index.max_price',
            ]);
        $collectionQuery = $collectionSelect->query();

        while ($row = $collectionQuery->fetch()) {
            $entityId = $row[$identifier];
            $row['price'] = $this->getPriceValue($collection->getStoreId(), $row);
            $collection->get($entityId)->setFromArray($row);
        }
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
            $value = (float)$priceData[$field];
            if ($value > 0.00001) {
                return $value;
            }
        }

        return 0.0;
    }
}