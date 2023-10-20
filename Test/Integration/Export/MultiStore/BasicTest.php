<?php
/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   Proprietary and confidential, Unauthorized copying of this file, via any medium is strictly prohibited
 */

namespace Tweakwise\Magento2TweakwiseExport\Test\Integration\Export\MultiStore;

use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Test\Integration\Export\MultiStoreTest;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

/**
 * Class BasicTest
 *
 * @package Tweakwise\Magento2TweakwiseExport\Test\Integration\Export\Product
 *
 * @magentoDataFixtureBeforeTransaction createMultiStoreFixture
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class BasicTest extends MultiStoreTest
{
    /**
     * Test multiple stores enabled
     */
    public function testEnabled()
    {
        $product = $this->productData->create();

        $feed = $this->exportFeed();
        $feed->getProduct($product->getId());
        $feed->getProduct($product->getId(), self::STORE_STORE_CODE);
    }

    /**
     * Test if feed will not be exported for disabled store
     */
    public function testDisabledStore()
    {
        $this->setConfig(Config::PATH_ENABLED, false);

        $product = $this->productData->create();

        $feed = $this->exportFeed();
        $feed->assertProductMissing($product->getId());
        $feed->getProduct($product->getId(), self::STORE_STORE_CODE);
    }

    /**
     * Test if product will be skipped if disabled in store
     */
    public function testDisabledProductForOneStore()
    {
        $product = $this->productData->create();

        $this->productData->saveAttribute($product, 'status', Status::STATUS_DISABLED, self::STORE_STORE_CODE);

        $feed = $this->exportFeed();
        $feed->getProduct($product->getId());
        $feed->assertProductMissing($product->getId(), self::STORE_STORE_CODE);
    }

    /**
     * Test if feed will not be exported for disabled store
     */
    public function testUnlinkStore()
    {
        $websiteIds = [$this->storeProvider->getStoreView(self::STORE_STORE_CODE)->getWebsiteId()];
        $product = $this->productData->create();
        $this->productData->saveWebsiteLink($product, $websiteIds);

        $feed = $this->exportFeed();
        $feed->assertProductMissing($product->getId());
        $feed->getProduct($product->getId(), self::STORE_STORE_CODE);
    }
}
