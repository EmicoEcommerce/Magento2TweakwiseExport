<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model;

use Emico\CodeCept\Test\Unit;
use Tweakwise\Magento2TweakwiseExport\Model\StockItem;

class StockItemTest extends Unit
{
    public function testDefaults(): void
    {
        $stockItem = new StockItem();

        self::assertSame(1.0, $stockItem->getOrderQty());
        self::assertFalse($stockItem->isEnableQtyIncrements());
        self::assertSame(1.0, $stockItem->getQtyIncrements());
    }

    public function testGettersReturnSetValues(): void
    {
        $stockItem = new StockItem();

        $stockItem->setOrderQty(2.0);
        $stockItem->setEnableQtyIncrements(true);
        $stockItem->setQtyIncrements(3.0);

        self::assertSame(2.0, $stockItem->getOrderQty());
        self::assertTrue($stockItem->isEnableQtyIncrements());
        self::assertSame(3.0, $stockItem->getQtyIncrements());
    }
}
