<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products\CollectionDecorator;

use Emico\CodeCept\Test\Unit;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\Manager;
use Mockery;
use ReflectionMethod;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\StockItem;
use Tweakwise\Magento2TweakwiseExport\Model\StockItemFactory as TweakwiseStockItemFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\StockData;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntity;

class StockDataTest extends Unit
{
    private array $addAttributeCalls = [];

    public function _after(): void
    {
        Mockery::close();
    }

    private function createStockData(): StockData
    {
        return new StockData(
            Mockery::mock(ProductMetadataInterface::class),
            Mockery::mock(TweakwiseStockItemFactory::class),
            Mockery::mock(Config::class),
            Mockery::mock(Manager::class),
            []
        );
    }

    private function createEntity(?StockItem $stockItem): ExportEntity
    {
        $this->addAttributeCalls = [];

        $entity = Mockery::mock(ExportEntity::class);
        $entity->shouldReceive('getStockItem')->andReturn($stockItem);
        $entity->shouldReceive('addAttribute')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $attribute, $value): void {
                $this->addAttributeCalls[$attribute] = $value;
            });

        return $entity;
    }

    public function testAddOrderQuantityUsesStockItemOrderQty(): void
    {
        $stockItem = new StockItem();
        $stockItem->setOrderQty(2.0);
        $entity = $this->createEntity($stockItem);

        $method = new ReflectionMethod(StockData::class, 'addOrderQuantity');
        $method->setAccessible(true);
        $method->invoke($this->createStockData(), $entity);

        self::assertSame(2.0, $this->addAttributeCalls['order_quantity']);
    }

    public function testAddOrderQuantityFallsBackToMagentoDefaultWithoutStockItem(): void
    {
        $entity = $this->createEntity(null);

        $method = new ReflectionMethod(StockData::class, 'addOrderQuantity');
        $method->setAccessible(true);
        $method->invoke($this->createStockData(), $entity);

        self::assertSame(1, $this->addAttributeCalls['order_quantity']);
    }

    public function testAddOrderIncrementsEnabledIsWrittenAsInt(): void
    {
        $stockItem = new StockItem();
        $stockItem->setEnableQtyIncrements(true);
        $entity = $this->createEntity($stockItem);

        $method = new ReflectionMethod(StockData::class, 'addOrderIncrementsEnabled');
        $method->setAccessible(true);
        $method->invoke($this->createStockData(), $entity);

        self::assertSame(1, $this->addAttributeCalls['order_increments_enabled']);
    }

    public function testAddOrderIncrementsEnabledIsZeroWhenDisabled(): void
    {
        $stockItem = new StockItem();
        $stockItem->setEnableQtyIncrements(false);
        $entity = $this->createEntity($stockItem);

        $method = new ReflectionMethod(StockData::class, 'addOrderIncrementsEnabled');
        $method->setAccessible(true);
        $method->invoke($this->createStockData(), $entity);

        self::assertSame(0, $this->addAttributeCalls['order_increments_enabled']);
    }

    public function testAddOrderIncrementUsesStockItemQtyIncrements(): void
    {
        $stockItem = new StockItem();
        $stockItem->setQtyIncrements(3.0);
        $entity = $this->createEntity($stockItem);

        $method = new ReflectionMethod(StockData::class, 'addOrderIncrement');
        $method->setAccessible(true);
        $method->invoke($this->createStockData(), $entity);

        self::assertSame(3.0, $this->addAttributeCalls['order_increment']);
    }
}
