<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products;

use Tweakwise\Magento2TweakwiseExport\Model\StockItem;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

class ExportEntityBundle extends CompositeExportEntity
{
    /**
     * @var bool
     */
    protected $isStockCombined;

    /**
     * @return StockItem
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getStockItem(): ?StockItem
    {
        if ($this->isStockCombined) {
            return $this->stockItem;
        }

        if (!$this->children) {
            return $this->stockItem;
        }

        $optionGroups = [];
        foreach ($this->getEnabledChildren() as $child) {
            $childOptions = $child->getChildOptions();
            if (!$childOptions) {
                continue;
            }

            $optionId = $childOptions->getOptionId();
            $childQty = $child->getStockItem() ? $child->getStockItem()->getQty() : 0;

            $optionGroups[$optionId]['qty'] =
                isset($optionGroups[$optionId]['qty'])
                    ? $optionGroups[$optionId]['qty'] + $childQty
                    : $childQty;

            if (isset($optionGroups[$optionId]['is_in_stock']) && $optionGroups[$optionId]['is_in_stock']) {
                continue;
            }

            $optionGroups[$optionId]['is_in_stock'] = $child->getStockItem()->getIsInStock();

            if (!$childOptions->isRequired()) {
                $optionGroups[$optionId]['is_in_stock'] = 1;
            }
        }

        if (empty($optionGroups)) {
            $this->isStockCombined = true;
            return $this->stockItem;
        }

        $qty = min(array_column($optionGroups, 'qty'));
        $isInStock = min(array_map(fn ($child) => $child['is_in_stock'], $optionGroups));
        $stockItem = new StockItem();
        $stockItem->setQty($qty);
        $stockItem->setIsInStock($isInStock);

        $this->stockItem = $stockItem;
        $this->isStockCombined = true;
        return $this->stockItem;
    }

    /**
     * @return bool
     */
    protected function shouldExportByChildStatus(): bool
    {
        $optionGroupStatus = [];
        foreach ($this->getAllChildren() as $child) {
            $childOptions = $child->getChildOptions();
            if (!$childOptions) {
                continue;
            }

            $optionId = $childOptions->getOptionId();
            if (!$childOptions->isRequired()) {
                $optionGroupStatus[$optionId] = 1;
                continue;
            }

            if (isset($optionGroupStatus[$optionId]) && $optionGroupStatus[$optionId]) {
                continue;
            }

            $childStatus = $child->getStatus() === Status::STATUS_ENABLED ? 1 : 0;
            $optionGroupStatus[$optionId] = $childStatus;
        }

        return (empty($optionGroupStatus)) || array_product($optionGroupStatus) === 1;
    }
}
