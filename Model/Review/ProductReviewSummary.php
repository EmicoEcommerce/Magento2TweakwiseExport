<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Review;

/**
 * Class ProductSummary
 * There is no Magento native review interface or summary interface, hence this class
 */
class ProductReviewSummary
{
    /**
     * @var float
     */
    protected $averageRating;

    /**
     * @var int
     */
    protected $reviewCount;

    /**
     * @var int
     */
    protected $productId;

    /**
     * ProductSummary constructor.
     * @param float $averageRating
     * @param int $reviewCount
     * @param int $productId
     */
    public function __construct(
        float $averageRating,
        int $reviewCount,
        int $productId
    ) {
        $this->averageRating = $averageRating;
        $this->reviewCount = $reviewCount;
        $this->productId = $productId;
    }

    /**
     * @return float
     */
    public function getAverageRating(): float
    {
        return $this->averageRating;
    }

    /**
     * @param float $averageRating
     */
    public function setAverageRating(float $averageRating): void
    {
        $this->averageRating = $averageRating;
    }

    /**
     * @return int
     */
    public function getReviewCount(): int
    {
        return $this->reviewCount;
    }

    /**
     * @param int $reviewCount
     */
    public function setReviewCount(int $reviewCount): void
    {
        $this->reviewCount = $reviewCount;
    }

    /**
     * @return int
     */
    public function getProductId(): int
    {
        return $this->productId;
    }

    /**
     * @param int $productId
     */
    public function setProductId(int $productId): void
    {
        $this->productId = $productId;
    }
}
