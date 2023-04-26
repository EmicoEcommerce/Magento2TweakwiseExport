<?php
/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\App\Response;

use Magento\Store\Api\Data\StoreInterface;
use Tweakwise\Magento2TweakwiseExport\Model\Export;
use Tweakwise\Magento2TweakwiseExport\Model\Logger;
use Magento\Store\Model\StoreManager;
use Exception;

/**
 * Class FeedContent
 *
 * To string wrapper so output is not stored in memory but written to output on get content
 *
 * @package Tweakwise\Magento2TweakwiseExport\App\Response
 */
class FeedContent
{
    /**
     * @var Export
     */
    protected $export;

    /**
     * @var Logger
     */
    protected $log;

    protected $type;

    protected $store;
    /**
     * SomeFeedResponse constructor.
     *
     * @param Export $export
     * @param Logger $log
     */
    public function __construct(Export $export, Logger $log, StoreInterface $store = null, $type = null)
    {
        $this->export = $export;
        $this->log = $log;
        $this->type = $type;
        $this->store = $store;
    }

    /**
     * Also renders feed to output stream
     *
     * @return string
     */
    public function __toString()
    {
        $resource = fopen('php://output', 'wb');
        try {
            try {
                $this->export->getFeed($resource, $this->store, $this->type);
            } catch (Exception $e) {
                $this->log->error(sprintf('Failed to get feed due to %s', $e->getMessage()));
            }
        } finally {
            fclose($resource);
        }

        return '';
    }
}
