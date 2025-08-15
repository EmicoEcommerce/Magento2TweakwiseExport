<?php

/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model\Write;

use DateTime;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Profiler;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManager;
use Magento\Framework\Composer\ComposerInformation;

class Writer
{
    /**
     * @var XMLWriter
     */
    protected $xml;

    /**
     * Resource where XML is written to after each flush
     *
     * @var Resource
     */
    protected $resource;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * @var AppState
     */
    protected $appState;

    /**
     * @var WriterInterface[]
     */
    protected $writers;

    /**
     * @var DateTime
     */
    protected $now;

    /**
     * @var ComposerInformation
     */
    protected $composerInformation;

    /**
     * @var File
     */
    private File $driver;

    /**
     * Writer constructor.
     *
     * @param StoreManager $storeManager
     * @param AppState $appState
     * @param ComposerInformation $composerInformation
     * @param WriterInterface[] $writers
     * @param File $driver
     */
    public function __construct(
        StoreManager $storeManager,
        AppState $appState,
        ComposerInformation $composerInformation,
        $writers,
        File $driver
    ) {
        $this->storeManager = $storeManager;
        $this->appState = $appState;
        $this->writers = $writers;
        $this->composerInformation = $composerInformation;
        $this->driver = $driver;
    }

    /**
     * @return DateTime
     */
    public function getNow(): DateTime
    {
        if (!$this->now) {
            $this->now = new DateTime();
        }

        return $this->now;
    }

    /**
     * @param DateTime $now
     */
    public function setNow(DateTime $now): void
    {
        $this->now = $now;
    }

    /**
     * @param WriterInterface[] $writers
     * @return void
     */
    public function setWriters($writers): void
    {
        $this->writers = [];
        foreach ($writers as $writer) {
            $this->addWriter($writer);
        }
    }

    /**
     * @param WriterInterface $writer
     * @return void
     */
    public function addWriter(WriterInterface $writer): void
    {
        $this->writers[] = $writer;
    }

    /**
     * @param resource $resource
     * @param null|StoreInterface $store
     */
    public function write($resource, ?StoreInterface $store = null, ?string $type = null): void
    {
        try {
            Profiler::start('write');
            $this->resource = $resource;

            $this->startDocumentType($store, $type);
            $xml = $this->getXml();

            $this->determineWriters($type);

            foreach ($this->writers as $writer) {
                $writer->write($this, $xml, $store);
            }

            $this->endDocument();
        } finally {
            $this->close();
            Profiler::stop('write');
        }
    }

    /**
     * @return XMLWriter
     */
    protected function getXml(): XMLWriter
    {
        if (!$this->xml) {
            $xml = new XMLWriter();
            $xml->openMemory();
            if ($this->appState->getMode() === AppState::MODE_DEVELOPER) {
                $xml->setIndent(true);
                $xml->setIndentString('    ');
            } else {
                $xml->setIndent(false);
            }

            $this->xml = $xml;
        }

        return $this->xml;
    }

    /**
     * Close xml and writer references
     */
    protected function close(): void
    {
        $this->xml = null;
        $this->resource = null;
    }

    /**
     * Close XML writer
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Flush current content of writer to resource
     */
    public function flush(): void
    {
        $output = $this->getXml()->flush();
        if ($output) {
            $this->driver->fileWrite($this->resource, $output);
        }
    }

    protected function startDocumentType(?StoreInterface $store = null, ?string $type = null)
    {
        if ($type === 'stock' || $type === 'price') {
            $this->startExternalDocument();
        } else {
            $this->startDocument();
        }
    }

    /**
     * Write document start
     */
    protected function startDocument(?StoreInterface $store = null): void
    {
        if ($store === null) {
            $store = $this->storeManager->getDefaultStoreView();
        }

        $xml = $this->getXml();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('tweakwise'); // Start root
        $xml->writeElement('shop', $store->getName());
        $xml->writeElement('timestamp', $this->getNow()->format('Y-m-d\TH:i:s.uP'));
        $xml->writeElement('generatedby', $this->getModuleVersion());
        $this->flush();
    }

    /**
     * Write document start
     */
    protected function startExternalDocument(): void
    {
        $xml = $this->getXml();
        $xml->startDocument('1.0', 'UTF-8');
        $this->flush();
    }

    /**
     * @return string
     */
    protected function getModuleVersion(): string
    {
        $installedPackages = $this->composerInformation
            ->getInstalledMagentoPackages();
        if (!isset($installedPackages['tweakwise/magento2-tweakwise-export']['version'])) {
            // This should never be the case
            return '';
        }

        $version = $installedPackages['tweakwise/magento2-tweakwise-export']['version'];

        return sprintf('Magento2TweakwiseExport %s', $version);
    }

    /**
     * Write document end
     */
    protected function endDocument(): void
    {
        $xml = $this->getXml();
        $xml->endElement(); // </tweakwise>
        $xml->endDocument();
        $this->flush();
    }

    protected function determineWriters($type = null): void
    {
        if ($type === null) {
            unset($this->writers['stock']);
            unset($this->writers['price']);
        } else {
            foreach ($this->writers as $key => $value) {
                if ($type !== $key) {
                    unset($this->writers[$key]);
                }
            }
        }
    }
}
