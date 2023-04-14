<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Stock;

use Tweakwise\Magento2TweakwiseExport\Model\ProductAttributes;
use Tweakwise\Magento2TweakwiseExport\Model\Write\EavIterator;

/**
 * Class IteratorInitializer
 * @package Tweakwise\Magento2TweakwiseExport\Model\Write\Products
 */
class IteratorInitializer
{
    /**
     * @var ProductAttributes
     */
    protected $productAttributes;

    /**
     * IteratorInitializer constructor.
     *
     * @param ProductAttributes $productAttributes
     */
    public function __construct(ProductAttributes $productAttributes)
    {
        $this->productAttributes = $productAttributes;
    }

    /**
     * Select all attributes who should be exported
     *
     * @param EavIterator $iterator
     */
    public function initializeAttributes(EavIterator $iterator)
    {
        // Add default attributes
        $iterator->selectAttribute('sku');
        $iterator->selectAttribute('status');
        $iterator->selectAttribute('visibility');
        $iterator->selectAttribute('type_id');

        $i = 0;

        foreach ($this->productAttributes->getAttributesToExport() as $attribute) {
            if ($i < 2) continue;
            //$iterator->selectAttribute($attribute->getAttributeCode());
            $i++;

        }
    }
}
