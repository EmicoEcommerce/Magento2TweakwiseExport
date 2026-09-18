<?php

declare(strict_types=1);

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Products\CollectionDecorator;

use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Tweakwise\Magento2TweakwiseExport\Exception\InvalidArgumentException;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Price\Collection as PriceCollection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Price\ExportEntity as PriceExportEntity;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\Collection;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityChild;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntityConfigurable;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Products\ExportEntity;

class DiscountPercentage implements DecoratorInterface
{
    private const ATTRIBUTE_NAME = 'discount_percentage';

    /**
     * Decorate items with a computed discount_percentage attribute.
     * Skipped for bundle/grouped products (pricing too complex) and products with no discount.
     *
     * @param Collection|PriceCollection $collection
     */
    public function decorate(Collection|PriceCollection $collection): void
    {
        foreach ($collection as $entity) {
            if (in_array($entity->getTypeId(), [BundleType::TYPE_CODE, Grouped::TYPE_CODE], true)) {
                continue;
            }

            if ($entity instanceof ExportEntityConfigurable) {
                $discount = $this->calculateConfigurableDiscount($entity);
            } else {
                $discount = $this->calculateSimpleDiscount($entity);
            }

            if ($discount <= 0) {
                continue;
            }

            $entity->addAttribute(self::ATTRIBUTE_NAME, $discount);
        }
    }

    /**
     * @param ExportEntity|PriceExportEntity $entity
     * @return int
     */
    private function calculateSimpleDiscount(ExportEntity|PriceExportEntity $entity): int
    {
        $regularPrice = $entity->getRegularPrice();
        if ($regularPrice === null) {
            return 0;
        }

        try {
            $finalPrice = (float)$entity->getAttribute('final_price', false);
        } catch (InvalidArgumentException $e) {
            return 0;
        }

        return $this->calculateDiscountValue((float)$regularPrice, $finalPrice);
    }

    /**
     * @param ExportEntityConfigurable $entity
     * @return int
     */
    private function calculateConfigurableDiscount(ExportEntityConfigurable $entity): int
    {
        $maxDiscount = 0;
        foreach ($entity->getExportChildren() as $child) {
            $discount = $this->calculateChildDiscount($child);
            if ($discount > $maxDiscount) {
                $maxDiscount = $discount;
            }
        }

        return $maxDiscount;
    }

    /**
     * @param ExportEntityChild $child
     * @return int
     */
    private function calculateChildDiscount(ExportEntityChild $child): int
    {
        $regularPrice = $child->getRegularPrice();
        if ($regularPrice === null) {
            return 0;
        }

        try {
            $finalPrice = (float)$child->getAttribute('final_price', false);
        } catch (InvalidArgumentException $e) {
            return 0;
        }

        return $this->calculateDiscountValue((float)$regularPrice, $finalPrice);
    }

    /**
     * @param float $regularPrice
     * @param float $finalPrice
     * @return int
     */
    private function calculateDiscountValue(float $regularPrice, float $finalPrice): int
    {
        if ($regularPrice <= 0.00001 || $finalPrice >= $regularPrice) {
            return 0;
        }

        return (int)round(($regularPrice - $finalPrice) / $regularPrice * 100);
    }
}
