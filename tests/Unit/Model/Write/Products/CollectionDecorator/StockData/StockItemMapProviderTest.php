<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products\CollectionDecorator\StockData;

use Emico\CodeCept\Test\Unit;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockItemCriteriaInterfaceFactory;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Mockery;
use ReflectionMethod;
use Tweakwise\Magento2TweakwiseExport\Model\StockItem;
use Tweakwise\Magento2TweakwiseExport\Model\StockItemFactory as TweakwiseStockItemFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\StockData\StockItemMapProvider;

class StockItemMapProviderTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    public function testGetTweakwiseStockItemMapsOrderFields(): void
    {
        $stockItemFactory = Mockery::mock(TweakwiseStockItemFactory::class);
        $stockItemFactory->shouldReceive('create')->andReturnUsing(static fn (): StockItem => new StockItem());

        $provider = new StockItemMapProvider(
            Mockery::mock(StockItemRepositoryInterface::class),
            Mockery::mock(StockItemCriteriaInterfaceFactory::class),
            $stockItemFactory
        );

        $item = Mockery::mock(StockItemInterface::class);
        $item->shouldReceive('getQty')->andReturn(10.0);
        $item->shouldReceive('getIsInStock')->andReturn(true);
        $item->shouldReceive('getManageStock')->andReturn(true);
        $item->shouldReceive('getMinSaleQty')->andReturn(2.0);
        $item->shouldReceive('getEnableQtyIncrements')->andReturn(true);
        $item->shouldReceive('getQtyIncrements')->andReturn(3.0);

        $method = new ReflectionMethod(StockItemMapProvider::class, 'getTweakwiseStockItem');
        $method->setAccessible(true);
        /** @var StockItem $result */
        $result = $method->invoke($provider, $item);

        self::assertSame(2.0, $result->getOrderQty());
        self::assertTrue($result->isEnableQtyIncrements());
        self::assertSame(3.0, $result->getQtyIncrements());
    }
}
