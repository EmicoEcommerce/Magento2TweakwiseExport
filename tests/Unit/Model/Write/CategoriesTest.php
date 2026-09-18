<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Write;

use Emico\CodeCept\Test\Unit;
use ReflectionClass;
use ReflectionMethod;
use Tweakwise\Magento2TweakwiseExport\Model\Write\Categories;

class CategoriesTest extends Unit
{
    /**
     * @param array $data
     * @return int
     */
    private function resolveParentId(array $data): int
    {
        $categories = (new ReflectionClass(Categories::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(Categories::class, 'resolveParentId');

        return $method->invoke($categories, $data);
    }

    /**
     * A partially applied category move leaves parent_id pointing at the old parent while the
     * path already points at the new one. Categories are exported in path order, so trusting
     * parent_id drops the category from the feed entirely.
     */
    public function testParentIsResolvedFromPathWhenParentIdIsStale(): void
    {
        self::assertSame(1001, $this->resolveParentId(['path' => '1/1000/1001/1223', 'parent_id' => 1220]));
    }

    public function testParentIsResolvedFromPathForHealthyCategory(): void
    {
        self::assertSame(1001, $this->resolveParentId(['path' => '1/1000/1001/1003', 'parent_id' => 1001]));
    }

    public function testStoreRootKeepsMagentoRootAsParent(): void
    {
        self::assertSame(1, $this->resolveParentId(['path' => '1/1000', 'parent_id' => 1]));
    }

    public function testFallsBackToParentIdWhenPathIsUnavailable(): void
    {
        self::assertSame(1220, $this->resolveParentId(['parent_id' => 1220]));
        self::assertSame(77, $this->resolveParentId(['path' => '', 'parent_id' => 77]));
    }
}
