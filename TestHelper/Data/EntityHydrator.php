<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Tweakwise\Magento2TweakwiseExport\TestHelper\Data;

use ReflectionClass;
use ReflectionException;
use Zend\Filter\Exception\InvalidArgumentException;
use Zend\Filter\FilterChain;
use Zend\Filter\FilterInterface;
use Zend\Filter\Word\UnderscoreToCamelCase;

class EntityHydrator
{
    /**
     * @var string[]
     */
    protected $methodCache;

    /**
     * @var ReflectionClass
     */
    protected $reflectionCache;

    /**
     * @var FilterInterface
     */
    protected $fieldToMethodFilter; // @phpstan-ignore-line

    /**
     * @param array $data
     * @param object $object
     * @return object
     * @throws ReflectionException
     */
    public function hydrate(array $data, $object)
    {
        if (method_exists($object, 'addData')) {
            $object->addData($data);
            return $object;
        }

        $class = \get_class($object);
        foreach ($data as $field => $value) {
            $method = $this->getSetMethod($class, $field);
            if (!$method) {
                continue;
            }

            $object->$method($value);
        }

        return $object;
    }

    /**
     * @return FilterInterface
     * @throws InvalidArgumentException
     */
    protected function getFieldToMethodFilter(): FilterInterface // @phpstan-ignore-line
    {
        if ($this->fieldToMethodFilter === null) {
            // @phpstan-ignore-next-line
            $filter = new FilterChain();
            // @phpstan-ignore-next-line
            $filter->attach(new UnderscoreToCamelCase());

            // @phpstan-ignore-next-line
            $this->fieldToMethodFilter = $filter;
        }

        // @phpstan-ignore-next-line
        return $this->fieldToMethodFilter;
    }

    /**
     * @param string $class
     * @return ReflectionClass
     * @throws ReflectionException
     */
    protected function getReflectionClass(string $class): ReflectionClass
    {
        // @phpstan-ignore-next-line
        if (!isset($this->reflectionCache[$class])) {
            // @phpstan-ignore-next-line
            $this->reflectionCache[$class] = new ReflectionClass($class);
        }

        return $this->reflectionCache[$class];
    }

    /**
     * @param string $class
     * @param string $field
     * @return string|false
     * @throws ReflectionException
     */
    protected function getSetMethod(string $class, string $field)
    {
        $key = $class . $field;
        if (!isset($this->methodCache[$key])) {
            // @phpstan-ignore-next-line
            $method = 'set' . $this->getFieldToMethodFilter()->filter($field);
            $reflection = $this->getReflectionClass($class);

            // @phpstan-ignore-next-line
            $this->methodCache[$key] = $reflection->hasMethod($method) ? $method : false;
        }

        return $this->methodCache[$key];
    }
}
