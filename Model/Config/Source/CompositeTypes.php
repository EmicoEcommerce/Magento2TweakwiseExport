<?php

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CompositeTypes implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'bundle',
                'label' => __('Bundled Products'),
            ],
            [
                'value' => 'configurable',
                'label' => __('Configurable Products'),
            ],
            [
                'value' => 'grouped',
                'label' => __('Grouped Products'),
            ],
        ];
    }
}
