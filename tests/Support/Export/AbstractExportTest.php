<?php

declare(strict_types=1);

namespace Tweakwise\Test\Support\Export;

use Emico\CodeCept\Models\Fixtures\ProductFixture;
use Emico\CodeCept\Test\Unit;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Model\StoreManagerInterface;
use SimpleXMLElement;
use Tweakwise\Magento2TweakwiseExport\Model\Export;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Test\Support\FunctionalTester;

abstract class AbstractExportTest extends Unit
{
    protected FunctionalTester $tester;

    /** @var string[] */
    private array $feedFiles = [];

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _before(): void
    {
        $this->tester->mockConfig('tweakwise/export/enabled', '1');
    }

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _after(): void
    {
        foreach ($this->feedFiles as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }
    }

    /** @return SimpleXMLElement[] */
    protected function exportFeed(): array
    {
        $objectManager = $this->tester->getObjectManager();
        $objectManager->reset();

        $feedFile = codecept_output_dir('export-' . uniqid('', true) . '.xml');
        $this->feedFiles[] = $feedFile;

        $objectManager->get(Export::class)->generateToFile($feedFile, false);

        $feed = simplexml_load_file($feedFile);
        $this->assertInstanceOf(SimpleXMLElement::class, $feed);

        $items = [];
        foreach ($feed->items->item as $item) {
            $items[(string)$item->id] = $item;
        }

        return $items;
    }

    /** @param SimpleXMLElement[] $items */
    protected function assertInFeed(array $items, ProductFixture $product, string $storeCode = 'default'): void
    {
        $this->assertArrayHasKey(
            $this->feedId($product, $storeCode),
            $items,
            sprintf('Product "%s" is not in the feed', $product->getSku())
        );
    }

    /** @param SimpleXMLElement[] $items */
    protected function assertNotInFeed(array $items, ProductFixture $product, string $storeCode = 'default'): void
    {
        $this->assertArrayNotHasKey(
            $this->feedId($product, $storeCode),
            $items,
            sprintf('Product "%s" should not be in the feed', $product->getSku())
        );
    }

    /**
     * @param SimpleXMLElement[] $items
     * @return string[]
     */
    protected function attributeValues(array $items, ProductFixture $product, string $attribute): array
    {
        $this->assertInFeed($items, $product);

        $values = [];
        foreach ($items[$this->feedId($product)]->attributes->attribute as $item) {
            if ((string)$item->name !== $attribute) {
                continue;
            }

            $values[] = (string)$item->value;
        }
        sort($values);

        return $values;
    }

    protected function setOutOfStock(ProductFixture $product): void
    {
        $this->tester->setStock($product->getSku(), 0, 0);

        $indexerRegistry = $this->tester->getObjectManager()->get(IndexerRegistry::class);
        foreach (['cataloginventory_stock', 'catalog_product_price'] as $indexerId) {
            $indexerRegistry->get($indexerId)->reindexRow((int)$product->getId());
        }
    }

    private function feedId(ProductFixture $product, string $storeCode = 'default'): string
    {
        $objectManager = $this->tester->getObjectManager();
        $storeId = (int)$objectManager->get(StoreManagerInterface::class)->getStore($storeCode)->getId();

        return $objectManager->get(Helper::class)->getTweakwiseId($storeId, (int)$product->getId());
    }
}
