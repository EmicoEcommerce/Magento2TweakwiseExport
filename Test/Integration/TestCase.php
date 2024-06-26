<?php

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   Proprietary and confidential, Unauthorized copying of this file, via any medium is strictly prohibited
 */

namespace Tweakwise\Magento2TweakwiseExport\Test\Integration;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Class TestCase
 *
 * @package Tweakwise\Magento2TweakwiseExport\Test\Integration
 */
abstract class TestCase extends \Tweakwise\Magento2TweakwiseExport\Test\TestCase
{
    /**
     * @param string $type
     * @return mixed
     */
    protected function getObject(string $type)
    {
        return Bootstrap::getObjectManager()->get($type);
    }

    /**
     * @param string $type
     * @param array $arguments
     * @return mixed
     */
    protected function createObject(string $type, array $arguments = [])
    {
        return Bootstrap::getObjectManager()->create($type, $arguments);
    }

    /**
     * Ensure all objects are destroyed
     * @param string $type
     */
    protected function clearObject(string $type)
    {
        $objectManager = Bootstrap::getObjectManager();
        if ($objectManager instanceof ObjectManager) {
            $objectManager->removeSharedInstance($type);
        }
    }

    /**
     * @param string $path
     * @param mixed $value
     * @param string|null $store
     * @param string $scope
     * @return $this
     */
    protected function setConfig(
        string $path,
        $value,
        string $store = null,
        string $scope = ScopeInterface::SCOPE_STORE
    ) {
        /** @var MutableScopeConfigInterface $config */
        $config = $this->getObject(MutableScopeConfigInterface::class);
        $config->setValue($path, $value, $scope, $store);
        return $this;
    }
}
