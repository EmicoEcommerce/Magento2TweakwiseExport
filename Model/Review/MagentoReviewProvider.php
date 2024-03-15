<?php

namespace Tweakwise\Magento2TweakwiseExport\Model\Review;

use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Magento\Review\Model\ResourceModel\Review\Summary\CollectionFactory as SummaryCollectionFactory;
use Magento\Review\Model\Review\Summary;

class MagentoReviewProvider implements ReviewProviderInterface
{
    /**
     * @var SummaryCollectionFactory
     */
    protected $summaryCollectionFactory;

    /**
     * MagentoReviewProvider constructor.
     * @param SummaryCollectionFactory $summaryCollectionFactory
     */
    public function __construct(
        SummaryCollectionFactory $summaryCollectionFactory
    ) {
        $this->summaryCollectionFactory = $summaryCollectionFactory;
    }

    /**
     * @param Collection $collection
     * @return ProductReviewSummary[]
     */
    public function getProductReviews(Collection $collection): array
    {
        $summaryCollection = $this->summaryCollectionFactory->create()
            ->addStoreFilter($collection->getStore()->getId())
            ->addEntityFilter($collection->getAllIds());

        $reviews = [];
        /** @var Summary $rating */
        foreach ($summaryCollection as $summary) {
            $reviews[] = $this->createProductReviewSummary($summary);
        }

        return $reviews;
    }

    /**
     * @param Summary $summary
     * @return ProductReviewSummary
     */
    protected function createProductReviewSummary(Summary $summary): ProductReviewSummary
    {
        return new ProductReviewSummary(
            $summary->getRatingSummary(),
            $summary->getReviewsCount(),
            $summary->getEntityPkValue()
        );
    }
}
