<?php

namespace Tweakwise\Magento2TweakwiseExport\Model;

class StockItem
{
    /**
     * @var int
     */
    protected $qty = 0;

    /**
     * @var int
     */
    protected $isInStock = 0;

    /**
     * @return int
     */
    public function getQty(): int
    {
        return $this->qty;
    }

    /**
     * @param int $qty
     */
    public function setQty(int $qty): void
    {
        $this->qty = $qty;
    }

    /**
     * @return int
     */
    public function getIsInStock(): int
    {
        return $this->isInStock;
    }

    /**
     * @param int $isInStock
     */
    public function setIsInStock(int $isInStock): void
    {
        $this->isInStock = $isInStock;
    }

    /**
     * @param int $qty
     */
    public function updateQty(int $qty): void
    {
        $this->qty += $qty;
    }

    /**
     * @param int $isInStock
     */
    public function updateIsInStock(int $isInStock): void
    {
        $this->isInStock = max($this->isInStock, $isInStock);
    }
}
