<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model\Write\Categories;

use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Magento2TweakwiseExport\Model\Write\EavIterator;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\Manager;
use Magento\Framework\Model\ResourceModel\Db\Context as DbContext;
use Tweakwise\Magento2TweakwiseExport\Model\Config as TweakwiseConfig;
use Traversable;

class Iterator extends EavIterator
{
    /**
     * @var array
     */
    protected $entityBatchOrder = [
        'level',
        'path'
    ];

    /**
     * @var string[]
     */
    protected $eavSelectOrder = [
        'path',
        'entity_id',
        'store_id'
    ];

    /**
     * Iterator constructor.
     *
     * @param Helper $helper
     * @param EavConfig $eavConfig
     * @param DbContext $dbContext
     * @param Manager $eventManager
     * @param array $attributes
     * @param TweakwiseConfig $config
     */
    public function __construct(
        Helper $helper,
        EavConfig $eavConfig,
        DbContext $dbContext,
        Manager $eventManager,
        array $attributes,
        TweakwiseConfig $config
    ) {
        parent::__construct(
            $helper,
            $eavConfig,
            $dbContext,
            $eventManager,
            $config,
            'catalog_category',
            $attributes,
            $config->getBatchSizeCategories()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getStaticAttributeSelect(array $attributes): array
    {
        $selects = parent::getStaticAttributeSelect($attributes);

        foreach ($selects as $select) {
            $select->columns('path');
        }

        return $selects;
    }

    /**
     * {@inheritdoc}
     */
    protected function createEavAttributeGroupSelect(string $group, array $attributes): Select
    {
        $select = parent::createEavAttributeGroupSelect($group, $attributes);

        if ($this->helper->isEnterprise()) {
            $select->columns('main_table.path');
        } else {
            /** @var AbstractAttribute $staticAttribute */
            $staticAttribute = reset($this->getAttributeGroups()['_static']);

            /** @var AbstractAttribute $eavAttribute */
            $eavAttribute = reset($attributes);

            $select->join(
                $staticAttribute->getBackendTable(),
                $staticAttribute->getBackendTable() . '.entity_id = ' . $eavAttribute->getBackendTable() . '.entity_id',
                ['path']
            );
        }

        return $select;
    }

    /**
     * @return Traversable
     * @throws Zend_Db_Statement_Exception
     */
    public function getIterator(): Traversable
    {
        foreach (parent::getIterator() as $entityData) {
            if (!$this->shouldProcess($entityData)) {
                continue;
            }

            yield $entityData;
        }
    }

    /**
     * @param array $result
     *
     * @return bool
     * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
     */
    public function shouldProcess(array $result): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function addStoreFilter(\Zend_Db_Select $select): void
    {
        $storeId = $this->store->getRootCategoryId();
        $select->where('path like ?', '%/'.$storeId.'%');
    }
}
