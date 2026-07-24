<?php

declare(strict_types=1);

namespace Tweakwise\Magento2TweakwiseExport\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DateField implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'all', 'label' => __('All Dates')],
            ['value' => 'min', 'label' => __('Min Date')],
            ['value' => 'max', 'label' => __('Max Date')],
        ];
    }
}
