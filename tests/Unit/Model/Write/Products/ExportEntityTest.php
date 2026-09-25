<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products;

use Emico\CodeCept\Test\Unit;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mockery;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntity;

class ExportEntityTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    public function testGetAttributesIncludesMagentoStoreId(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getId')->andReturn(2);

        $config = Mockery::mock(Config::class);
        $config->shouldReceive('getDateAttributes')->andReturn(['created_at', 'updated_at']);

        $entity = new ExportEntity(
            $store,
            Mockery::mock(StoreManagerInterface::class),
            Mockery::mock(StockConfigurationInterface::class),
            Mockery::mock(Visibility::class),
            $config,
            Mockery::mock(Helper::class)
        );

        $magentoStoreIdAttributes = array_values(array_filter(
            $entity->getAttributes(),
            static fn (array $attribute): bool => $attribute['attribute'] === 'magento_store_id'
        ));

        self::assertCount(1, $magentoStoreIdAttributes);
        self::assertSame(2, $magentoStoreIdAttributes[0]['value']);
    }
}
