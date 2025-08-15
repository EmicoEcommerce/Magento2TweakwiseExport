<?php

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model;

use Tweakwise\Magento2TweakwiseExport\Cron\Export;
use Exception;
use InvalidArgumentException;
use Magento\Cron\Model\ResourceModel\Schedule\Collection;
use Magento\Cron\Model\Schedule;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Most of this class came from https://github.com/netz98/n98-magerun2/:
 * - N98/Magento/Command/System/Cron/AbstractCronCommand.php
 * - N98/Magento/Command/System/Cron/ScheduleCommand.php
 */
class Scheduler
{
    /**
     * @var Collection
     */
    protected $scheduleCollection;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @param Collection $scheduleCollection
     * @param ProductMetadataInterface $productMetadata
     * @param DateTime $dateTime
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        Collection $scheduleCollection,
        ProductMetadataInterface $productMetadata,
        DateTime $dateTime,
        TimezoneInterface $timezone
    ) {
        $this->scheduleCollection = $scheduleCollection;
        $this->productMetadata = $productMetadata;
        $this->dateTime = $dateTime;
        $this->timezone = $timezone;
    }

    /**
     * Schedule new export
     *
     * @param string|null $type
     *
     * @return Schedule
     * @throws Exception
     */
    public function schedule(?string $type = null): Schedule
    {
        $job = 'tweakwise_magento2_tweakwise_export';

        if (!empty($type)) {
            if ($type == 'stock') {
                $job = 'tweakwise_magento2_tweakwise_export_stock';
            }

            if ($type == 'price') {
                $job = 'tweakwise_magento2_tweakwise_export_price';
            }
        }

        $createdAtTime = $this->getCronTimestamp();
        $scheduledAtTime = $createdAtTime;

        /* @var $schedule Schedule */
        $schedule = $this->scheduleCollection->getNewEmptyItem();
        $schedule->setJobCode($job)
            ->setStatus(Schedule::STATUS_PENDING)
            ->setCreatedAt(date('Y-m-d H:i:s', $createdAtTime))
            ->setScheduledAt(date('Y-m-d H:i', $scheduledAtTime));

        $schedule->save();

        return $schedule;
    }

    /**
     * Get timestamp used for time related database fields in the cron tables
     *
     * Note: The timestamp used will change from Magento 2.1.7 to 2.2.0 and
     *       these changes are branched by Magento version in this method.
     *
     * @return int
     */
    protected function getCronTimestamp(): int
    {
        /* @var $version string e.g. "2.1.7" */
        $version = $this->productMetadata->getVersion();
        if (version_compare($version, '2.2.0') >= 0) {
            return $this->dateTime->gmtTimestamp();
        }

        return $this->timezone->scopeTimeStamp();
    }
}
