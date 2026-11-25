<?php

declare(strict_types=1);

namespace Tweakwise\Test\Functional\Export;

use Emico\CodeCept\Models\Fixtures\BundleProductFixture;
use Emico\CodeCept\Models\Fixtures\CategoryFixture;
use Emico\CodeCept\Models\Fixtures\ConfigurableProductFixture;
use Emico\CodeCept\Models\Fixtures\ProductFixture;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Test\Support\Export\AbstractExportTest;

class GroupedExportTest extends AbstractExportTest
{
    private const OPTION_ATTRIBUTE = 'tw_export_test_option';

    private const OPTIONS = ['one', 'two', 'three'];

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _before(): void
    {
        parent::_before();

        $this->tester->mockConfig(Config::PATH_GROUPED_EXPORT_ENABLED, '1');
    }

    public function testChildrenAreExportedAsOneGroupWithTheDataOfTheParent(): void
    {
        $parent = $this->createParent('grouped-test-data');

        $items = $this->exportFeed();

        $this->assertNotInFeed($items, $parent);
        foreach ($parent->getChildren() as $child) {
            $this->assertInFeed($items, $child);
            $this->assertSame($this->feedId($parent), $this->groupCode($items, $child));
            $this->assertSame(['Parent grouped-test-data'], $this->attributeValues($items, $child, 'parent_name'));
            $this->assertSame(['grouped-test-data-url'], $this->attributeValues($items, $child, 'parent_url_key'));
            $this->assertContains(
                (string)Visibility::VISIBILITY_BOTH,
                $this->attributeValues($items, $child, 'parent_visibility')
            );
        }
    }

    public function testChildWithoutCategoriesGetsTheCategoriesOfTheParent(): void
    {
        $category = $this->tester->createFixture(new CategoryFixture('grouped-test-parent-category'));
        $parent = $this->createParent('grouped-test-categories', $category);

        $items = $this->exportFeed();

        foreach ($parent->getChildren() as $child) {
            $this->assertContains($this->feedIdOfCategory($category), $this->categoryIds($items, $child));
        }
    }

    public function testProductWithoutParentIsItsOwnGroup(): void
    {
        $product = $this->tester->createFixture(new ProductFixture('grouped-test-single'));

        $items = $this->exportFeed();

        $this->assertSame($this->feedId($product), $this->groupCode($items, $product));
    }

    public function testDisabledChildIsNotExported(): void
    {
        $parent = new ConfigurableProductFixture('grouped-test-disabled');
        $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => 'one']);
        $disabled = $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => 'two'])
            ->setStatus(Status::STATUS_DISABLED);
        $this->tester->createFixture($parent);

        $items = $this->exportFeed();

        $this->assertInFeed($items, $parent->getChild(0));
        $this->assertNotInFeed($items, $disabled);
    }

    public function testOutOfStockChildIsNotExported(): void
    {
        $this->tester->mockConfig(Config::PATH_OUT_OF_STOCK_CHILDREN, '0');

        [$inStock, $outOfStock] = $this->createParentWithOutOfStockChild('grouped-test-out-of-stock');

        $items = $this->exportFeed();

        $this->assertInFeed($items, $inStock);
        $this->assertNotInFeed($items, $outOfStock);
    }

    public function testOutOfStockChildIsExportedWhenEnabled(): void
    {
        $this->tester->mockConfig(Config::PATH_OUT_OF_STOCK_CHILDREN, '1');

        [$inStock, $outOfStock] = $this->createParentWithOutOfStockChild('grouped-test-out-of-stock-shown');

        $items = $this->exportFeed();

        $this->assertInFeed($items, $inStock);
        $this->assertInFeed($items, $outOfStock);
    }

    public function testBundleProductIsItsOwnGroup(): void
    {
        $first = $this->tester->createFixture(new ProductFixture('grouped-test-bundle-first'));
        $second = $this->tester->createFixture(new ProductFixture('grouped-test-bundle-second'));
        $bundle = new BundleProductFixture('grouped-test-bundle');
        $bundle->addBundleOption([$first, $second]);
        $this->tester->createFixture($bundle);

        $items = $this->exportFeed();

        $this->assertSame($this->feedId($bundle), $this->groupCode($items, $bundle));
        $this->assertSame($this->feedId($first), $this->groupCode($items, $first));
    }

    public function testGroupedProductIsItsOwnGroup(): void
    {
        $grouped = $this->tester->createFixture(
            new ProductFixture('grouped-test-grouped-type', null, 10.0, 100.0, 4, 'grouped')
        );
        $associated = new ProductFixture('grouped-test-associated');
        $associated->addReverseProductLink($grouped, 'associated');
        $this->tester->createFixture($associated);

        $items = $this->exportFeed();

        $this->assertSame($this->feedId($grouped), $this->groupCode($items, $grouped));
        $this->assertSame($this->feedId($associated), $this->groupCode($items, $associated));
    }

    private function createParent(string $sku, ?CategoryFixture $category = null): ConfigurableProductFixture
    {
        $parent = new ConfigurableProductFixture($sku, 'Parent ' . $sku);
        $parent->setUrlKey($sku . '-url');
        if ($category !== null) {
            $parent->assignToCategory($category);
        }
        foreach (self::OPTIONS as $option) {
            $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => $option]);
        }

        return $this->tester->createFixture($parent);
    }

    /** @return ProductFixture[] */
    private function createParentWithOutOfStockChild(string $sku): array
    {
        $parent = new ConfigurableProductFixture($sku);
        $inStock = $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => 'one']);
        $outOfStock = $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => 'two']);
        $this->tester->createFixture($parent);
        $this->setOutOfStock($outOfStock);

        return [$inStock, $outOfStock];
    }
}
