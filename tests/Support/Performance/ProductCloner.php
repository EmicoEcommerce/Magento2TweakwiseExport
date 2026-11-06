<?php

declare(strict_types=1);

namespace Tweakwise\Test\Support\Performance;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class ProductCloner
{
    public const SKU_PREFIX = 'perf-';

    private const ENTITY_TABLE = 'catalog_product_entity';

    // No foreign key to the product table: not found automatically and not removed by a cascading delete.
    private const INDEX_TABLES = [
        'cataloginventory_stock_status' => 'product_id',
        'catalog_product_index_price' => 'entity_id',
        'catalog_category_product_index_store1' => 'product_id',
    ];

    // Cloning these would add the cloned products to the original bundle product.
    private const SKIPPED_TABLES = [
        'catalog_product_bundle_option',
        'catalog_product_bundle_selection',
        'catalog_product_bundle_price_index',
    ];

    private AdapterInterface $connection;

    private int $copies = 0;

    /** @var array<string, string[]>|null */
    private ?array $productTables = null;

    public function __construct(private readonly ResourceConnection $resource)
    {
        $this->connection = $resource->getConnection();
    }

    public function addCatalogCopies(int $count): void
    {
        $baseMax = $this->highestOriginalProductId();

        for ($i = 0; $i < $count; $i++) {
            $prefix = self::SKU_PREFIX . (++$this->copies) . '-';

            $this->cloneProducts($prefix, $baseMax);
            foreach ($this->getProductTables() as $table => $columns) {
                $this->cloneRows($prefix, $baseMax, $table, $columns);
            }

            $this->cloneSourceItems($prefix, $baseMax);
        }
    }

    public function removeAll(): void
    {
        $entity = $this->table(self::ENTITY_TABLE);
        $this->connection->delete($entity, ['sku LIKE ?' => self::SKU_PREFIX . '%']);

        foreach (self::INDEX_TABLES as $table => $column) {
            $table = $this->table($table);
            $this->connection->query(
                sprintf(
                    'DELETE t FROM `%s` t LEFT JOIN `%s` e ON e.entity_id = t.`%s` WHERE e.entity_id IS NULL',
                    $table,
                    $entity,
                    $column
                )
            );
        }

        // Source items are linked by sku and have no foreign key to the product table.
        $this->connection->delete(
            $this->table('inventory_source_item'),
            ['sku LIKE ?' => self::SKU_PREFIX . '%']
        );

        $this->copies = 0;
    }

    private function cloneProducts(string $prefix, int $baseMax): void
    {
        $entity = $this->table(self::ENTITY_TABLE);
        $columns = $this->columns($entity);

        $select = array_map(
            static fn(string $column): string => $column === 'sku' ? 'CONCAT(:prefix, t.sku)' : 't.`' . $column . '`',
            $columns
        );

        $this->connection->query(
            sprintf(
                "INSERT INTO `%s` (%s) SELECT %s FROM `%s` t WHERE t.entity_id <= :baseMax AND t.type_id <> 'bundle'",
                $entity,
                $this->quoteList($columns),
                implode(', ', $select),
                $entity
            ),
            ['prefix' => $prefix, 'baseMax' => $baseMax]
        );
    }

    /** @param string[] $productColumns columns that hold a product id and must point to the cloned product */
    private function cloneRows(string $prefix, int $baseMax, string $table, array $productColumns): void
    {
        $entity = $this->table(self::ENTITY_TABLE);
        $columns = $this->columns($table);

        $joins = [];
        $mapped = [];
        foreach ($productColumns as $i => $column) {
            $joins[] = sprintf('JOIN `%s` s%d ON s%d.entity_id = t.`%s`', $entity, $i, $i, $column);
            $joins[] = sprintf('JOIN `%s` d%d ON d%d.sku = CONCAT(:prefix, s%d.sku)', $entity, $i, $i, $i);
            $mapped[$column] = 'd' . $i . '.entity_id';
        }

        $select = array_map(
            static fn(string $column): string => $mapped[$column] ?? 't.`' . $column . '`',
            $columns
        );

        $this->connection->query(
            sprintf(
                'INSERT INTO `%s` (%s) SELECT %s FROM `%s` t %s WHERE t.`%s` <= :baseMax',
                $table,
                $this->quoteList($columns),
                implode(', ', $select),
                $table,
                implode(' ', $joins),
                $productColumns[0]
            ),
            ['prefix' => $prefix, 'baseMax' => $baseMax]
        );
    }

    private function cloneSourceItems(string $prefix, int $baseMax): void
    {
        $entity = $this->table(self::ENTITY_TABLE);
        $table = $this->table('inventory_source_item');
        $columns = $this->columns($table);

        $select = array_map(
            static fn(string $column): string => $column === 'sku' ? 'CONCAT(:prefix, t.sku)' : 't.`' . $column . '`',
            $columns
        );

        $this->connection->query(
            sprintf(
                'INSERT INTO `%s` (%s) SELECT %s FROM `%s` t JOIN `%s` s ON s.sku = t.sku '
                . 'JOIN `%s` d ON d.sku = CONCAT(:prefix, s.sku) WHERE s.entity_id <= :baseMax',
                $table,
                $this->quoteList($columns),
                implode(', ', $select),
                $table,
                $entity,
                $entity
            ),
            ['prefix' => $prefix, 'baseMax' => $baseMax]
        );
    }

    /**
     * Every table with a foreign key to the product table, plus the index tables.
     *
     * @return array<string, string[]> table => columns that hold a product id
     */
    private function getProductTables(): array
    {
        if ($this->productTables !== null) {
            return $this->productTables;
        }

        $rows = $this->connection->fetchAll(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = :entity '
            . 'AND REFERENCED_COLUMN_NAME = :column ORDER BY TABLE_NAME, ORDINAL_POSITION',
            ['entity' => $this->table(self::ENTITY_TABLE), 'column' => 'entity_id']
        );

        $skipped = array_map(fn(string $table): string => $this->table($table), self::SKIPPED_TABLES);

        $tables = [];
        foreach ($rows as $row) {
            if (in_array($row['TABLE_NAME'], $skipped, true)) {
                continue;
            }

            $tables[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
        }

        foreach (self::INDEX_TABLES as $table => $column) {
            $tables[$this->table($table)] = [$column];
        }

        $this->productTables = array_map(
            static fn(array $columns): array => array_values(array_unique($columns)),
            $tables
        );

        return $this->productTables;
    }

    // Cloned products always get a higher id than the original ones, so this separates the two.
    private function highestOriginalProductId(): int
    {
        return (int)$this->connection->fetchOne(
            $this->connection->select()
                ->from($this->table(self::ENTITY_TABLE), ['MAX(entity_id)'])
                ->where('sku NOT LIKE ?', self::SKU_PREFIX . '%')
        );
    }

    /** @return string[] column names without the auto increment column */
    private function columns(string $table): array
    {
        $columns = [];
        foreach ($this->connection->describeTable($table) as $name => $definition) {
            if (!empty($definition['IDENTITY'])) {
                continue;
            }

            $columns[] = $name;
        }

        return $columns;
    }

    /** @param string[] $columns */
    private function quoteList(array $columns): string
    {
        return implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
