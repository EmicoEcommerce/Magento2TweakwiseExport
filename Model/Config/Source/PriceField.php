<?php

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model\Config\Source;

use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Option\ArrayInterface;

class PriceField implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $priceFields = [
            'min_price' => 'Min Price',
            'final_price' => 'Final Price',
            'price' => 'Price',
            'max_price' => 'Max Price'
        ];

        $priceFieldPermutations = $this->combineArrayPermutations($priceFields);

        return array_map(
            function ($option) {
                $value = implode(',', array_keys($option));
                $label = implode(' -> ', array_values($option));
                return ['value' => $value, 'label' => $label];
            },
            $priceFieldPermutations
        );
    }

    /**
     * @param array $input
     * @param array $processed
     * @return array
     * phpcs:disable Magento2.Performance.ForeachArrayMerge.ForeachArrayMerge
     */
    protected function combineArrayPermutations(array $input, array $processed = null): array
    {
        $permutations = [];
        foreach ($input as $key => $value) {
            $copy = $processed ?? [];
            $copy[$key] = $value;
            $tmp = \array_diff_key($input, $copy);
            if (\count($tmp) === 0) {
                $permutations[] = $copy;
            } else {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $permutations = array_merge($permutations, $this->combineArrayPermutations($tmp, $copy));
            }
        }

        return $permutations;
    }
}
