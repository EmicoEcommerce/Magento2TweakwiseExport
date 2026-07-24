<?php

declare(strict_types=1);

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products;

enum DateFieldType: string
{
    case MIN = 'min';
    case MAX = 'max';
    case ALL = 'all';
}
