<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Review;

use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;

/**
 * Interface ReviewProviderInterface
 */
interface ReviewProviderInterface
{
    /**
     * @param Collection $collection
     * @return ProductReviewSummary[]
     */
    public function getProductReviews(Collection $collection): array;
}
