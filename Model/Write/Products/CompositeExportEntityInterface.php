<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products;

interface CompositeExportEntityInterface
{
    /**
     * @param ExportEntityChild $child
     * @return mixed
     */
    public function addChild(ExportEntityChild $child);

    /**
     * This method should return all known children for the composite export entity
     *
     * @return ExportEntityChild[]
     */
    public function getAllChildren();

    /**
     * This method should return all children that are eligible for export (this will be written in the feed)
     *
     * @return ExportEntityChild[]
     */
    public function getExportChildren();

    /**
     * This method should return all enabled export children regardless of stock status and quantity
     *
     * @return ExportEntityChild[]
     */
    public function getEnabledChildren();
}
