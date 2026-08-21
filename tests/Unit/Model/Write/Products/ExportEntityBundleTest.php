<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products;

use Emico\CodeCept\Test\Unit;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mockery;
use Tweakwise\Magento2TweakwiseExport\Model\ChildOptions;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Magento2TweakwiseExport\Model\StockItem;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityBundle;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityChild;

class ExportEntityBundleTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    public function testCombinedStockItemKeepsParentOrderFields(): void
    {
        $store = Mockery::mock(Store::class);
        $storeManager = Mockery::mock(StoreManagerInterface::class);
        $storeManager->shouldReceive('isSingleStoreMode')->andReturn(true);
        $stockConfiguration = Mockery::mock(StockConfigurationInterface::class);
        $visibility = Mockery::mock(Visibility::class);
        $config = Mockery::mock(Config::class);
        $config->shouldReceive('getDateAttributes')->andReturn([]);
        $helper = Mockery::mock(Helper::class);

        $parent = new ExportEntityBundle(
            $store,
            $storeManager,
            $stockConfiguration,
            $visibility,
            $config,
            $helper
        );
        $parent->setStatus(Status::STATUS_ENABLED);

        $parentStockItem = new StockItem();
        $parentStockItem->setQty(0);
        $parentStockItem->setIsInStock(0);
        $parentStockItem->setOrderQty(5.0);
        $parentStockItem->setEnableQtyIncrements(true);
        $parentStockItem->setQtyIncrements(2.0);
        $parent->setStockItem($parentStockItem);

        $child = new ExportEntityChild(
            $config,
            $store,
            $storeManager,
            $stockConfiguration,
            $visibility,
            $helper
        );
        $child->setFromArray(['entity_id' => 1]);
        $child->setStatus(Status::STATUS_ENABLED);
        $child->setChildOptions(new ChildOptions(1, true));

        $childStockItem = new StockItem();
        $childStockItem->setQty(8);
        $childStockItem->setIsInStock(1);
        // Children's own order fields are irrelevant - the parent's own settings must win.
        $childStockItem->setOrderQty(999.0);
        $child->setStockItem($childStockItem);

        $parent->addChild($child);

        $combined = $parent->getStockItem();

        self::assertSame(8, $combined->getQty());
        self::assertSame(1, $combined->getIsInStock());
        self::assertSame(5.0, $combined->getOrderQty());
        self::assertTrue($combined->isEnableQtyIncrements());
        self::assertSame(2.0, $combined->getQtyIncrements());
    }
}
