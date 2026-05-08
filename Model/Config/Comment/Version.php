<?php

declare(strict_types=1);

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model\Config\Comment;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\Composer\ComposerInformation;

class Version implements CommentInterface
{
    public function __construct(private readonly ComposerInformation $composerInformation)
    {
    }

    public function getCommentText($elementValue): string
    {
        $installedPackages = $this->composerInformation->getInstalledMagentoPackages();

        if (!isset($installedPackages['tweakwise/magento2-tweakwise-export']['version'])) {
            return '';
        }

        return sprintf('Tweakwise Export version %s', $installedPackages['tweakwise/magento2-tweakwise-export']['version']);
    }
}
