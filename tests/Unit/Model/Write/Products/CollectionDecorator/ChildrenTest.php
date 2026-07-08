<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products\CollectionDecorator;

use Emico\CodeCept\Test\Unit;
use Mockery;
use Magento\Catalog\Model\Product\Type;
use Magento\Store\Model\Store;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\DbResourceHelper;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Magento2TweakwiseExport\Model\Write\EavIteratorFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\Children;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\WebsiteLink;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityChild;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityConfigurable;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\IteratorInitializer;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionFactory;

class ChildrenTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    public function testGroupedExportAddsParentMainImageAttributeToVariant(): void
    {
        $store = Mockery::mock(Store::class);

        $config = Mockery::mock(Config::class);
        $config->shouldReceive('getBatchSizeProductsChildren')->andReturn(100);
        $config->shouldReceive('isGroupedExport')
            ->with($store)
            ->once()
            ->andReturn(true);

        $childrenDecorator = new Children(
            Mockery::mock(Type::class),
            Mockery::mock(EavIteratorFactory::class),
            Mockery::mock(IteratorInitializer::class),
            Mockery::mock(ExportEntityFactory::class),
            Mockery::mock(CollectionFactory::class),
            Mockery::mock(Helper::class),
            Mockery::mock(DbResourceHelper::class),
            $config,
            Mockery::mock(WebsiteLink::class)
        );

        $parent = Mockery::mock(ExportEntityConfigurable::class);
        $parent->shouldReceive('getAttribute')->with('url_key', false)->andReturn('parent-url-key');
        $parent->shouldReceive('getAttribute')->with('name', false)->andReturn('Parent Name');
        $parent->shouldReceive('getAttribute')->with('visibility', false)->andReturn(4);
        $parent->shouldReceive('getAttribute')->with('image', false)->andReturn('/p/a/parent-image.jpg');

        $child = Mockery::mock(ExportEntityChild::class);
        $child->shouldReceive('setGroupCode')->with(123)->once();
        $addAttributeCalls = [];
        $child->shouldReceive('addAttribute')
            ->zeroOrMoreTimes()
            ->andReturnUsing(
                static function (string $attribute, $value) use (&$addAttributeCalls): void {
                    $addAttributeCalls[] = [$attribute, $value];
                }
            );
        $child->shouldReceive('getCategories')->andReturn([10]);

        $collection = Mockery::mock(Collection::class);
        $collection->shouldReceive('getStore')->andReturn($store);
        $collection->shouldReceive('get')->with(456)->andReturn($child);

        $method = new \ReflectionMethod(Children::class, 'enrichGroupedExportChild');
        $method->setAccessible(true);
        $method->invoke($childrenDecorator, $collection, $parent, 123, 456);

        self::assertSame(
            [
                ['parent_url_key', 'parent-url-key'],
                ['parent_name', 'Parent Name'],
                ['parent_visibility', 4],
                ['parent_main_image', '/p/a/parent-image.jpg'],
            ],
            $addAttributeCalls
        );
    }
}
