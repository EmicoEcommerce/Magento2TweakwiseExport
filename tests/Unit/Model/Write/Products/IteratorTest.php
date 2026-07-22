<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products;

use Emico\CodeCept\Test\Unit;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Event\Manager;
use Magento\Framework\Model\ResourceModel\Db\Context as DbContext;
use Magento\Store\Model\Store;
use Mockery;
use Tweakwise\Magento2TweakwiseExport\Model\Config as TweakwiseConfig;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Iterator;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\IteratorInitializer;

class IteratorTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    public function testSetStoreSelectsConfiguredAttributesAndRemovesPreviousOnes(): void
    {
        $eavConfig = Mockery::mock(EavConfig::class);
        $manufacturer = Mockery::mock(AbstractAttribute::class);
        $manufacturer->shouldReceive('getId')->andReturn(10);

        $image = Mockery::mock(AbstractAttribute::class);
        $image->shouldReceive('getId')->andReturn(20);

        $brandAttr = Mockery::mock(AbstractAttribute::class);
        $brandAttr->shouldReceive('getId')->andReturn(30);

        $mainImage = Mockery::mock(AbstractAttribute::class);
        $mainImage->shouldReceive('getId')->andReturn(40);

        $attributes = [
            'manufacturer' => $manufacturer,
            'image' => $image,
            'brand_attr' => $brandAttr,
            'main_image' => $mainImage,
        ];
        $eavConfig->shouldReceive('getAttribute')
            ->with(Product::ENTITY, Mockery::type('string'))
            ->zeroOrMoreTimes()
            ->andReturnUsing(static fn(string $entityType, string $attributeCode): AbstractAttribute => $attributes[$attributeCode]);

        $config = Mockery::mock(TweakwiseConfig::class);
        $config->shouldReceive('getBatchSizeProducts')->andReturn(100);
        $config->shouldReceive('getBrandAttribute')->andReturn('manufacturer', 'brand_attr', '');
        $config->shouldReceive('getImageAttribute')->andReturn('image', 'main_image', '');

        $iteratorInitializer = Mockery::mock(IteratorInitializer::class);
        $iteratorInitializer->shouldReceive('initializeAttributes')->once();

        $iterator = new Iterator(
            Mockery::mock(Helper::class),
            $eavConfig,
            Mockery::mock(DbContext::class),
            Mockery::mock(Manager::class),
            Mockery::mock(ExportEntityFactory::class),
            Mockery::mock(CollectionFactory::class),
            $iteratorInitializer,
            [],
            $config
        );

        $storeOne = Mockery::mock(Store::class);
        $storeTwo = Mockery::mock(Store::class);
        $storeThree = Mockery::mock(Store::class);

        $iterator->setStore($storeOne);
        self::assertSame(['brand' => 'manufacturer', 'image' => 'image'], $this->getActiveStoreAttributes($iterator));
        self::assertArrayHasKey('manufacturer', $this->getAttributesByCode($iterator));
        self::assertArrayHasKey('image', $this->getAttributesByCode($iterator));

        $iterator->setStore($storeTwo);
        self::assertSame(['brand' => 'brand_attr', 'image' => 'main_image'], $this->getActiveStoreAttributes($iterator));
        self::assertArrayNotHasKey('manufacturer', $this->getAttributesByCode($iterator));
        self::assertArrayNotHasKey('image', $this->getAttributesByCode($iterator));
        self::assertArrayHasKey('brand_attr', $this->getAttributesByCode($iterator));
        self::assertArrayHasKey('main_image', $this->getAttributesByCode($iterator));

        $iterator->setStore($storeThree);
        self::assertSame(['brand' => '', 'image' => ''], $this->getActiveStoreAttributes($iterator));
        self::assertArrayNotHasKey('brand_attr', $this->getAttributesByCode($iterator));
        self::assertArrayNotHasKey('main_image', $this->getAttributesByCode($iterator));
    }

    /**
     * @param Iterator $iterator
     * @return array{brand: string, image: string}
     */
    private function getActiveStoreAttributes(Iterator $iterator): array
    {
        $reflection = new \ReflectionClass($iterator);
        $property = $reflection->getProperty('activeStoreAttributes');
        $property->setAccessible(true);
        return $property->getValue($iterator);
    }

    /**
     * @param Iterator $iterator
     * @return array<string, AbstractAttribute>
     */
    private function getAttributesByCode(Iterator $iterator): array
    {
        $reflection = new \ReflectionClass($iterator);
        $property = $reflection->getParentClass()->getProperty('attributesByCode');
        $property->setAccessible(true);
        return $property->getValue($iterator);
    }
}
