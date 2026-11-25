<?php

declare(strict_types=1);

namespace Tabula17\Satelles\Odf\Template;

use Tabula17\Satelles\Odf\DataRendererInterface;
use Tabula17\Satelles\Odf\Exception\FileException;
use Tabula17\Satelles\Odf\Exception\FileNotFoundException;
use Tabula17\Satelles\Odf\Exception\RelatioRuntimeException;
use Tabula17\Satelles\Odf\Exception\XmlProcessException;
use Tabula17\Satelles\Odf\Exporter\ExportToFile;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\OdfContainerInterface;
use Tabula17\Satelles\Odf\OdfProcessor;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;
use Tabula17\Satelles\Odf\XmlMemberPath;
use Tabula17\Satelles\Odf\XmlProcessorInterface;
use Tabula17\Satelles\Xml\XmlPart;
use Throwable;
use ZipArchive;

/**
 * Cacheable, immutable representation of an ODF template.
 *
 * The template file and its XML parts are parsed exactly once. Each call to
 * {@see self::createProcessor()} produces an independent {@see OdfProcessor}
 * with cloned XML parts, so rendering jobs never mutate the cached structure
 * and can safely run in different coroutines/workers.
 */
class OdfTemplate
{
    private readonly string $filePath;
    private readonly string $baseDir;
    private readonly OdfContainerInterface $fileContainer;
    private readonly DataRendererInterface $renderer;
    private readonly XmlProcessorInterface $xmlProcessor;

    /** @var array<string, string> Raw XML source of each parsed part. */
    private array $partSources = [];

    /** @var array<string, XmlPart> Parsed template parts (clone sources). */
    private array $parts = [];

    private bool $isLoaded = false;

    /**
     * @param string $filePath Path to the ODF template file.
     * @param string $baseDir Base directory used for temporary job directories.
     * @param OdfContainerInterface|null $fileContainer Prototype container; a fresh one is cloned per job.
     * @param DataRendererInterface|null $renderer Renderer prototype; shared across jobs (stateless).
     * @param XmlProcessorInterface|null $xmlProcessor XML processor prototype; cloned per job.
     * @throws FileNotFoundException
     * @throws FileException
     */
    public function __construct(
        string                  $filePath,
        string                  $baseDir,
        ?OdfContainerInterface  $fileContainer = null,
        ?DataRendererInterface  $renderer = null,
        ?XmlProcessorInterface  $xmlProcessor = null
    )
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException(sprintf(FileNotFoundException::FILE_NOT_FOUND, $filePath));
        }

        if (!is_readable($filePath)) {
            throw new FileException(sprintf(FileException::CANT_READ, $filePath));
        }

        $this->filePath = $filePath;
        $this->baseDir = $baseDir;
        $this->fileContainer = $fileContainer ?? new OdfContainer(new ZipArchive());
        $this->renderer = $renderer ?? new DataRenderer([], null);
        $this->xmlProcessor = $xmlProcessor ?? new XmlProcessor($this->renderer, $this->fileContainer);
    }

    /**
     * Eagerly parses and caches the template parts. Called automatically by
     * {@see self::createProcessor()}; exposed for warm-up in long-running workers.
     *
     * @return $this
     * @throws RelatioRuntimeException
     * @throws XmlProcessException
     */
    public function load(): self
    {
        if ($this->isLoaded) {
            return $this;
        }

        $zip = new ZipArchive();
        $result = $zip->open($this->filePath);
        if ($result !== true) {
            throw new RelatioRuntimeException(
                sprintf(RelatioRuntimeException::FAILED_TO_LOAD, $this->filePath)
            );
        }

        try {
            foreach (self::templateParts() as $part) {
                $path = $part->value;
                $source = $zip->getFromName($path);
                if ($source === false) {
                    throw new XmlProcessException(
                        sprintf(XmlProcessException::EMPTY_PARSE_ERROR, $path)
                    );
                }
                $this->partSources[$part->name()] = $source;
                $this->parts[$part->name()] = new XmlPart($source);
            }
        } catch (Throwable $e) {
            throw new RelatioRuntimeException(
                sprintf(RelatioRuntimeException::ACTION_ERROR, 'parsing', $this->filePath) . ': ' . $e->getMessage(),
                0,
                $e
            );
        } finally {
            $zip->close();
        }

        $this->isLoaded = true;
        return $this;
    }

    /**
     * Creates an isolated processor whose XML parts are cloned from the cache.
     * The original template file is copied into a fresh working directory for
     * the job, mirroring {@see OdfProcessor::loadFile()}.
     *
     * @return OdfProcessor
     * @throws RelatioRuntimeException
     */
    public function createProcessor(): OdfProcessor
    {
        $this->load();

        $processor = new OdfProcessor(
            $this->filePath,
            $this->baseDir,
            clone $this->fileContainer,
            $this->renderer,
            $this->xmlProcessor
        );

        // Inject the cached (cloned) XML parts so the processor does not need
        // to unzip and re-parse the template for this job.
        $processor->useCachedParts($this->cloneParts(), $this->partSources);

        return $processor;
    }

    /**
     * Processes each data set into its own compiled output.
     *
     * @param iterable<array> $dataSets
     * @param callable(int, OdfProcessor): string $outputPath Resolves the destination file for each job.
     * @return array<int, array> One result entry per job (see {@see OdfProcessor::getResult()}).
     * @throws RelatioRuntimeException
     */
    public function processAll(iterable $dataSets, callable $outputPath): array
    {
        $results = [];
        foreach ($dataSets as $index => $data) {
            $processor = $this->createProcessor();
            try {
                $processor->process($data)->compile();
                $destination = $outputPath($index, $processor);
                $processor->exportTo(new ExportToFile(
                    dirname($destination),
                    basename($destination)
                ));
                $results[] = $processor->getResult();
            } finally {
                $processor->cleanUpWorkingDir();
            }
        }

        return $results;
    }

    public function isLoaded(): bool
    {
        return $this->isLoaded;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return array<string, XmlPart>
     */
    private function cloneParts(): array
    {
        $clones = [];
        foreach ($this->parts as $name => $part) {
            // XmlPart treats any pending libxml error as fatal, and XPath
            // queries from previous jobs may have left warnings behind.
            libxml_clear_errors();
            $clones[$name] = new XmlPart($part->asXML());
        }
        return $clones;
    }

    /**
     * Parts that are read/processed by the template engine and must be seeded
     * into each job container. Kept in sync with {@see XmlMemberPath}.
     *
     * @return array<int, XmlMemberPath>
     */
    private static function templateParts(): array
    {
        return [
            XmlMemberPath::CONTENT,
            XmlMemberPath::STYLES,
            XmlMemberPath::MANIFEST,
        ];
    }

    public function setRendererFunctionsDirectory(string $dir): void
    {
        if (isset($this->renderer->functions)) {
            $this->renderer->functions->workingDir = $dir;
        }
    }
}
