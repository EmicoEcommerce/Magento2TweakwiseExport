<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write\Products;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Event\Manager;
use Magento\Framework\Model\ResourceModel\Db\Context as DbContext;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Magento2TweakwiseExport\Model\StockItem;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityChild;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityConfigurable;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Iterator;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\IteratorInitializer;

class ExportEntityChildTest extends TestCase
{
    public function testChildStockSelectionForBothExportModes(): void
    {
        foreach ([false, true] as $grouped) {
            foreach ([false, true] as $exportOutOfStockChildren) {
                foreach ([false, true] as $showOutOfStock) {
                    foreach ([false, true] as $inStock) {
                        $store = $this->createStub(Store::class);
                        $store->method('getId')->willReturn(1);
                        $storeManager = $this->createStub(StoreManagerInterface::class);
                        $storeManager->method('isSingleStoreMode')->willReturn(true);
                        $stockConfiguration = $this->createMock(StockConfigurationInterface::class);
                        $stockConfiguration->expects($grouped ? self::once() : self::never())
                            ->method('isShowOutOfStock')->with(1)->willReturn($showOutOfStock);
                        $config = $this->createMock(Config::class);
                        $config->method('getDateAttributes')->willReturn([]);
                        $config->expects(self::once())->method('isGroupedExport')->with($store)->willReturn($grouped);
                        $config->expects($grouped ? self::never() : self::once())
                            ->method('isOutOfStockChildren')->willReturn($exportOutOfStockChildren);

                        $child = new ExportEntityChild(
                            $config,
                            $store,
                            $storeManager,
                            $stockConfiguration,
                            $this->createStub(Visibility::class),
                            $this->createStub(Helper::class)
                        );
                        $child->setStatus(Status::STATUS_ENABLED);
                        $stockItem = new StockItem();
                        $stockItem->setIsInStock((int) $inStock);
                        $child->setStockItem($stockItem);

                        $expected = $inStock || ($grouped ? $showOutOfStock : $exportOutOfStockChildren);
                        self::assertSame(
                            $expected,
                            $child->shouldExport(),
                            sprintf(
                                'grouped=%d, exportOutOfStockChildren=%d, showOutOfStock=%d, inStock=%d',
                                $grouped,
                                $exportOutOfStockChildren,
                                $showOutOfStock,
                                $inStock
                            )
                        );
                    }
                }
            }
        }
    }

    public function testGroupedExportUsesMagentoStockSettingForConfigurableVariants(): void
    {
        foreach ([false, true] as $showOutOfStock) {
            foreach ([false, true] as $exportOutOfStockChildren) {
                $store = $this->createStub(Store::class);
                $store->method('getId')->willReturn(1);
                $storeManager = $this->createStub(StoreManagerInterface::class);
                $storeManager->method('isSingleStoreMode')->willReturn(true);
                $stockConfiguration = $this->createStub(StockConfigurationInterface::class);
                $stockConfiguration->method('isShowOutOfStock')->willReturn($showOutOfStock);
                $visibility = $this->createStub(Visibility::class);
                $visibility->method('getVisibleInSiteIds')->willReturn([Visibility::VISIBILITY_BOTH]);
                $config = $this->createStub(Config::class);
                $config->method('getDateAttributes')->willReturn([]);
                $config->method('isGroupedExport')->willReturn(true);
                $config->method('isOutOfStockChildren')->willReturn($exportOutOfStockChildren);
                $config->method('getBatchSizeProducts')->willReturn(100);
                $config->method('getBrandAttribute')->willReturn('');
                $config->method('getImageAttribute')->willReturn('');
                $helper = $this->createStub(Helper::class);
                $helper->method('getTweakwiseId')
                    ->willReturnCallback(static fn(int $storeId, int $entityId): string => (string) $entityId);

                $collection = new Collection($store);
                foreach ([100 => [1, 0], 200 => [0, 0]] as $parentId => $stockStatuses) {
                    $parent = new ExportEntityConfigurable(
                        $store,
                        $storeManager,
                        $stockConfiguration,
                        $visibility,
                        $config,
                        $helper,
                        [
                            'entity_id' => $parentId,
                            'type_id' => 'configurable',
                            'name' => 'Parent',
                            'status' => Status::STATUS_ENABLED,
                            'visibility' => Visibility::VISIBILITY_BOTH,
                        ]
                    );
                    $parent->setStockItem(new StockItem());
                    $collection->add($parent);

                    foreach ($stockStatuses as $index => $stockStatus) {
                        $child = new ExportEntityChild(
                            $config,
                            $store,
                            $storeManager,
                            $stockConfiguration,
                            $visibility,
                            $helper,
                            ['entity_id' => $parentId + $index + 1, 'name' => 'Variant']
                        );
                        $child->setStatus(Status::STATUS_ENABLED);
                        $child->setGroupCode($parentId);
                        $stockItem = new StockItem();
                        $stockItem->setIsInStock($stockStatus);
                        $child->setStockItem($stockItem);
                        $parent->addChild($child);
                        $collection->add($child);
                    }
                }

                $iterator = new Iterator(
                    $helper,
                    $this->createStub(EavConfig::class),
                    $this->createStub(DbContext::class),
                    $this->createStub(Manager::class),
                    $this->createStub(ExportEntityFactory::class),
                    $this->createStub(CollectionFactory::class),
                    $this->createStub(IteratorInitializer::class),
                    [],
                    $config
                );
                $iterator->setStore($store);
                $processBatch = new ReflectionMethod(Iterator::class, 'processBatch');
                $items = iterator_to_array($processBatch->invoke($iterator, $collection), false);
                $groupsByItem = array_column($items, 'groupcode', 'entity_id');

                self::assertSame(
                    $showOutOfStock ? [101 => 100, 102 => 100, 201 => 200, 202 => 200] : [101 => 100],
                    $groupsByItem,
                    sprintf(
                        'showOutOfStock=%d, exportOutOfStockChildren=%d',
                        $showOutOfStock,
                        $exportOutOfStockChildren
                    )
                );
            }
        }
    }
}
