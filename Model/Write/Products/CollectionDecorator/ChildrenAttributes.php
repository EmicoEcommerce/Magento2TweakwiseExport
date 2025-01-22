<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator;

use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CompositeExportEntityInterface;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Stock\Collection as StockCollection;

class ChildrenAttributes implements DecoratorInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * ChildrenAttributes constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Decorate items with extra data or remove items completely
     *
     * @param Collection|StockCollection $collection
     */
    public function decorate(Collection|StockCollection $collection): void
    {
        foreach ($collection as $exportEntity) {
            if (!$exportEntity instanceof CompositeExportEntityInterface) {
                continue;
            }
            if (
                in_array(
                    $exportEntity->getTypeId(),
                    $this->config->getSkipChildByCompositeTypes($exportEntity->getStore()),
                    true
                )
            ) {
                continue;
            }
            foreach ($exportEntity->getExportChildren() as $child) {
                foreach ($child->getAttributes() as $attributeData) {
                    if ($this->config->getSkipChildAttribute($attributeData['attribute'])) {
                        continue;
                    }

                    $exportEntity->addAttribute($attributeData['attribute'], $attributeData['value']);
                }
            }
        }
    }
}
