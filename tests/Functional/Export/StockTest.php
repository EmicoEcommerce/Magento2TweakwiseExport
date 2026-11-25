<?php

declare(strict_types=1);

namespace Tweakwise\Test\Functional\Export;

use Emico\CodeCept\Models\Fixtures\ProductFixture;
use Magento\CatalogInventory\Model\Configuration as StockConfiguration;
use Tweakwise\Test\Support\Export\AbstractExportTest;

class StockTest extends AbstractExportTest
{
    public function testProductOutOfStockIsNotExported(): void
    {
        $this->tester->mockConfig(StockConfiguration::XML_PATH_SHOW_OUT_OF_STOCK, '0');

        [$inStock, $outOfStock] = $this->createProducts();

        $items = $this->exportFeed();

        $this->assertInFeed($items, $inStock);
        $this->assertNotInFeed($items, $outOfStock);
    }

    public function testProductOutOfStockIsExportedWhenShowOutOfStockIsEnabled(): void
    {
        $this->tester->mockConfig(StockConfiguration::XML_PATH_SHOW_OUT_OF_STOCK, '1');

        [$inStock, $outOfStock] = $this->createProducts();

        $items = $this->exportFeed();

        $this->assertInFeed($items, $inStock);
        $this->assertInFeed($items, $outOfStock);
    }

    /** @return ProductFixture[] */
    private function createProducts(): array
    {
        $inStock = $this->tester->createFixture(new ProductFixture('stock-test-in-stock'));
        $outOfStock = $this->tester->createFixture(new ProductFixture('stock-test-out-of-stock'));
        $this->setOutOfStock($outOfStock);

        return [$inStock, $outOfStock];
    }
}
