<?php

declare(strict_types=1);

namespace Tweakwise\Magento2TweakwiseExport\Model\Config\Source;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductImageAttributes implements OptionSourceInterface
{
    /**
     * @param EavConfig $eavConfig
     */
    public function __construct(private readonly EavConfig $eavConfig)
    {
    }

    /**
     * Return catalog product attributes with frontend input type "media_image" as select options,
     * with a blank "disabled" option first.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [
            ['value' => '', 'label' => __('-- Disabled --')->render()],
        ];

        try {
            $type = $this->eavConfig->getEntityType(Product::ENTITY);
        } catch (LocalizedException $e) {
            return $options;
        }

        $attributes = [];
        foreach ($type->getAttributeCollection() as $attribute) {
            if ($attribute->getFrontendInput() !== 'media_image') {
                continue;
            }

            $code = $attribute->getAttributeCode();
            $label = $attribute->getDefaultFrontendLabel();
            $attributes[] = [
                'value' => $code,
                'label' => $label !== '' && $label !== null
                    ? sprintf('%s [%s]', $label, $code)
                    : $code,
            ];
        }

        usort($attributes, static fn(array $a, array $b) => strnatcasecmp($a['label'], $b['label']));

        return array_merge($options, $attributes);
    }
}
