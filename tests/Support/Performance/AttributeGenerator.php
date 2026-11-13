<?php

declare(strict_types=1);

namespace Tweakwise\Test\Support\Performance;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class AttributeGenerator
{
    public const CODE_PREFIX = 'perf_attr_';

    private const PRODUCT_ENTITY_TYPE_ID = 4;
    private const OPTIONS_PER_SELECT = 10;

    private AdapterInterface $connection;

    private int $added = 0;

    public function __construct(private readonly ResourceConnection $resource)
    {
        $this->connection = $resource->getConnection();
    }

    public function add(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $number = ++$this->added;

            match ($number % 3) {
                0 => $this->addSelect($number),
                1 => $this->addText($number),
                default => $this->addNumber($number),
            };
        }
    }

    public function removeAll(): void
    {
        $this->connection->delete(
            $this->table('eav_attribute'),
            [
                'entity_type_id = ?' => self::PRODUCT_ENTITY_TYPE_ID,
                'attribute_code LIKE ?' => self::CODE_PREFIX . '%',
            ]
        );

        $this->added = 0;
    }

    private function addSelect(int $number): void
    {
        $attributeId = $this->createAttribute($number, 'int', 'select');

        $optionTable = $this->table('eav_attribute_option');
        $optionIds = [];
        for ($i = 1; $i <= self::OPTIONS_PER_SELECT; $i++) {
            $this->connection->insert($optionTable, ['attribute_id' => $attributeId, 'sort_order' => $i]);
            $optionId = (int)$this->connection->lastInsertId($optionTable);

            $this->connection->insert(
                $this->table('eav_attribute_option_value'),
                ['option_id' => $optionId, 'store_id' => 0, 'value' => 'Option ' . $number . '-' . $i]
            );

            $optionIds[] = $optionId;
        }

        $this->assignValues(
            'catalog_product_entity_int',
            $attributeId,
            'ELT(1 + MOD(e.entity_id, ' . self::OPTIONS_PER_SELECT . '), ' . implode(', ', $optionIds) . ')'
        );
    }

    private function addText(int $number): void
    {
        $attributeId = $this->createAttribute($number, 'varchar', 'text');

        $this->assignValues('catalog_product_entity_varchar', $attributeId, "CONCAT('text ', MOD(e.entity_id, 25))");
    }

    private function addNumber(int $number): void
    {
        $attributeId = $this->createAttribute($number, 'decimal', 'text');

        $this->assignValues('catalog_product_entity_decimal', $attributeId, 'MOD(e.entity_id, 100) + 0.5');
    }

    private function createAttribute(int $number, string $backendType, string $frontendInput): int
    {
        $code = self::CODE_PREFIX . $number;
        $table = $this->table('eav_attribute');

        $this->connection->insert($table, [
            'entity_type_id' => self::PRODUCT_ENTITY_TYPE_ID,
            'attribute_code' => $code,
            'backend_type' => $backendType,
            'frontend_input' => $frontendInput,
            'frontend_label' => $code,
            'is_required' => 0,
            'is_user_defined' => 1,
            'is_unique' => 0,
        ]);
        $attributeId = (int)$this->connection->lastInsertId($table);

        $this->connection->insert($this->table('catalog_eav_attribute'), [
            'attribute_id' => $attributeId,
            'is_global' => 1,
            'is_visible' => 1,
            'is_filterable' => 1,
            'used_in_product_listing' => 1,
        ]);

        return $attributeId;
    }

    private function assignValues(string $table, int $attributeId, string $valueExpression): void
    {
        $this->connection->query(
            sprintf(
                'INSERT INTO `%s` (attribute_id, store_id, entity_id, value) SELECT %d, 0, e.entity_id, %s FROM `%s` e',
                $this->table($table),
                $attributeId,
                $valueExpression,
                $this->table('catalog_product_entity')
            )
        );
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
