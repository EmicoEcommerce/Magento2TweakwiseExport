<?php

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model\Write;

use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Magento2TweakwiseExport\Model\Logger;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\Source\SourceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Profiler;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Price\Iterator;

class Price implements WriterInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Iterator
     */
    protected $iterator;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * @var array
     */
    protected $attributeOptionMap = [];

    /**
     * Products constructor.
     *
     * @param Config $config
     * @param Iterator $iterator
     * @param StoreManager $storeManager
     * @param Helper $helper
     * @param Logger $log
     * @param EavConfig $eavConfig
     */
    public function __construct(
        Config $config,
        Iterator $iterator,
        StoreManager $storeManager,
        Helper $helper,
        Logger $log,
        EavConfig $eavConfig
    ) {
        $this->config = $config;
        $this->iterator = $iterator;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->log = $log;
        $this->eavConfig = $eavConfig;
    }

    /**
     * @param Writer $writer
     * @param XMLWriter $xml
     * @param StoreInterface|null $store
     */
    public function write(Writer $writer, XMLWriter $xml, ?StoreInterface $store = null): void
    {
        $xml->startElement('items');

        $stores = [];
        if ($store) {
            $stores[] = $store;
        } else {
            $stores = $this->storeManager->getStores();
        }

        /** @var Store $store */
        foreach ($stores as $store) {
            if ($this->config->isEnabled($store)) {
                $profileKey = 'stock::' . $store->getCode();
                try {
                    Profiler::start($profileKey);
                    $this->exportStore($writer, $xml, $store);
                } finally {
                    Profiler::stop($profileKey);
                }

                $this->log->debug(sprintf('Export price for store %s', $store->getName()));
            } else {
                $this->log->debug(sprintf('Skip price for store %s (disabled)', $store->getName()));
            }
        }

        $xml->endElement(); // items
        $writer->flush();
    }

    /**
     * @param Writer $writer
     * @param XMLWriter $xml
     * @param Store $store
     * @param int[] $entityIds
     */
    public function exportStore(Writer $writer, XMLWriter $xml, Store $store, array $entityIds = []): void
    {
        $this->iterator->setStore($store);
        // Purge iterator entity ids for each store
        $this->iterator->setEntityIds($entityIds);

        foreach ($this->iterator as $index => $data) {
            $this->writeProduct($xml, $store->getId(), $data);
            // Flush every so often
            if ($index % 100 === 0) {
                $writer->flush();
            }
        }

        // Flush any remaining products
        $writer->flush();
    }

    /**
     * @param XMLWriter $xml
     * @param int $storeId
     * @param array $data
     */
    protected function writeProduct(XMLWriter $xml, $storeId, array $data): void
    {
        //don't export products without price, this can happen when the price isn't in the price index table.
        if ($data['price'] === null) {
            return;
        }

        $xml->startElement('item');

        // Write product base data
        $tweakwiseId = $this->helper->getTweakwiseId($storeId, $data['entity_id']);
        $xml->writeElement('id', $tweakwiseId);
        $xml->startElement('values');
        $xml->writeElement('value', $this->scalarValue($data['price']));
        $xml->endElement(); // </values>

        $xml->endElement(); // </item>

        $this->log->debug(sprintf('Export product price [%s] %s', $tweakwiseId, $data['price']));
    }

    /**
     * Get scalar value from object, array or scalar value
     *
     * @param mixed $value
     *
     * @return string|array
     * phpcs:disable Magento2.Functions.DiscouragedFunction.Discouraged
     */
    protected function scalarValue($value)
    {
        if (is_array($value)) {
            $data = [];
            foreach ($value as $key => $childValue) {
                $data[$key] = $this->scalarValue($childValue);
            }

            return $data;
        }

        if (is_object($value)) {
            if (method_exists($value, 'toString')) {
                $value = $value->toString();
            } elseif (method_exists($value, '__toString')) {
                $value = (string)$value;
            } else {
                $value = spl_object_hash($value);
            }
        }

        if (is_numeric($value)) {
            $value = $this->normalizeExponent($value);
        }

        if ($value !== null) {
            return html_entity_decode($value, ENT_NOQUOTES | ENT_HTML5);
        }

        return '';
    }

    /**
     * @param float|int $value
     * @return float|string
     */
    protected function normalizeExponent($value)
    {
        if (stripos($value, 'E+') !== false) {
            // Assume integer value
            $decimals = 0;
            if (is_float($value)) {
                // Update decimals if not int
                $decimals = 5;
            }

            return number_format($value, $decimals, '.', '');
        }

        return $value;
    }
}
