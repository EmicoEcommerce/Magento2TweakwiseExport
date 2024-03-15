<?php

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   Proprietary and confidential, Unauthorized copying of this file, via any medium is strictly prohibited
 */

namespace Tweakwise\Magento2TweakwiseExport\Test;

if (class_exists('PHPUnit\Framework\TestCase')) {
    abstract class BaseTestCase extends \PHPUnit\Framework\TestCase
    {
    }
} else {
    // phpcs:disable Generic.Classes.DuplicateClassName.Found
    // phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
    abstract class BaseTestCase extends \PHPUnit_Framework_TestCase
    {
    }
}

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
abstract class TestCase extends BaseTestCase
{
    /**
     * Asserts that an array has a specified subset.
     *
     * @param array $subset
     * @param array $array
     */
    public function safeAssertArraySubset(array $subset, array $array)
    {
        if (method_exists($this, 'assertArraySubset')) {
            $this->assertArraySubset($subset, $array);
        } else {
            foreach ($subset as $field => $value) {
                $this->assertArrayHasKey($field, $array);
                $this->assertEquals($array[$field], $value);
            }
        }
    }
}
