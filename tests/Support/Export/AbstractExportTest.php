<?php

declare(strict_types=1);

namespace Tweakwise\Test\Support\Export;

use Emico\CodeCept\Models\Fixtures\ProductFixture;
use Emico\CodeCept\Test\Unit;
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
    protected function assertInFeed(array $items, ProductFixture $product): void
    {
        $this->assertArrayHasKey(
            $this->feedId($product),
            $items,
            sprintf('Product "%s" is not in the feed', $product->getSku())
        );
    }

    /** @param SimpleXMLElement[] $items */
    protected function assertNotInFeed(array $items, ProductFixture $product): void
    {
        $this->assertArrayNotHasKey(
            $this->feedId($product),
            $items,
            sprintf('Product "%s" should not be in the feed', $product->getSku())
        );
    }

    private function feedId(ProductFixture $product): string
    {
        $objectManager = $this->tester->getObjectManager();
        $storeId = (int)$objectManager->get(StoreManagerInterface::class)->getStore('default')->getId();

        return $objectManager->get(Helper::class)->getTweakwiseId($storeId, (int)$product->getId());
    }
}
