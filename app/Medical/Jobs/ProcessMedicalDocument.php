<?php

namespace App\Medical\Jobs;

use App\Medical\Enums\DocumentStatus;
use App\Medical\Enums\MetadataState;
use App\Medical\Metadata\MetadataManager;
use App\Medical\Models\MedicalDocument;
use App\Medical\Ocr\OcrException;
use App\Medical\Ocr\OcrManager;
use App\Medical\Ocr\OcrPage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads a document and files what it says.
 *
 * Failing here never loses the scan: the document keeps its file and gets a
 * status the UI can retry, because a blurry photo of a carbon-copy referral is
 * normal rather than exceptional.
 */
class ProcessMedicalDocument implements ShouldQueue
{
    use Queueable;

    /**
     * How many times the queue may try before giving up.
     */
    public int $tries = 3;

    /**
     * Back off further on each attempt: an OCR outage should not be hammered.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120, 600];

    /**
     * Give the engine room to answer on a large scan.
     */
    public int $timeout = 300;

    public function __construct(public readonly int $documentId) {}

    /**
     * Read the document, then let the metadata extractor suggest its details.
     */
    public function handle(OcrManager $ocr, MetadataManager $metadata): void
    {
        $document = MedicalDocument::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        $document->forceFill([
            'status' => DocumentStatus::Processing,
            'ocr_error' => null,
        ])->save();

        try {
            $result = $ocr->driver()->extract($document);
        } catch (OcrException $exception) {
            $this->fail($document, $exception->getMessage());

            return;
        }

        $this->storePages($document, $result->pages, $result->model);

        $this->suggestMetadata($document, $metadata);

        $document->forceFill([
            'status' => DocumentStatus::Ready,
            'ocr_error' => null,
            'ocr_model' => $result->model,
            'ocr_pages' => $result->pageCount(),
        ])->save();
    }

    /**
     * Three attempts at a real outage, then leave it failed for a human.
     */
    public function failed(?Throwable $exception): void
    {
        $document = MedicalDocument::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        $this->fail($document, $exception?->getMessage() ?? __('The OCR service did not answer.'));
    }

    /**
     * Replace the document's pages with what this run produced.
     *
     * @param  list<OcrPage>  $pages
     */
    private function storePages(MedicalDocument $document, array $pages, ?string $model): void
    {
        $document->pages()->delete();

        foreach ($pages as $page) {
            $document->pages()->create([
                'page_number' => $page->number,
                'markdown' => $page->markdown,
                'blocks' => $page->blocks,
            ]);
        }
    }

    /**
     * Ask the extractor what the document is, storing its answer as a proposal.
     *
     * Nothing here may fail the document: metadata is a convenience, and a
     * document with readable text and no suggestions is still useful.
     */
    private function suggestMetadata(MedicalDocument $document, MetadataManager $metadata): void
    {
        try {
            $suggestion = $metadata->driver()->extract($document->fresh()->fullText());
        } catch (Throwable $exception) {
            Log::warning('Medical metadata extraction threw.', [
                'document' => $document->id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if ($suggestion === null || ! $suggestion->isMeaningful()) {
            return;
        }

        $document->forceFill([
            'metadata' => $suggestion->toArray(),
            'metadata_state' => MetadataState::Suggested,
        ])->save();
    }

    /**
     * Record why reading failed, leaving the file in place.
     */
    private function fail(MedicalDocument $document, string $message): void
    {
        $document->forceFill([
            'status' => DocumentStatus::Failed,
            'ocr_error' => mb_substr($message, 0, 1000),
        ])->save();
    }
}
