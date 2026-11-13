<?php

declare(strict_types=1);

namespace Tweakwise\Test\Support\Performance;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class WebsiteGenerator
{
    public const CODE_PREFIX = 'perf_website_';

    private const SOURCE_WEBSITE_ID = 1;
    private const SOURCE_STORE_ID = 1;
    private const ROOT_CATEGORY_ID = 2;
    private const STOCK_ID = 1;
    private const CATEGORY_INDEX_TABLE = 'catalog_category_product_index_store';

    private AdapterInterface $connection;

    private int $added = 0;

    public function __construct(private readonly ResourceConnection $resource)
    {
        $this->connection = $resource->getConnection();
    }

    public function add(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createWebsite(++$this->added);
        }
    }

    public function removeAll(): void
    {
        $websiteTable = $this->table('store_website');
        $websiteIds = $this->connection->fetchCol(
            $this->connection->select()
                ->from($websiteTable, ['website_id'])
                ->where('code LIKE ?', self::CODE_PREFIX . '%')
        );

        if ($websiteIds !== []) {
            $storeIds = $this->connection->fetchCol(
                $this->connection->select()
                    ->from($this->table('store'), ['store_id'])
                    ->where('website_id IN (?)', $websiteIds)
            );

            foreach ($storeIds as $storeId) {
                $this->connection->dropTable($this->table(self::CATEGORY_INDEX_TABLE . $storeId));
            }

            $this->connection->delete(
                $this->table('catalog_product_index_price'),
                ['website_id IN (?)' => $websiteIds]
            );
        }

        $this->connection->delete(
            $this->table('inventory_stock_sales_channel'),
            ['type = ?' => 'website', 'code LIKE ?' => self::CODE_PREFIX . '%']
        );
        $this->connection->delete($websiteTable, ['code LIKE ?' => self::CODE_PREFIX . '%']);

        $this->added = 0;
    }

    private function createWebsite(int $number): void
    {
        $code = self::CODE_PREFIX . $number;
        $websiteTable = $this->table('store_website');
        $groupTable = $this->table('store_group');
        $storeTable = $this->table('store');

        $this->connection->insert($websiteTable, [
            'code' => $code,
            'name' => 'Performance website ' . $number,
            'sort_order' => $number,
            'default_group_id' => 0,
            'is_default' => 0,
        ]);
        $websiteId = (int)$this->connection->lastInsertId($websiteTable);

        $this->connection->insert($groupTable, [
            'website_id' => $websiteId,
            'name' => 'Performance group ' . $number,
            'root_category_id' => self::ROOT_CATEGORY_ID,
            'default_store_id' => 0,
            'code' => $code,
        ]);
        $groupId = (int)$this->connection->lastInsertId($groupTable);

        $this->connection->insert($storeTable, [
            'code' => $code,
            'website_id' => $websiteId,
            'group_id' => $groupId,
            'name' => 'Performance store view ' . $number,
            'sort_order' => $number,
            'is_active' => 1,
        ]);
        $storeId = (int)$this->connection->lastInsertId($storeTable);

        $this->connection->update($groupTable, ['default_store_id' => $storeId], ['group_id = ?' => $groupId]);
        $this->connection->update($websiteTable, ['default_group_id' => $groupId], ['website_id = ?' => $websiteId]);
        $this->connection->insert(
            $this->table('inventory_stock_sales_channel'),
            ['type' => 'website', 'code' => $code, 'stock_id' => self::STOCK_ID]
        );

        $this->copyWebsiteRows('catalog_product_website', ['website_id' => $websiteId]);
        $this->copyWebsiteRows('catalog_product_index_price', ['website_id' => $websiteId]);
        $this->copyWebsiteRows(
            'catalog_product_index_website',
            ['website_id' => $websiteId, 'default_store_id' => $storeId]
        );
        $this->cloneCategoryIndex($storeId);
    }

    /** @param array<string, int> $replacements */
    private function copyWebsiteRows(string $table, array $replacements): void
    {
        $table = $this->table($table);
        $columns = $this->columns($table);

        $select = array_map(
            static fn(string $column): string => isset($replacements[$column])
                ? (string)$replacements[$column]
                : '`' . $column . '`',
            $columns
        );

        $this->connection->query(
            sprintf(
                'INSERT INTO `%s` (%s) SELECT %s FROM `%s` WHERE website_id = %d',
                $table,
                $this->quoteList($columns),
                implode(', ', $select),
                $table,
                self::SOURCE_WEBSITE_ID
            )
        );
    }

    private function cloneCategoryIndex(int $storeId): void
    {
        $source = $this->table(self::CATEGORY_INDEX_TABLE . self::SOURCE_STORE_ID);
        $target = $this->table(self::CATEGORY_INDEX_TABLE . $storeId);

        $this->connection->query(sprintf('CREATE TABLE `%s` LIKE `%s`', $target, $source));

        $columns = array_keys($this->connection->describeTable($source));
        $select = array_map(
            static fn(string $column): string => $column === 'store_id' ? (string)$storeId : '`' . $column . '`',
            $columns
        );

        $this->connection->query(
            sprintf(
                'INSERT INTO `%s` (%s) SELECT %s FROM `%s`',
                $target,
                $this->quoteList($columns),
                implode(', ', $select),
                $source
            )
        );
    }

    /** @return string[] */
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
