<?php

namespace Tweakwise\Magento2TweakwiseExport\Model;

use Magento\Framework\App\Cache\Manager;

/**
 * Class CacheHandler flush caches after tweakwise publish task
 */
class CacheHandler
{
    /**
     * @var Manager Magento native cache handler
     */
    protected $manager;

    /**
     * @var array
     */
    protected $cacheTypes = [];

    /**
     * CacheHandler constructor.
     *
     * @param Manager $manager
     * @param array $cacheTypes
     */
    public function __construct(Manager $manager, array $cacheTypes = [])
    {
        $this->manager = $manager;
        $this->cacheTypes = $cacheTypes;
    }

    /**
     * Flush Caches
     *
     * @return void
     */
    public function clear(): void
    {
        $this->manager->flush($this->cacheTypes);
    }
}
