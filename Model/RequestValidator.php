<?php

namespace Tweakwise\Magento2TweakwiseExport\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\StoreInterface;
use Tweakwise\Magento2TweakwiseExport\Model\Config;

class RequestValidator
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * RequestValidator constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param RequestInterface $request
     * @return bool
     */
    public function validateRequestKey(RequestInterface $request): bool
    {
        $key = $this->config->getKey();
        $requestKey = $request->getParam('key');

        return $key === $requestKey;
    }

    public function validateStoreKey(RequestInterface $request): bool
    {
        $store = $request->getParam('store');
        if (!$this->config->isStoreLevelExportEnabled() && ($store !== null)) {
            return false;
        }

        return true;
    }

    public function validateType(RequestInterface $request): bool
    {
        $type = $request->getParam('type');
        if ($type === 'stock' || $type === 'price' || empty($type)) {
            return true;
        }

        return false;
    }
}
