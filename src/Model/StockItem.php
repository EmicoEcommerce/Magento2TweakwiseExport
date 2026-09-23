<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

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
     * @var float
     */
    protected $orderQty = 1.0;

    /**
     * @var bool
     */
    protected $enableQtyIncrements = false;

    /**
     * @var float
     */
    protected $qtyIncrements = 1.0;

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

    /**
     * @return float
     */
    public function getOrderQty(): float
    {
        return $this->orderQty;
    }

    /**
     * @param float $orderQty
     */
    public function setOrderQty(float $orderQty): void
    {
        $this->orderQty = $orderQty;
    }

    /**
     * @return bool
     */
    public function isEnableQtyIncrements(): bool
    {
        return $this->enableQtyIncrements;
    }

    /**
     * @param bool $enableQtyIncrements
     */
    public function setEnableQtyIncrements(bool $enableQtyIncrements): void
    {
        $this->enableQtyIncrements = $enableQtyIncrements;
    }

    /**
     * @return float
     */
    public function getQtyIncrements(): float
    {
        return $this->qtyIncrements;
    }

    /**
     * @param float $qtyIncrements
     */
    public function setQtyIncrements(float $qtyIncrements): void
    {
        $this->qtyIncrements = $qtyIncrements;
    }
}
