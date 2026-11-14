<?php

namespace Tests\Support;

use App\Medical\Models\MedicalDocument;
use App\Medical\Ocr\OcrEngine;
use App\Medical\Ocr\OcrException;
use App\Medical\Ocr\OcrPage;
use App\Medical\Ocr\OcrResult;

/**
 * An OCR engine that answers with text a test decided on, so no test ever
 * reaches the network and every assertion is about our own code.
 */
class FakeOcrEngine implements OcrEngine
{
    /**
     * The pages the next call should return, keyed by document id.
     *
     * @var array<int, list<OcrPage>>
     */
    private array $pages = [];

    /**
     * Whether the next call should throw.
     */
    private bool $shouldFail = false;

    /**
     * The reason the next call should fail.
     */
    private string $failureReason = 'The OCR service is unavailable.';

    /**
     * How many documents the engine was asked to read.
     */
    public int $calls = 0;

    /**
     * Answer with these pages for this document.
     *
     * @param  list<OcrPage>  $pages
     */
    public function willReturn(MedicalDocument $document, array $pages): static
    {
        $this->pages[$document->id] = $pages;

        return $this;
    }

    /**
     * Answer with one page of text for this document.
     */
    public function willReturnText(MedicalDocument $document, string $markdown): static
    {
        return $this->willReturn($document, [new OcrPage(number: 1, markdown: $markdown)]);
    }

    /**
     * Fail the next call, as a real engine would when the vendor is down.
     */
    public function willFail(string $reason = 'The OCR service is unavailable.'): static
    {
        $this->shouldFail = true;
        $this->failureReason = $reason;

        return $this;
    }

    /**
     * Read a document.
     */
    public function extract(MedicalDocument $document): OcrResult
    {
        $this->calls++;

        if ($this->shouldFail) {
            throw OcrException::requestFailed($this->failureReason);
        }

        return new OcrResult(
            pages: $this->pages[$document->id] ?? [new OcrPage(number: 1, markdown: '')],
            model: 'fake-ocr',
        );
    }
}
