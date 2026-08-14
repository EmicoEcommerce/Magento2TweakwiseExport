<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products\CollectionDecorator;

use Emico\CodeCept\Test\Unit;
use Mockery;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\Store;
use Tweakwise\Magento2TweakwiseExport\Exception\InvalidArgumentException;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Price\Collection as PriceCollection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Price\ExportEntity as PriceExportEntity;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\DiscountPercentage;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntity;

class DiscountPercentageTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    public function testAddsDiscountPercentageForDiscountedProducts(): void
    {
        $entity = Mockery::mock(ExportEntity::class);
        $entity->shouldReceive('getId')->andReturn(1);
        $entity->shouldReceive('getTypeId')->andReturn('simple');
        $entity->shouldReceive('getRegularPrice')->andReturn(99.0);
        $entity->shouldReceive('getAttribute')->with('final_price', false)->andReturn(65.0);
        $entity->shouldReceive('addAttribute')->with('discount_percentage', 34)->once();

        $collection = $this->createCollection([$entity]);

        (new DiscountPercentage())->decorate($collection);
    }

    public function testSkipsBundleProducts(): void
    {
        $entity = Mockery::mock(ExportEntity::class);
        $entity->shouldReceive('getId')->andReturn(2);
        $entity->shouldReceive('getTypeId')->andReturn(BundleType::TYPE_CODE);
        $entity->shouldNotReceive('getRegularPrice');
        $entity->shouldNotReceive('getAttribute');
        $entity->shouldNotReceive('addAttribute');

        $collection = $this->createCollection([$entity]);

        (new DiscountPercentage())->decorate($collection);
    }

    public function testSkipsGroupedProducts(): void
    {
        $entity = Mockery::mock(ExportEntity::class);
        $entity->shouldReceive('getId')->andReturn(6);
        $entity->shouldReceive('getTypeId')->andReturn(Grouped::TYPE_CODE);
        $entity->shouldNotReceive('getRegularPrice');
        $entity->shouldNotReceive('getAttribute');
        $entity->shouldNotReceive('addAttribute');

        $collection = $this->createCollection([$entity]);

        (new DiscountPercentage())->decorate($collection);
    }

    public function testSkipsProductWithoutDiscount(): void
    {
        $entity = Mockery::mock(ExportEntity::class);
        $entity->shouldReceive('getId')->andReturn(3);
        $entity->shouldReceive('getTypeId')->andReturn('simple');
        $entity->shouldReceive('getRegularPrice')->andReturn(50.0);
        $entity->shouldReceive('getAttribute')->with('final_price', false)->andReturn(50.0);
        $entity->shouldNotReceive('addAttribute');

        $collection = $this->createCollection([$entity]);

        (new DiscountPercentage())->decorate($collection);
    }

    public function testSkipsProductWhenPriceAttributesMissing(): void
    {
        $entity = Mockery::mock(ExportEntity::class);
        $entity->shouldReceive('getId')->andReturn(4);
        $entity->shouldReceive('getTypeId')->andReturn('simple');
        $entity->shouldReceive('getRegularPrice')->andReturn(99.0);
        $entity->shouldReceive('getAttribute')
            ->with('final_price', false)
            ->andThrow(new InvalidArgumentException('Could not find attribute final_price'));
        $entity->shouldNotReceive('addAttribute');

        $collection = $this->createCollection([$entity]);

        (new DiscountPercentage())->decorate($collection);
    }

    public function testSkipsProductWhenRegularPriceIsZero(): void
    {
        $entity = Mockery::mock(ExportEntity::class);
        $entity->shouldReceive('getId')->andReturn(5);
        $entity->shouldReceive('getTypeId')->andReturn('simple');
        $entity->shouldReceive('getRegularPrice')->andReturn(0.0);
        $entity->shouldReceive('getAttribute')->with('final_price', false)->andReturn(10.0);
        $entity->shouldNotReceive('addAttribute');

        $collection = $this->createCollection([$entity]);

        (new DiscountPercentage())->decorate($collection);
    }

    public function testDecoratesPriceCollection(): void
    {
        $entity = Mockery::mock(PriceExportEntity::class);
        $entity->shouldReceive('getId')->andReturn(7);
        $entity->shouldReceive('getTypeId')->andReturn('simple');
        $entity->shouldReceive('getRegularPrice')->andReturn(100.0);
        $entity->shouldReceive('getAttribute')->with('final_price', false)->andReturn(75.0);
        $entity->shouldReceive('addAttribute')->with('discount_percentage', 25)->once();

        $collection = new PriceCollection(Mockery::mock(Store::class));
        $collection->add($entity);

        (new DiscountPercentage())->decorate($collection);
    }

    /**
     * @param ExportEntity[] $entities
     */
    private function createCollection(array $entities): Collection
    {
        $collection = new Collection(Mockery::mock(Store::class));
        foreach ($entities as $entity) {
            $collection->add($entity);
        }

        return $collection;
    }
}
