<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

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
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Iterator;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\Source\SourceInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Profiler;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;

class Products implements WriterInterface
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
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Config $config,
        Iterator $iterator,
        StoreManager $storeManager,
        Helper $helper,
        Logger $log,
        EavConfig $eavConfig,
        private readonly ScopeConfigInterface $scopeConfig
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
                $profileKey = 'products::' . $store->getCode();
                try {
                    Profiler::start($profileKey);
                    $this->exportStore($writer, $xml, $store);
                } finally {
                    Profiler::stop($profileKey);
                }

                $this->log->debug(sprintf('Export products for store %s', $store->getName()));
            } else {
                $this->log->debug(sprintf('Skip products for store %s (disabled)', $store->getName()));
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

        // Resolve all per-store config values once to avoid repeated lookups per product.
        $storeContext = $this->buildStoreContext($store);

        foreach ($this->iterator as $index => $data) {
            $this->writeProduct($xml, $storeContext, $data);
            // Flush every so often
            if ($index % 100 !== 0) {
                continue;
            }

            $writer->flush();
        }

        // Flush any remaining products
        $writer->flush();
    }

    /**
     * Resolve all store-scoped configuration values used during product writing into a single array.
     * This avoids redundant config, scope-config, and store-manager lookups on every product.
     *
     * @param Store $store
     * @return array{storeId: int, isGroupedExport: bool, brandAttribute: string, mediaBaseUrl: string, baseUrl: string, urlSuffix: string}
     */
    protected function buildStoreContext(Store $store): array
    {
        $storeId = (int) $store->getId();
        return [
            'storeId'         => $storeId,
            'isGroupedExport' => $this->config->isGroupedExport($store),
            'brandAttribute'  => $this->config->getBrandAttribute($store),
            'mediaBaseUrl'    => rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/catalog/product',
            'baseUrl'         => rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_WEB), '/'),
            'urlSuffix'       => (string) $this->scopeConfig->getValue(
                'catalog/seo/product_url_suffix',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
        ];
    }

    /**
     * @param XMLWriter $xml
     * @param array $storeContext
     * @param array $data
     */
    protected function writeProduct(XMLWriter $xml, array $storeContext, array $data): void
    {
        $storeId = $storeContext['storeId'];
        $xml->startElement('item');

        // Write product base data
        $tweakwiseId = $this->helper->getTweakwiseId($storeId, $data['entity_id'], $storeContext['isGroupedExport'] ? $data['groupcode'] : null);
        $xml->writeElement('id', $tweakwiseId);
        $xml->writeElement('name', $this->scalarValue($data['name']));
        $xml->writeElement('price', $this->scalarValue((float)$data['price']));
        $xml->writeElement('stock', $this->scalarValue($data['stock']));

        if ($storeContext['isGroupedExport']) {
            $xml->writeElement('groupcode', $this->scalarValue($data['groupcode']));
        }

        if (!empty($data['brand'])) {
            $brandValues = $this->normalizeAttributeValue($storeId, $storeContext['brandAttribute'], $data['brand']);
            $brandValues = array_filter($brandValues, static fn($v) => $v !== null && $v !== '');
            if (!empty($brandValues)) {
                $xml->writeElement('brand', $this->scalarValue(reset($brandValues)));
            }
        }

        $imageUrl = $this->buildImageUrl($storeContext['mediaBaseUrl'], $data['image'] ?? null);
        if ($imageUrl !== null) {
            $xml->writeElement('image', $imageUrl);
        }

        $productUrl = $this->buildProductUrl($storeContext['baseUrl'], $storeContext['urlSuffix'], $data['url_key'] ?? null);
        if ($productUrl !== null) {
            $xml->writeElement('url', $productUrl);
        }

        // Write product categories
        $xml->startElement('categories');
        foreach ($data['categories'] as $categoryId) {
            $categoryTweakwiseId = $this->helper->getTweakwiseId($storeId, $categoryId);
            // @phpstan-ignore-next-line
            if ($xml->hasCategoryExport($categoryTweakwiseId)) {
                $xml->writeElement('categoryid', $categoryTweakwiseId);
            } else {
                $this->log->debug(
                    sprintf('Skip product (%s) category (%s) relation', $tweakwiseId, $categoryTweakwiseId)
                );
            }
        }

        $xml->endElement(); // categories

        // Write product attributes
        $xml->startElement('attributes');
        foreach ($data['attributes'] as $attributeKeyValue) {
            $this->writeAttribute($xml, $storeId, $attributeKeyValue['attribute'], $attributeKeyValue['value']);
        }

        $xml->endElement(); // attributes

        $xml->endElement(); // </item>

        $this->log->debug(sprintf('Export product [%s] %s', $tweakwiseId, $data['name']));
    }

    /**
     * Build the full product URL from pre-resolved base URL and URL suffix.
     *
     * @param string $baseUrl
     * @param string $urlSuffix
     * @param string|null $urlKey
     * @return string|null
     */
    protected function buildProductUrl(string $baseUrl, string $urlSuffix, ?string $urlKey): ?string
    {
        if (empty($urlKey)) {
            return null;
        }

        return $baseUrl . '/' . $urlKey . $urlSuffix;
    }

    /**
     * Build the full image URL from a pre-resolved media base URL and relative image path.
     *
     * @param string $mediaBaseUrl
     * @param string|null $imagePath
     * @return string|null
     */
    protected function buildImageUrl(string $mediaBaseUrl, ?string $imagePath): ?string
    {
        if (empty($imagePath) || $imagePath === 'no_selection') {
            return null;
        }

        return $mediaBaseUrl . $imagePath;
    }

    /**
     * @param XMLWriter $xml
     * @param int $storeId
     * @param string $name
     * @param string|string[]|int|int[]|float|float[] $attributeValue
     */
    public function writeAttribute(
        XMLWriter $xml,
        $storeId,
        $name,
        $attributeValue
    ): void {
        $values = $this->normalizeAttributeValue($storeId, $name, $attributeValue);
        $values = array_unique($values);

        foreach ($values as $value) {
            if (empty($value) && $value !== '0') {
                continue;
            }

            $xml->startElement('attribute');
            $xml->writeAttribute('datatype', is_numeric($value) ? 'numeric' : 'text');
            $xml->writeElement('name', $name);
            $xml->writeElement('value', $value);
            $xml->endElement(); // </attribute>
        }
    }

    /**
     * @param int $storeId
     * @param AbstractAttribute $attribute
     * @return string[]
     */
    protected function getAttributeOptionMap($storeId, AbstractAttribute $attribute): array
    {
        $attributeKey = $storeId . '-' . $attribute->getId();
        if (!isset($this->attributeOptionMap[$attributeKey])) {
            $map = [];

            // Set store id to trick in fetching correct options
            $attribute->setData('store_id', $storeId);

            foreach ($attribute->getSource()->getAllOptions() as $option) {
                $map[$option['value']] = (string)$option['label'];
            }

            $this->attributeOptionMap[$attributeKey] = $map;
        }

        return $this->attributeOptionMap[$attributeKey];
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
            // @phpstan-ignore-next-line
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
        // @phpstan-ignore-next-line
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

    /**
     * @param mixed $data
     * @return array
     */
    protected function ensureArray($data): array
    {
        return is_array($data) ? $data : [$data];
    }

    /**
     * @param string[] $data
     * @param string $delimiter
     * @return string[]
     */
    protected function explodeValues(array $data, string $delimiter = ','): array
    {
        $result = [];
        foreach ($data as $value) {
            // @phpstan-ignore-next-line
            $result[] = explode($delimiter, $value) ? explode($delimiter, $value) : [];
        }

        return !empty($result) ? array_merge([], ...$result) : [];
    }

    /**
     * Convert attribute value to array of scalar values.
     *
     * @param int $storeId
     * @param string $attributeCode
     * @param mixed $value
     * @return array
     */
    protected function normalizeAttributeValue(int $storeId, string $attributeCode, $value): array
    {
        $values = $this->ensureArray($value);
        $values = array_map(
            function ($value) {
                return $this->scalarValue($value);
            },
            $values
        );

        try {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);
        } catch (LocalizedException $e) {
            $this->log->error($e->getMessage());
            return $values;
        }

        // Attribute does not exists so just return value
        // @phpstan-ignore-next-line
        if (!$attribute || !$attribute->getId()) {
            return $values;
        }

        // Apparently Magento adds a default source model to the attribute even if it does not use a source
        if (!$attribute->usesSource()) {
            return $values;
        }

        // Explode values if source is used (multi select)
        // @phpstan-ignore-next-line
        $values = $this->explodeValues($values);
        try {
            $attributeSource = $attribute->getSource();
        } catch (LocalizedException $e) {
            $this->log->error($e->getMessage());
            return $values;
        }

        // @phpstan-ignore-next-line
        if (!$attributeSource instanceof SourceInterface) {
            return $values;
        }

        $result = [];
        /** @var string $attributeValue */
        foreach ($values as $attributeValue) {
            $map = $this->getAttributeOptionMap($storeId, $attribute);
            $result[] = $map[$attributeValue] ?? null;
        }

        return $result;
    }
}
