<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Tweakwise\Magento2TweakwiseExport\Model;

/**
 * Used solely for stock calculation for bundle products
 *
 * Class ChildOptions
 */
class ChildOptions
{
    /**
     * @var int
     */
    protected $optionId;

    /**
     * ChildOptions constructor.
     * @param int|null $optionId
     * @param null $isRequired
     */
    public function __construct(?int $optionId = null, protected $isRequired = null)
    {
        $this->optionId = $optionId;
    }

    /**
     * @return int
     */
    public function getOptionId(): ?int
    {
        return $this->optionId;
    }

    /**
     * @param int $optionId
     */
    public function setOptionId(int $optionId): void
    {
        $this->optionId = $optionId;
    }

    /**
     * @return bool|null
     */
    public function isRequired(): ?bool
    {
        return $this->isRequired;
    }

    /**
     * @param bool $isRequired
     */
    public function setIsRequired(bool $isRequired): void
    {
        // @phpstan-ignore-next-line
        $this->isRequired = $isRequired;
    }
}
