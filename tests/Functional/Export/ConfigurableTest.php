<?php

declare(strict_types=1);

namespace Tweakwise\Test\Functional\Export;

use Emico\CodeCept\Models\Fixtures\AttributeManager;
use Emico\CodeCept\Models\Fixtures\ConfigurableProductFixture;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Model\Configuration as StockConfiguration;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Test\Support\Export\AbstractExportTest;

class ConfigurableTest extends AbstractExportTest
{
    private const OPTION_ATTRIBUTE = 'tw_export_test_option';

    private const EXTRA_ATTRIBUTE = 'tw_export_test_extra';

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _before(): void
    {
        parent::_before();

        $attributeManager = $this->tester->getObjectManager()->get(AttributeManager::class);
        $exported = ['is_filterable' => 1, 'used_in_product_listing' => 1];
        foreach ([self::OPTION_ATTRIBUTE, self::EXTRA_ATTRIBUTE] as $code) {
            $attributeManager->getAttribute($code, 'catalog_product', $exported);
        }
    }

    public function testVisibleChildrenAreExportedAsWell(): void
    {
        $parent = new ConfigurableProductFixture('configurable-test-visible');
        $children = [];
        foreach (['one', 'two'] as $option) {
            $children[] = $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => $option])
                ->setVisibility(Visibility::VISIBILITY_BOTH);
        }
        $this->tester->createFixture($parent);

        $items = $this->exportFeed();

        $this->assertInFeed($items, $parent);
        foreach ($children as $child) {
            $this->assertInFeed($items, $child);
        }
    }

    public function testDisabledChildrenAreNotExportedAndTheirAttributesAreLeftOut(): void
    {
        $parent = new ConfigurableProductFixture('configurable-test-status');
        $children = [];
        $children[] = $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => 'one']);
        $children[] = $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => 'two']);
        $children[] = $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => 'three'])
            ->setStatus(Status::STATUS_DISABLED);
        $this->tester->createFixture($parent);

        $items = $this->exportFeed();

        $this->assertSame(['one', 'two'], $this->attributeValues($items, $parent, self::OPTION_ATTRIBUTE));
        foreach ($children as $child) {
            $this->assertNotInFeed($items, $child);
        }
    }

    public function testParentIsNotExportedWhenAllChildrenAreDisabled(): void
    {
        $parent = new ConfigurableProductFixture('configurable-test-all-disabled');
        foreach (['one', 'two'] as $option) {
            $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => $option])->setStatus(Status::STATUS_DISABLED);
        }
        $this->tester->createFixture($parent);

        $this->assertNotInFeed($this->exportFeed(), $parent);
    }

    public function testAttributesOfChildrenAreAddedToTheParent(): void
    {
        $parent = $this->createParentAndChildWithExtraAttribute();

        $items = $this->exportFeed();

        $this->assertSame(['child', 'parent'], $this->attributeValues($items, $parent, self::EXTRA_ATTRIBUTE));
    }

    public function testExcludedChildAttributesAreTakenFromTheParentOnly(): void
    {
        $this->tester->mockConfig(Config::PATH_EXCLUDE_CHILD_ATTRIBUTES, self::EXTRA_ATTRIBUTE);

        $parent = $this->createParentAndChildWithExtraAttribute();

        $items = $this->exportFeed();

        $this->assertSame(['parent'], $this->attributeValues($items, $parent, self::EXTRA_ATTRIBUTE));
    }

    public function testAttributesOfOutOfStockChildrenAreExportedWhenEnabled(): void
    {
        $this->tester->mockConfig(StockConfiguration::XML_PATH_SHOW_OUT_OF_STOCK, '0');
        $this->tester->mockConfig(Config::PATH_OUT_OF_STOCK_CHILDREN, '1');

        $parent = $this->createParentWithOneChildOutOfStock();

        $items = $this->exportFeed();

        $this->assertSame(['one', 'three', 'two'], $this->attributeValues($items, $parent, self::OPTION_ATTRIBUTE));
    }

    public function testAttributesOfOutOfStockChildrenAreLeftOutWhenDisabled(): void
    {
        $this->tester->mockConfig(StockConfiguration::XML_PATH_SHOW_OUT_OF_STOCK, '0');
        $this->tester->mockConfig(Config::PATH_OUT_OF_STOCK_CHILDREN, '0');

        $parent = $this->createParentWithOneChildOutOfStock();

        $items = $this->exportFeed();

        $this->assertSame(['one', 'two'], $this->attributeValues($items, $parent, self::OPTION_ATTRIBUTE));
    }

    private function createParentAndChildWithExtraAttribute(): ConfigurableProductFixture
    {
        $parent = new ConfigurableProductFixture('configurable-test-extra');
        $parent->addAttribute(self::EXTRA_ATTRIBUTE, 'parent');
        $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => 'one'])->addAttribute(self::EXTRA_ATTRIBUTE, 'child');

        return $this->tester->createFixture($parent);
    }

    private function createParentWithOneChildOutOfStock(): ConfigurableProductFixture
    {
        $parent = new ConfigurableProductFixture('configurable-test-stock');
        $children = [];
        foreach (['one', 'two', 'three'] as $option) {
            $children[] = $parent->addChildByAttributes([self::OPTION_ATTRIBUTE => $option]);
        }
        $this->tester->createFixture($parent);
        $this->setOutOfStock($children[2]);

        return $parent;
    }
}
