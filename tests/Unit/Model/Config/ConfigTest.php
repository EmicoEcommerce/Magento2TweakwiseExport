<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Config;

use Emico\CodeCept\Test\Unit;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Mockery;
use Tweakwise\Magento2TweakwiseExport\Model\Config;

class ConfigTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    public function testGetBrandAttributeUsesStoreScope(): void
    {
        $store = Mockery::mock(Store::class);
        $scopeConfig = Mockery::mock(ScopeConfigInterface::class);
        $scopeConfig->shouldReceive('getValue')
            ->once()
            ->with(Config::PATH_BRAND_ATTRIBUTE, ScopeInterface::SCOPE_STORE, $store)
            ->andReturn('manufacturer');

        $config = new Config(
            $scopeConfig,
            Mockery::mock(DirectoryList::class),
            Mockery::mock(DeploymentConfig::class),
            Mockery::mock(File::class)
        );

        self::assertSame('manufacturer', $config->getBrandAttribute($store));
    }

    public function testGetImageAttributeUsesStoreScope(): void
    {
        $scopeConfig = Mockery::mock(ScopeConfigInterface::class);
        $scopeConfig->shouldReceive('getValue')
            ->once()
            ->with(Config::PATH_IMAGE_ATTRIBUTE, ScopeInterface::SCOPE_STORE, 5)
            ->andReturn('small_image');

        $config = new Config(
            $scopeConfig,
            Mockery::mock(DirectoryList::class),
            Mockery::mock(DeploymentConfig::class),
            Mockery::mock(File::class)
        );

        self::assertSame('small_image', $config->getImageAttribute(5));
    }

    public function testGetSkipChildAttributeReadsListPerStore(): void
    {
        $scopeConfig = Mockery::mock(ScopeConfigInterface::class);
        $scopeConfig->shouldReceive('getValue')
            ->with(Config::PATH_EXCLUDE_CHILD_ATTRIBUTES, ScopeInterface::SCOPE_STORE, 1)
            ->once()
            ->andReturn('color');
        $scopeConfig->shouldReceive('getValue')
            ->with(Config::PATH_EXCLUDE_CHILD_ATTRIBUTES, ScopeInterface::SCOPE_STORE, 2)
            ->once()
            ->andReturn('size');

        $config = new Config(
            $scopeConfig,
            Mockery::mock(DirectoryList::class),
            Mockery::mock(DeploymentConfig::class),
            Mockery::mock(File::class)
        );

        self::assertTrue($config->getSkipChildAttribute('color', 1));
        self::assertFalse($config->getSkipChildAttribute('size', 1));
        self::assertTrue($config->getSkipChildAttribute('size', 2));
        self::assertFalse($config->getSkipChildAttribute('color', 2));
        self::assertTrue($config->getSkipChildAttribute('visibility', 2));

        $storeOne = Mockery::mock(Store::class);
        $storeOne->shouldReceive('getId')->andReturn(1);
        self::assertTrue($config->getSkipChildAttribute('color', $storeOne));
        self::assertFalse($config->getSkipChildAttribute('size', $storeOne));
        self::assertEqualsCanonicalizing(['color', 'visibility'], $config->getSkipChildAttribute(null, 1));
    }
}
