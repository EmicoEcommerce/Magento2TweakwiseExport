<?php

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use RuntimeException;

class Config
{
    /**
     * Config path constants
     */
    public const PATH_ENABLED = 'tweakwise/export/enabled';
    public const PATH_STORE_LEVEL_EXPORT_ENABLED = 'tweakwise/export/store_level_export_enabled';
    public const PATH_REAL_TIME = 'tweakwise/export/real_time';
    public const PATH_VALIDATE = 'tweakwise/export/validate';
    public const PATH_ARCHIVE = 'tweakwise/export/archive';
    public const PATH_API_IMPORT_URL = 'tweakwise/export/api_import_url';
    public const PATH_API_IMPORT_URL_STOCK = 'tweakwise/export/api_import_url_stock';
    public const PATH_OUT_OF_STOCK_CHILDREN = 'tweakwise/export/out_of_stock_children';
    public const PATH_FEED_KEY = 'tweakwise/export/feed_key';
    public const PATH_ALLOW_CACHE_FLUSH = 'tweakwise/export/allow_cache_flush';
    public const PATH_PRICE_FIELD = 'tweakwise/export/price_field';
    public const PATH_EXCLUDE_CHILD_ATTRIBUTES = 'tweakwise/export/exclude_child_attributes';
    public const BATCH_SIZE_CATEGORIES = 'tweakwise/export/batch_size_categories';
    public const BATCH_SIZE_PRODUCTS = 'tweakwise/export/batch_size_products';
    public const BATCH_SIZE_PRODUCTS_CHILDREN = 'tweakwise/export/batch_size_products_children';
    public const PATH_SKIP_CHILD_BY_COMPOSITE_TYPE = 'tweakwise/export/skip_child_by_composite_type';
    public const CALCULATE_COMPOSITE_PRICES = 'tweakwise/export/calculate_composite_prices';
    public const PATH_GROUPED_EXPORT_ENABLED = 'tweakwise/export/grouped_export_enabled';

    /**
     * Default feed filename
     */
    public const FEED_FILE_NAME = 'tweakwise%s.xml';

    /**
     * @var ScopeConfigInterface
     */
    protected $config;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var array
     */
    protected $skipAttributes;

    /**
     * @var DeploymentConfig
     */
    protected $deployConfig;

    /**
     * @var File
     */
    private File $driver;

    /**
     * Export constructor.
     *
     * @param ScopeConfigInterface $config
     * @param DirectoryList $directoryList
     * @param DeploymentConfig $deployConfig
     * @param File $driver
     */
    public function __construct(
        ScopeConfigInterface $config,
        DirectoryList $directoryList,
        DeploymentConfig $deployConfig,
        File $driver
    ) {
        $this->config = $config;
        $this->directoryList = $directoryList;
        $this->deployConfig = $deployConfig;
        $this->driver = $driver;
    }

    /**
     * @param Store|int|string|null $store
     * @return bool
     */
    public function isEnabled($store = null): bool
    {
        return (bool) $this->config->isSetFlag(self::PATH_ENABLED, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @return bool
     */
    public function isStoreLevelExportEnabled(): bool
    {
        return (bool) $this->config->isSetFlag(self::PATH_STORE_LEVEL_EXPORT_ENABLED);
    }

    /**
     * @return bool
     */
    public function isRealTime(): bool
    {
        return (bool) $this->config->isSetFlag(self::PATH_REAL_TIME);
    }

    /**
     * @return bool
     */
    public function isValidate(): bool
    {
        if (!$this->deployConfig->isAvailable()) {
            return false;
        }

        return (bool) $this->config->isSetFlag(self::PATH_VALIDATE);
    }

    /**
     * @return integer
     */
    public function getMaxArchiveFiles(): int
    {
        return (int) $this->config->getValue(self::PATH_ARCHIVE);
    }

    /**
     * @return string
     */
    public function getApiImportUrl($store = null, $type = null): string
    {
        if ($type === 'stock') {
            return (string) $this->config->getValue(
                self::PATH_API_IMPORT_URL_STOCK,
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }

        return (string) $this->config->getValue(self::PATH_API_IMPORT_URL, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param Store|int|string|null $store
     * @return bool
     */
    public function isOutOfStockChildren($store = null): bool
    {
        return (bool) $this->config->isSetFlag(self::PATH_OUT_OF_STOCK_CHILDREN, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @return string|null
     */
    public function getKey(): ?string
    {
        return $this->config->getValue(self::PATH_FEED_KEY);
    }

    /**
     * @return bool Allow cache flush or not
     */
    public function isAllowCacheFlush(): bool
    {
        return (bool) $this->config->getValue(self::PATH_ALLOW_CACHE_FLUSH);
    }

    /**
     * @param Store|int|string|null $store
     * @return string[]
     */
    public function getPriceFields($store = null): array
    {
        $data = (array) explode(
            ',',
            (string) $this->config->getValue(self::PATH_PRICE_FIELD, ScopeInterface::SCOPE_STORE, $store)
        );
        return array_filter($data);
    }

    /**
     * @param string|null $attribute
     * @param Store|int|string|null $store
     * @return bool|int[]|string[]
     */
    public function getSkipChildAttribute($attribute = null, $store = null)
    {
        if (!$this->skipAttributes) {
            $value = $this->config->getValue(self::PATH_EXCLUDE_CHILD_ATTRIBUTES, ScopeInterface::SCOPE_STORE, $store);
            $skipAttributes = explode(',', (string) $value);
            $this->skipAttributes = array_flip($skipAttributes);
        }

        if ($attribute === null) {
            return array_keys($this->skipAttributes);
        }

        return isset($this->skipAttributes[$attribute]);
    }

    /**
     * @param Store|int|string|null $store
     * @return string[]
     */
    public function getSkipChildByCompositeTypes($store = null): array
    {
        $data = explode(
            ',',
            (string) $this->config->getValue(
                self::PATH_SKIP_CHILD_BY_COMPOSITE_TYPE,
                ScopeInterface::SCOPE_STORE,
                $store
            )
        );

        return array_filter($data);
    }

    /**
     * @param StoreInterface|null $store
     * @param string|null $type
     * @return string
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function getDefaultFeedFile(?StoreInterface $store = null, ?string $type = null): string
    {
        $dir = $this->directoryList->getPath('var') . DIRECTORY_SEPARATOR . 'feeds';
        if (
            !$this->driver->isDirectory($dir) &&
            !$this->driver->createDirectory($dir) &&
            !$this->driver->isDirectory($dir)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        $storeCode = $store && $this->isStoreLevelExportEnabled() ? '-' . $store->getCode() : '';
        $filename = sprintf(self::FEED_FILE_NAME, $storeCode);
        if (!empty($type)) {
            $filename = sprintf(self::FEED_FILE_NAME, ($storeCode . '_' . $type));
        }

        return $dir . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * @param string|null $file
     * @param StoreInterface|null $store
     * @return string
     */
    public function getFeedLockFile($file = null, $store = null, $type = null): string
    {
        if (!$file) {
            $file = $this->getDefaultFeedFile($store, $type);
        }

        return $file . '.lock';
    }

    /**
     * @param string|null $file
     * @return string
     */
    public function getFeedTmpFile(?string $file = null, ?StoreInterface  $store = null): string
    {
        if (!$file) {
            $file = $this->getDefaultFeedFile($store);
        }

        return $file . '.tmp';
    }

    /**
     * @return int
     */
    public function getBatchSizeCategories(): int
    {
        return (int) $this->config->getValue(self::BATCH_SIZE_CATEGORIES);
    }

    /**
     * @return int
     */
    public function getBatchSizeProducts(): int
    {
        return (int) $this->config->getValue(self::BATCH_SIZE_PRODUCTS);
    }

    /**
     * @return int
     */
    public function getBatchSizeProductsChildren(): int
    {
        return (int) $this->config->getValue(self::BATCH_SIZE_PRODUCTS_CHILDREN);
    }

    /**
     * @return bool
     */
    public function isGroupedExport(?StoreInterface $store = null): bool
    {
        return (bool) $this->config->getValue(
            self::PATH_GROUPED_EXPORT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param Store $store
     * @return bool
     */
    public function calculateCombinedPrices(Store $store): bool
    {
        return (bool) $this->config->isSetFlag(self::CALCULATE_COMPOSITE_PRICES, ScopeInterface::SCOPE_STORE, $store);
    }
}
