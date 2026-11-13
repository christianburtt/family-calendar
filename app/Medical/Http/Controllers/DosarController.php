<?php

namespace App\Medical\Http\Controllers;

use App\Medical\Enums\DocumentCategory;
use App\Medical\Enums\DocumentStatus;
use App\Medical\Http\Controllers\Concerns\MedicalController;
use App\Medical\Http\Requests\StoreDosarRequest;
use App\Medical\Http\Requests\UpdateDosarRequest;
use App\Medical\Models\Dosar;
use App\Medical\Models\MedicalDocument;
use App\Medical\Services\Search\DocumentSearcher;
use App\Models\Calendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DosarController extends MedicalController
{
    /**
     * Show the household's folders and whatever the search box found.
     */
    public function index(Request $request, DocumentSearcher $searcher): Response
    {
        $calendar = $this->calendar($request);
        $term = trim((string) $request->query('q', ''));

        return Inertia::render('medical/Index', [
            'dosars' => $this->dosarSummaries($calendar),
            'search' => $term,
            'results' => $term === ''
                ? []
                : $searcher->search($calendar, $term)
                    ->map(fn ($hit): array => $hit->toArray())
                    ->all(),
            'can_create' => $calendar->hasActiveMember($request->user()),
            'trash_count' => MedicalDocument::onlyTrashed()->inCalendar($calendar)->count(),
        ]);
    }

    /**
     * Show the form for a new folder.
     *
     * Also lists the household's members so a folder can be created for anyone
     * who already has a login in one step.
     */
    public function create(Request $request): Response
    {
        $calendar = $this->calendar($request);

        $existing = Dosar::query()
            ->inCalendar($calendar)
            ->pluck('name')
            ->map(fn (string $name): string => mb_strtolower($name))
            ->all();

        return Inertia::render('medical/Dosars/Create', [
            'suggestions' => $calendar->activeMembers()
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.color'])
                ->reject(fn ($member): bool => in_array(mb_strtolower($member->name), $existing, true))
                ->map(fn ($member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'color' => $member->color,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Create one or more folders.
     *
     * A single person comes through as a full form; the "create for these
     * people" shortcut sends several names at once, which is how a household
     * gets set up without typing every name.
     */
    public function store(StoreDosarRequest $request): RedirectResponse
    {
        $calendar = $this->calendar($request);
        $created = [];

        foreach ((array) $request->input('people', []) as $person) {
            $created[] = Dosar::query()->create([
                'calendar_id' => $calendar->id,
                'name' => $person['name'],
                'color' => $person['color'],
                'created_by_id' => $request->user()->id,
            ]);
        }

        if ($created === [] && $request->filled('name')) {
            $created[] = Dosar::query()->create([
                'calendar_id' => $calendar->id,
                'name' => (string) $request->input('name'),
                'date_of_birth' => $request->input('date_of_birth'),
                'cnp' => $request->input('cnp'),
                'color' => $request->input('color'),
                'notes' => $request->input('notes'),
                'created_by_id' => $request->user()->id,
            ]);
        }

        abort_if($created === [], 422, __('Add a name for the folder.'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($created) === 1
                ? __('Folder created.')
                : __(':count folders created.', ['count' => count($created)]),
        ]);

        return redirect()->route('medical.dosars.show', $created[0]);
    }

    /**
     * Show one folder and everything filed in it.
     */
    public function show(Request $request, Dosar $dosar): Response
    {
        Gate::authorize('view', $dosar);

        $documents = $dosar->documents()
            ->with('uploader')
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('medical/Dosars/Show', [
            'dosar' => [
                'id' => $dosar->id,
                'name' => $dosar->name,
                'initials' => $dosar->initials(),
                'color' => $dosar->color,
                'date_of_birth' => $dosar->date_of_birth?->toDateString(),
                'notes' => $dosar->notes,
                'document_count' => $documents->count(),
                'can_delete' => Gate::allows('delete', $dosar),
            ],
            'documents' => $documents->map(fn (MedicalDocument $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'category' => $document->category->value,
                'category_label' => $document->category->label(),
                'document_date' => $document->document_date?->toDateString(),
                'status' => $document->status->value,
                'is_pdf' => $document->isPdf(),
                'pages' => $document->ocr_pages,
                'uploaded_by' => $document->uploader?->name,
                'can_delete' => Gate::allows('delete', $document),
                'is_duplicate' => $document->duplicate_of_id !== null,
            ])->all(),
            'categories' => collect(DocumentCategory::cases())
                ->map(fn ($category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ])->all(),
            'upload' => [
                'max_mb' => (int) config('medical.max_upload_mb'),
                'max_files' => (int) config('medical.max_files_per_upload'),
                'accept' => implode(',', (array) config('medical.accepted_mime_types')),
            ],
        ]);
    }

    /**
     * Rename a folder or change its details.
     */
    public function update(UpdateDosarRequest $request, Dosar $dosar): RedirectResponse
    {
        $dosar->update($request->validated());

        $dosar->documents()->get()->each->syncSearchText();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder updated.')]);

        return back();
    }

    /**
     * Move a folder and its documents to the bin.
     */
    public function destroy(Request $request, Dosar $dosar): RedirectResponse
    {
        Gate::authorize('delete', $dosar);

        $dosar->documents()->get()->each->delete();
        $dosar->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder moved to the bin.')]);

        return redirect()->route('medical.index');
    }

    /**
     * Bring a folder back out of the bin.
     */
    public function restore(Request $request, int $dosar): RedirectResponse
    {
        $model = Dosar::onlyTrashed()->inCalendar($this->calendar($request))->findOrFail($dosar);

        Gate::authorize('delete', $model);

        $model->restore();
        $model->documents()->onlyTrashed()->get()->each->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder restored.')]);

        return redirect()->route('medical.dosars.show', $model);
    }

    /**
     * Reduce the household's folders to what the grid needs.
     *
     * Counts and the newest document date come from subqueries rather than
     * aggregates, so each row is a single object with known properties and a
     * household with six people costs one round trip instead of a dozen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dosarSummaries(Calendar $calendar): array
    {
        $summaries = [];

        foreach (Dosar::query()->inCalendar($calendar)->orderBy('name')->get() as $dosar) {
            $summaries[] = [
                'id' => $dosar->id,
                'name' => $dosar->name,
                'initials' => $dosar->initials(),
                'color' => $dosar->color,
                'date_of_birth' => $dosar->date_of_birth?->toDateString(),
                'document_count' => $dosar->documents()->count(),
                'latest_document_date' => $dosar->documents()->max('document_date'),
                'processing' => $dosar->documents()
                    ->whereIn('status', [DocumentStatus::Pending->value, DocumentStatus::Processing->value])
                    ->count(),
            ];
        }

        return $summaries;
    }
}
