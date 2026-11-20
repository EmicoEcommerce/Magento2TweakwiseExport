<?php

declare(strict_types=1);

namespace Tweakwise\Test\Functional\Export;

use Emico\CodeCept\Test\Unit;
use ReflectionMethod;
use ReflectionProperty;
use SimpleXMLElement;
use Tweakwise\Magento2TweakwiseExport\Model\Export;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products;
use Tweakwise\Test\Support\FunctionalTester;

class GroupedExportBatchSizeTest extends Unit
{
    private const REFERENCE_BATCH_SIZE = 5000;

    private const SMALL_BATCH_SIZE = 1;

    protected FunctionalTester $tester;

    /** @var string[] */
    private array $feedFiles = [];

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _before(): void
    {
        $this->tester->mockConfig('tweakwise/export/enabled', '1');
        $this->tester->mockConfig('tweakwise/export/grouped_export_enabled', '1');
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

    public function testGroupedExportDoesNotDependOnTheBatchSize(): void
    {
        $reference = $this->exportItems(self::REFERENCE_BATCH_SIZE);
        $this->assertGroupedFeed($reference);

        $small = $this->exportItems(self::SMALL_BATCH_SIZE);

        $this->assertSame(
            array_keys($reference),
            array_keys($small),
            'A batch size of 1 exports other items than a batch size of 5000'
        );

        foreach ($reference as $id => $item) {
            $this->assertSame($item, $small[$id], sprintf('Item "%s" differs with a batch size of 1', $id));
        }
    }

    /** @return string[][][] */
    private function exportItems(int $batchSize): array
    {
        $this->tester->mockConfig('tweakwise/export/batch_size_products', (string)$batchSize);
        $this->tester->mockConfig('tweakwise/export/batch_size_products_children', (string)$batchSize);

        $export = $this->tester->getObjectManager()->create(Export::class);
        $this->assertSame($batchSize, $this->productBatchSize($export), 'The batch size was not applied');

        $feedFile = codecept_output_dir('grouped-batch-' . $batchSize . '-' . uniqid('', true) . '.xml');
        $this->feedFiles[] = $feedFile;

        $export->generateToFile($feedFile, false);

        return $this->readItems($feedFile);
    }

    private function productBatchSize(Export $export): int
    {
        $writer = $this->propertyValue($export, 'writer');
        $products = $this->propertyValue($writer, 'writers')['products'];
        $this->assertInstanceOf(Products::class, $products);

        return $this->propertyValue($this->propertyValue($products, 'iterator'), 'batchSize');
    }

    private function propertyValue(object $object, string $name): mixed
    {
        if (method_exists($object, '_getSubject')) {
            $getSubject = new ReflectionMethod($object, '_getSubject');
            $getSubject->setAccessible(true);
            $object = $getSubject->invoke($object);
        }

        $property = new ReflectionProperty($object, $name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /** @return string[][][] */
    private function readItems(string $feedFile): array
    {
        $feed = simplexml_load_file($feedFile);
        $this->assertInstanceOf(SimpleXMLElement::class, $feed);

        $items = [];
        foreach ($feed->items->item as $item) {
            $categories = [];
            foreach ($item->categories->categoryid as $categoryId) {
                $categories[] = (string)$categoryId;
            }
            sort($categories);

            $attributes = [];
            foreach ($item->attributes->attribute as $attribute) {
                $attributes[] = (string)$attribute->name . '=' . (string)$attribute->value;
            }
            sort($attributes);

            $items[(string)$item->id] = [
                'name' => [(string)$item->name],
                'price' => [(string)$item->price],
                'stock' => [(string)$item->stock],
                'groupcode' => [(string)$item->groupcode],
                'url' => [(string)$item->url],
                'categories' => $categories,
                'attributes' => $attributes,
            ];
        }

        return $items;
    }

    /** @param string[][][] $items */
    private function assertGroupedFeed(array $items): void
    {
        $this->assertNotEmpty($items, 'There are no products to export, is the sample data installed?');

        $groupCodes = [];
        $children = 0;
        foreach ($items as $item) {
            $groupCodes[$item['groupcode'][0]] = true;
            $names = array_map(static fn(string $a): string => explode('=', $a)[0], $item['attributes']);
            if (!in_array('parent_name', $names, true)) {
                continue;
            }

            $children++;
        }

        $this->assertGreaterThan(0, $children, 'The grouped feed has no children of configurable products');
        $this->assertLessThan(count($items), count($groupCodes), 'The grouped feed has no group with several items');
    }
}
