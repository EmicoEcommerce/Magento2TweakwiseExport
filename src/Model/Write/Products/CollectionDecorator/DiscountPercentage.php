<?php

declare(strict_types=1);

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator;

use Magento\Bundle\Model\Product\Type as BundleType;
use Tweakwise\Magento2TweakwiseExport\Exception\InvalidArgumentException;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntity;

class DiscountPercentage implements DecoratorInterface
{
    private const ATTRIBUTE_NAME = 'discount_percentage';

    /**
     * Decorate items with a computed discount_percentage attribute.
     * Skipped for bundle products (pricing too complex) and products with no discount.
     *
     * @param Collection $collection
     */
    public function decorate(Collection $collection): void
    {
        foreach ($collection as $entity) {
            if ($entity->getTypeId() === BundleType::TYPE_CODE) {
                continue;
            }

            $discount = $this->calculateDiscount($entity);
            if ($discount <= 0) {
                continue;
            }

            $entity->addAttribute(self::ATTRIBUTE_NAME, $discount);
        }
    }

    /**
     * @param ExportEntity $entity
     * @return int
     */
    private function calculateDiscount(ExportEntity $entity): int
    {
        try {
            $regularPrice = (float)$entity->getAttribute('regular_price', false);
            $finalPrice = (float)$entity->getAttribute('final_price', false);
        } catch (InvalidArgumentException $e) {
            return 0;
        }

        if ($regularPrice <= 0.00001 || $finalPrice >= $regularPrice) {
            return 0;
        }

        return (int)round(($regularPrice - $finalPrice) / $regularPrice * 100);
    }
}
