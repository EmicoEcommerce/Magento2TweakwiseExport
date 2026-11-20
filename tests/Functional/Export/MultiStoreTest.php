<?php

declare(strict_types=1);

namespace Tweakwise\Test\Functional\Export;

use Emico\CodeCept\Models\Fixtures\ConfigurableProductFixture;
use Emico\CodeCept\Models\Fixtures\ProductFixture;
use Hipex\Magento2Psr7Bridge\Http\Connector;
use Magento\Catalog\Model\Product\Action;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Test\Support\Export\AbstractExportTest;
use Tweakwise\Test\Support\Performance\WebsiteGenerator;

class MultiStoreTest extends AbstractExportTest
{
    private const OTHER_STORE = WebsiteGenerator::CODE_PREFIX . '1';

    private WebsiteGenerator $websites;

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _before(): void
    {
        parent::_before();

        $objectManager = $this->tester->getObjectManager();
        $this->websites = new WebsiteGenerator($objectManager->get(ResourceConnection::class));
        $this->websites->add(1);
        $this->refreshStores();
    }

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _after(): void
    {
        if (isset($this->websites)) {
            $this->refreshStores();
            $this->tester->cleanUp();
            $this->websites->removeAll();
            $this->refreshStores();
        }

        parent::_after();
    }

    public function testProductIsExportedForEveryStore(): void
    {
        $product = $this->tester->createFixture(new ProductFixture('multi-store-test-basic'));

        $items = $this->exportFeed();

        $this->assertInFeed($items, $product);
        $this->assertInFeed($items, $product, self::OTHER_STORE);
    }

    public function testStoreWithExportDisabledIsLeftOut(): void
    {
        $this->tester->mockConfig(Config::PATH_ENABLED, '0');
        $this->tester->mockConfig(Config::PATH_ENABLED, '1', self::OTHER_STORE);

        $product = $this->tester->createFixture(new ProductFixture('multi-store-test-disabled-store'));

        $items = $this->exportFeed();

        $this->assertNotInFeed($items, $product);
        $this->assertInFeed($items, $product, self::OTHER_STORE);
    }

    public function testProductDisabledForOneStoreIsLeftOutThere(): void
    {
        $product = $this->tester->createFixture(new ProductFixture('multi-store-test-disabled-product'));
        $this->disableForStore($product, self::OTHER_STORE);

        $items = $this->exportFeed();

        $this->assertInFeed($items, $product);
        $this->assertNotInFeed($items, $product, self::OTHER_STORE);
    }

    public function testProductNotLinkedToAWebsiteIsLeftOutThere(): void
    {
        $product = $this->tester->createFixture(
            (new ProductFixture('multi-store-test-unlinked'))->setWebsites([self::OTHER_STORE])
        );

        $items = $this->exportFeed();

        $this->assertNotInFeed($items, $product);
        $this->assertInFeed($items, $product, self::OTHER_STORE);
    }

    public function testConfigurableIsLeftOutWhereAllChildrenAreDisabled(): void
    {
        $parent = new ConfigurableProductFixture('multi-store-test-configurable');
        $children = [];
        foreach (['one', 'two'] as $option) {
            $children[] = $parent->addChildByAttributes(['tw_export_test_option' => $option]);
        }
        $this->tester->createFixture($parent);
        foreach ($children as $child) {
            $this->disableForStore($child, self::OTHER_STORE);
        }

        $items = $this->exportFeed();

        $this->assertInFeed($items, $parent);
        $this->assertNotInFeed($items, $parent, self::OTHER_STORE);
    }

    public function testGroupedExportTakesTheDataOfTheParentFromEachStore(): void
    {
        $this->tester->mockConfig(Config::PATH_GROUPED_EXPORT_ENABLED, '1');

        $parent = new ConfigurableProductFixture('multi-store-test-grouped', 'Parent name');
        $child = $parent->addChildByAttributes(['tw_export_test_option' => 'one']);
        $this->tester->createFixture($parent);
        $this->renameForStore($parent, 'Other store name', self::OTHER_STORE);

        $items = $this->exportFeed();

        $this->assertSame(['Parent name'], $this->attributeValues($items, $child, 'parent_name'));
        $this->assertSame(
            ['Other store name'],
            $this->attributeValues($items, $child, 'parent_name', self::OTHER_STORE)
        );
        $this->assertSame($this->feedId($parent), $this->groupCode($items, $child));
        $this->assertSame(
            $this->feedId($parent, self::OTHER_STORE),
            $this->groupCode($items, $child, self::OTHER_STORE)
        );
    }

    private function refreshStores(): void
    {
        $this->tester->clearCache();
        $this->tester->forEachConnector(
            static function (Connector $connector): void {
                $connector->getObjectManager()->get(StoreManagerInterface::class)->reinitStores();
            }
        );
    }

    private function disableForStore(ProductFixture $product, string $storeCode): void
    {
        $this->updateForStore($product, ['status' => Status::STATUS_DISABLED], $storeCode);
    }

    private function renameForStore(ProductFixture $product, string $name, string $storeCode): void
    {
        $this->updateForStore($product, ['name' => $name], $storeCode);
    }

    /** @param string[]|int[] $data */
    private function updateForStore(ProductFixture $product, array $data, string $storeCode): void
    {
        $objectManager = $this->tester->getObjectManager();
        $storeId = (int)$objectManager->get(StoreManagerInterface::class)->getStore($storeCode)->getId();

        $objectManager->get(Action::class)->updateAttributes([(int)$product->getId()], $data, $storeId);
    }
}
