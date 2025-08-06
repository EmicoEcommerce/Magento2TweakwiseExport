<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Price;

use Tweakwise\Magento2TweakwiseExport\Model\ProductAttributes;
use Tweakwise\Magento2TweakwiseExport\Model\Write\EavIterator;

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
        $iterator->selectAttribute('tax_class_id');
    }
}
