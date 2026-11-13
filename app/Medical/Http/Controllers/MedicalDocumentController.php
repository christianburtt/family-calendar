<?php

namespace App\Medical\Http\Controllers;

use App\Medical\Enums\DocumentCategory;
use App\Medical\Enums\DocumentStatus;
use App\Medical\Enums\MetadataState;
use App\Medical\Http\Controllers\Concerns\MedicalController;
use App\Medical\Http\Requests\StoreDocumentsRequest;
use App\Medical\Http\Requests\UpdateDocumentRequest;
use App\Medical\Jobs\ProcessMedicalDocument;
use App\Medical\Models\Activity;
use App\Medical\Models\Dosar;
use App\Medical\Models\MedicalDocument;
use App\Medical\Services\ActivityLog;
use App\Medical\Services\DocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicalDocumentController extends MedicalController
{
    /**
     * Upload one or more scans into a folder.
     *
     * The file is stored immediately and reading happens on the queue, so a slow
     * OCR service never makes an upload feel broken.
     */
    public function store(
        StoreDocumentsRequest $request,
        Dosar $dosar,
        DocumentStorage $storage,
    ): RedirectResponse {
        Gate::authorize('update', $dosar);

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files', []);

        $created = 0;
        $store = $request->string('category');
        $category = $store->isEmpty() ? null : $store->toString();

        foreach ($files as $file) {
            $document = MedicalDocument::query()->create([
                'calendar_id' => $dosar->calendar_id,
                'medical_dosar_id' => $dosar->id,
                'title' => $this->titleFromFilename($file->getClientOriginalName()),
                'category' => $category ?: 'other',
                'document_date' => $request->input('document_date'),
                'original_name' => $file->getClientOriginalName(),
                'disk' => (string) config('medical.disk'),
                'path' => '',
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'sha256' => '',
                'status' => DocumentStatus::Pending,
                'uploaded_by_id' => $request->user()->id,
            ]);

            $storage->store($document, $file);

            $document->update([
                'duplicate_of_id' => $this->findDuplicate($document)?->id,
            ]);

            $document->syncSearchText();

            ProcessMedicalDocument::dispatch($document->id);

            $created++;
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $created === 1
                ? __('File added. Reading it now.')
                : __(':count files added. Reading them now.', ['count' => $created]),
        ]);

        return back();
    }

    /**
     * Show a document, its text and what the extractor suggested.
     */
    public function show(
        Request $request,
        MedicalDocument $document,
        ActivityLog $activity,
    ): Response {
        Gate::authorize('view', $document);

        $activity->record($request, $document, Activity::ACTION_VIEW);

        $document->load(['dosar', 'uploader', 'pages']);

        return Inertia::render('medical/Documents/Show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'category' => $document->category->value,
                'category_label' => $document->category->label(),
                'document_date' => $document->document_date?->toDateString(),
                'tags' => $document->tags ?? [],
                'status' => $document->status->value,
                'ocr_error' => $document->ocr_error,
                'ocr_model' => $document->ocr_model,
                'ocr_pages' => $document->ocr_pages,
                'ocr_edited_at' => $document->ocr_edited_at?->toIso8601String(),
                'is_pdf' => $document->isPdf(),
                'size' => $document->size,
                'original_name' => $document->original_name,
                'uploaded_by' => $document->uploader?->name,
                'created_at' => $document->created_at?->toIso8601String(),
                'metadata' => $document->metadata,
                'metadata_state' => $document->metadata_state->value,
                'can_delete' => Gate::allows('delete', $document),
                'is_duplicate' => $document->duplicate_of_id !== null,
                'duplicate_of' => $document->duplicate_of_id,
            ],
            'dosar' => [
                'id' => $document->dosar->id,
                'name' => $document->dosar->name,
                'color' => $document->dosar->color,
            ],
            'pages' => $document->pages->map(fn ($page): array => [
                'number' => $page->page_number,
                'markdown' => $page->markdown,
            ])->all(),
            'categories' => collect(DocumentCategory::cases())
                ->map(fn ($category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ])->all(),
            'activity' => $this->recentActivity($document),
        ]);
    }

    /**
     * Stream the original into the browser, inline, behind the policy.
     */
    public function file(Request $request, MedicalDocument $document, ActivityLog $activity): StreamedResponse
    {
        Gate::authorize('view', $document);

        $activity->record($request, $document, Activity::ACTION_PREVIEW);

        return Storage::disk($document->disk)->response(
            $document->path,
            $document->original_name,
            ['Cache-Control' => 'private, no-store'],
        );
    }

    /**
     * Download the original.
     */
    public function download(Request $request, MedicalDocument $document, ActivityLog $activity): StreamedResponse
    {
        Gate::authorize('view', $document);

        $activity->record($request, $document, Activity::ACTION_DOWNLOAD);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    /**
     * Serve the generated thumbnail, if the document has one.
     */
    public function thumbnail(MedicalDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_if($document->thumbnail_path === null, 404);

        return Storage::disk($document->disk)->response(
            $document->thumbnail_path,
            null,
            ['Cache-Control' => 'private, max-age=3600'],
        );
    }

    /**
     * Change the document's details, or correct its text.
     */
    public function update(UpdateDocumentRequest $request, MedicalDocument $document): RedirectResponse
    {
        $data = $request->validated();

        $document->update([
            'title' => $data['title'],
            'category' => $data['category'],
            'document_date' => $data['document_date'] ?? null,
            'tags' => $data['tags'] ?? null,
            'metadata_state' => ($data['accept_metadata'] ?? false) ? MetadataState::Accepted : $document->metadata_state,
        ]);

        if (array_key_exists('text', $data)) {
            $this->replaceText($document, (string) $data['text']);
        }

        $document->syncSearchText();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document updated.')]);

        return back();
    }

    /**
     * Move a document to the bin. The file stays until the bin is emptied.
     */
    public function destroy(MedicalDocument $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $document->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Document moved to the bin. It can be restored for :days days.', [
                'days' => config('medical.restore_days'),
            ]),
        ]);

        return redirect()->route('medical.dosars.show', $document->medical_dosar_id);
    }

    /**
     * Ask the OCR service to read the document again.
     */
    public function retry(MedicalDocument $document): RedirectResponse
    {
        Gate::authorize('update', $document);

        $document->forceFill([
            'status' => DocumentStatus::Pending,
            'ocr_error' => null,
        ])->save();

        ProcessMedicalDocument::dispatch($document->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reading the file again.')]);

        return back();
    }

    /**
     * Bring a document back out of the bin.
     */
    public function restore(Request $request, int $document): RedirectResponse
    {
        $model = MedicalDocument::onlyTrashed()
            ->inCalendar($this->calendar($request))
            ->findOrFail($document);

        Gate::authorize('delete', $model);

        $model->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document restored.')]);

        return back();
    }

    /**
     * Everything in the bin, newest first.
     */
    public function trashed(Request $request): Response
    {
        $calendar = $this->calendar($request);

        $documents = MedicalDocument::onlyTrashed()
            ->inCalendar($calendar)
            ->with('dosar')
            ->orderByDesc('deleted_at')
            ->get();

        return Inertia::render('medical/Trash', [
            'restore_days' => (int) config('medical.restore_days'),
            'documents' => $documents->map(fn (MedicalDocument $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'deleted_at' => $document->deleted_at?->toIso8601String(),
                'dosar' => $document->dosar?->name,
                'can_restore' => Gate::allows('delete', $document),
            ])->all(),
        ]);
    }

    /**
     * Replace a document's pages with text a person typed or pasted.
     *
     * This is the escape hatch for a photo the OCR service could not read, so it
     * has to work even when there are no pages at all.
     */
    private function replaceText(MedicalDocument $document, string $text): void
    {
        $text = trim($text);

        $document->pages()->delete();

        if ($text !== '') {
            $document->pages()->create([
                'page_number' => 1,
                'markdown' => $text,
            ]);
        }

        $document->forceFill([
            'ocr_edited_at' => now(),
            'ocr_pages' => $text === '' ? 0 : 1,
            'status' => DocumentStatus::Ready,
        ])->save();
    }

    /**
     * An earlier copy of the same bytes in the same folder.
     *
     * The same file in a different folder is a legitimately shared document, so
     * only a match inside one person's folder counts as a duplicate.
     */
    private function findDuplicate(MedicalDocument $document): ?MedicalDocument
    {
        return MedicalDocument::query()
            ->inCalendar($document->calendar_id)
            ->where('medical_dosar_id', $document->medical_dosar_id)
            ->where('sha256', $document->sha256)
            ->whereKeyNot($document->id)
            ->first();
    }

    /**
     * A readable title from the file the phone or clinic produced.
     */
    private function titleFromFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $name = trim((string) preg_replace('/[\-_]+/', ' ', $name));

        return $name === '' ? __('Untitled document') : mb_substr($name, 0, 120);
    }

    /**
     * Who looked at this document lately.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(MedicalDocument $document): array
    {
        return Activity::query()
            ->with('user')
            ->where('medical_document_id', $document->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Activity $entry): array => [
                'action' => $entry->action,
                'user' => $entry->user?->name,
                'at' => $entry->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
