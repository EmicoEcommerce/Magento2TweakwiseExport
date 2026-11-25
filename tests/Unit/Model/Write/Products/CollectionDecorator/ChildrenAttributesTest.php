<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products\CollectionDecorator;

use Emico\CodeCept\Test\Unit;
use Magento\Store\Model\Store;
use Mockery;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator\ChildrenAttributes;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityChild;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityConfigurable;

class ChildrenAttributesTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    public function testSkipChildAttributeIsResolvedForTheExportedStore(): void
    {
        $store = Mockery::mock(Store::class);

        $config = Mockery::mock(Config::class);
        $config->shouldReceive('getSkipChildByCompositeTypes')->with($store)->andReturn([]);
        $config->shouldReceive('getDateAttributes')->andReturn([]);
        $config->shouldReceive('getSkipChildAttribute')->with('color', $store)->once()->andReturn(true);
        $config->shouldReceive('getSkipChildAttribute')->with('size', $store)->once()->andReturn(false);

        $child = Mockery::mock(ExportEntityChild::class);
        $child->shouldReceive('getAttributes')->andReturn([
            ['attribute' => 'color', 'value' => 'red'],
            ['attribute' => 'size', 'value' => 'XL'],
        ]);

        $parent = Mockery::mock(ExportEntityConfigurable::class);
        $parent->shouldReceive('getTypeId')->andReturn('configurable');
        $parent->shouldReceive('getStore')->andReturn($store);
        $parent->shouldReceive('getExportChildren')->andReturn([$child]);
        $parent->shouldReceive('addAttribute')->with('size', 'XL')->once();

        $collection = Mockery::mock(Collection::class);
        $collection->shouldReceive('getIterator')->andReturn(new \ArrayIterator([$parent]));

        (new ChildrenAttributes($config))->decorate($collection);
    }
}
