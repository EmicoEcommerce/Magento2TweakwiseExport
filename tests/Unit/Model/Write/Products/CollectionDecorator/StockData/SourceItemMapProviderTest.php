<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products\CollectionDecorator\StockData;

use Emico\CodeCept\Test\Unit;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolver;
use Magento\Store\Model\StoreManagerInterface;
use Mockery;
use ReflectionMethod;
use Tweakwise\Magento2TweakwiseExport\Model\DbResourceHelper;
use Tweakwise\Magento2TweakwiseExport\Model\DefaultStockProviderInterfaceFactory;
use Tweakwise\Magento2TweakwiseExport\Model\StockItem;
use Tweakwise\Magento2TweakwiseExport\Model\StockItemFactory as TweakwiseStockItemFactory;
use Tweakwise\Magento2TweakwiseExport\Model\StockResolverFactory;
use Tweakwise\Magento2TweakwiseExport\Model\StockSourceProviderFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\StockData\SourceItemMapProvider;

class SourceItemMapProviderTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    private function createProvider(): SourceItemMapProvider
    {
        $stockItemFactory = Mockery::mock(TweakwiseStockItemFactory::class);
        $stockItemFactory->shouldReceive('create')->andReturnUsing(static fn (): StockItem => new StockItem());

        return new SourceItemMapProvider(
            Mockery::mock(DbResourceHelper::class),
            Mockery::mock(StockSourceProviderFactory::class),
            $stockItemFactory,
            Mockery::mock(StoreManagerInterface::class),
            Mockery::mock(StockResolverFactory::class),
            Mockery::mock(DefaultStockProviderInterfaceFactory::class),
            Mockery::mock(DbResourceHelper::class),
            Mockery::mock(StockIndexTableNameResolver::class)
        );
    }

    public function testGetTweakwiseStockItemMapsOrderFields(): void
    {
        $provider = $this->createProvider();

        $method = new ReflectionMethod(SourceItemMapProvider::class, 'getTweakwiseStockItem');
        $method->setAccessible(true);
        /** @var StockItem $result */
        $result = $method->invoke($provider, [
            'qty' => 5,
            'is_in_stock' => 1,
            'order_qty' => 2,
            'enable_qty_increments' => 1,
            'qty_increments' => 3,
        ]);

        self::assertSame(2.0, $result->getOrderQty());
        self::assertTrue($result->isEnableQtyIncrements());
        self::assertSame(3.0, $result->getQtyIncrements());
    }

    public function testGetTweakwiseStockItemFallsBackWhenOrderFieldsMissing(): void
    {
        $provider = $this->createProvider();

        $method = new ReflectionMethod(SourceItemMapProvider::class, 'getTweakwiseStockItem');
        $method->setAccessible(true);
        /** @var StockItem $result */
        $result = $method->invoke($provider, [
            'qty' => 5,
            'is_in_stock' => 1,
        ]);

        self::assertSame(1.0, $result->getOrderQty());
        self::assertFalse($result->isEnableQtyIncrements());
        self::assertSame(1.0, $result->getQtyIncrements());
    }
}
