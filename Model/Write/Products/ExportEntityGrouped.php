<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products;

use Tweakwise\Magento2TweakwiseExport\Traits\Stock\HasStockThroughChildren;

class ExportEntityGrouped extends CompositeExportEntity
{
    use HasStockThroughChildren;

    /**
     * @var bool
     */
    protected $isStockCombined;
}
