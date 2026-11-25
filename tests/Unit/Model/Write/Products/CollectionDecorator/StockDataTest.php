<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products\CollectionDecorator;

use Emico\CodeCept\Test\Unit;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\Manager;
use Magento\Store\Model\Store;
use Mockery;
use ReflectionMethod;
use stdClass;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\StockItem;
use Tweakwise\Magento2TweakwiseExport\Model\StockItemFactory as TweakwiseStockItemFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\StockData;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityChild;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityConfigurable;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntity;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Stock\Collection as StockCollection;

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

    public function testGroupedExportCopiesParentStockPercentageToChildren(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getId')->andReturn(1);

        $config = Mockery::mock(Config::class);
        $config->shouldReceive('isGroupedExport')->with($store)->once()->andReturnTrue();

        $parentStockItem = new StockItem();
        $parentStockItem->setIsInStock(1);
        $childStockItem = new StockItem();
        $childStockItem->setIsInStock(1);
        $outOfStockChildStockItem = new StockItem();

        $outOfStockChild = Mockery::mock(ExportEntityChild::class);
        $outOfStockChild->shouldReceive('getStockItem')->andReturn($outOfStockChildStockItem);
        $outOfStockChildAttributes = new stdClass();
        $outOfStockChild->shouldReceive('addAttribute')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $attribute, $value) use ($outOfStockChildAttributes): void {
                $outOfStockChildAttributes->$attribute = $value;
            });

        $child = Mockery::mock(ExportEntityChild::class);
        $child->shouldReceive('getId')->andReturn(2);
        $child->shouldReceive('getStockItem')->andReturn($childStockItem);
        $childAttributes = new stdClass();
        $child->shouldReceive('addAttribute')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $attribute, $value) use ($childAttributes): void {
                $childAttributes->$attribute = $value;
            });

        $parent = Mockery::mock(ExportEntityConfigurable::class);
        $parent->shouldReceive('getId')->andReturn(1);
        $parent->shouldReceive('getStockItem')->andReturn($parentStockItem);
        $parent->shouldReceive('getEnabledChildren')->andReturn([$child, $outOfStockChild]);
        $parent->shouldReceive('getAllChildren')->andReturn([$child, $outOfStockChild]);
        $parentAttributes = new stdClass();
        $parent->shouldReceive('getAttribute')
            ->with('stock_percentage', false)
            ->andReturnUsing(function () use ($parentAttributes) {
                return $parentAttributes->stock_percentage;
            });
        $parent->shouldReceive('addAttribute')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $attribute, $value) use ($parentAttributes): void {
                $parentAttributes->$attribute = $value;
            });

        $collection = new Collection($store);
        $collection->add($parent);
        $collection->add($child);

        $stockData = new class (
            Mockery::mock(ProductMetadataInterface::class),
            Mockery::mock(TweakwiseStockItemFactory::class),
            $config,
            Mockery::mock(Manager::class),
            []
        ) extends StockData {
            protected function addStockItems(int $storeId, Collection|StockCollection $collection): void
            {
            }
        };
        $stockData->decorate($collection);

        self::assertSame(50.0, $parentAttributes->stock_percentage);
        self::assertSame(50.0, $childAttributes->parent_stock_percentage);
        self::assertSame(50.0, $outOfStockChildAttributes->parent_stock_percentage);
    }
}
