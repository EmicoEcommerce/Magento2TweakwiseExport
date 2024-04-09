<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products;

use Tweakwise\Magento2TweakwiseExport\Traits\Stock\HasStockThroughChildren;

class ExportEntityConfigurable extends CompositeExportEntity
{
    use HasStockThroughChildren;

    /**
     * @var bool
     */
    protected $isStockCombined;

    /**
     * @param array $data
     */
    public function setFromArray(array $data): void
    {
        if (key_exists('price', $data) && (float)$data['price'] === 0.00) {
            unset($data['price']);
        }

        parent::setFromArray($data);
    }
}
