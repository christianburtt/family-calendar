<?php

use App\Medical\Enums\DocumentStatus;
use App\Medical\Jobs\ProcessMedicalDocument;
use App\Medical\Metadata\MetadataManager;
use App\Medical\Models\MedicalDocument;
use App\Medical\Ocr\OcrManager;
use App\Medical\Ocr\OcrPage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeOcrEngine;

/**
 * Bind a fake OCR engine and hand it back for the test to script.
 */
function fakeOcr(): FakeOcrEngine
{
    $engine = new FakeOcrEngine;

    app()->instance(OcrManager::class, new class(app(), $engine) extends OcrManager
    {
        public function __construct($app, private FakeOcrEngine $engine)
        {
            parent::__construct($app);
        }

        public function driver($driver = null)
        {
            return $this->engine;
        }
    });

    return $engine;
}

test('uploading a scan files it and queues it to be read', function () {
    Queue::fake();
    Storage::fake('medical');

    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar, 'Maria');

    $this->actingAs($owner)
        ->post(route('medical.documents.store', $dosar), [
            'files' => [fakePdf('reteta-martie.pdf')],
            'category' => 'prescription',
            'document_date' => '2026-03-14',
        ])
        ->assertRedirect();

    $document = MedicalDocument::query()->sole();

    expect($document->medical_dosar_id)->toBe($dosar->id)
        ->and($document->category->value)->toBe('prescription')
        ->and($document->document_date->toDateString())->toBe('2026-03-14')
        ->and($document->status)->toBe(DocumentStatus::Pending)
        ->and($document->uploaded_by_id)->toBe($owner->id)
        ->and($document->sha256)->toHaveLength(64)
        ->and($document->path)->toContain('medical/'.$calendar->id.'/');

    Storage::disk('medical')->assertExists($document->path);

    Queue::assertPushed(ProcessMedicalDocument::class);
});

test('the uploaded file is titled from its filename', function () {
    Queue::fake();

    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);

    $this->actingAs($owner)->post(route('medical.documents.store', $dosar), [
        'files' => [fakePdf('bilet_trimitere_ORL.pdf')],
    ]);

    expect(MedicalDocument::query()->sole()->title)->toBe('bilet trimitere ORL');
});

test('the job stores the extracted text page by page', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);
    $document = MedicalDocument::factory()->inDosar($dosar)->pending()->create();

    fakeOcr()->willReturn($document, [
        new OcrPage(1, 'Rețetă. Dr. Popescu.'),
        new OcrPage(2, 'Hemogramă: hemoglobină 13.2'),
    ]);

    (new ProcessMedicalDocument($document->id))->handle(app(OcrManager::class), app(MetadataManager::class));

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->ocr_pages)->toBe(2)
        ->and($document->ocr_model)->toBe('fake-ocr')
        ->and($document->pages()->pluck('page_number')->all())->toBe([1, 2])
        ->and($document->pages->first()->markdown)->toContain('Popescu');
});

test('a failed OCR keeps the document and records why', function () {
    [$calendar] = medicalHousehold();
    $dosar = dosarIn($calendar);
    $document = MedicalDocument::factory()->inDosar($dosar)->pending()->create();

    fakeOcr()->willFail('The engine refused the file.');

    (new ProcessMedicalDocument($document->id))->handle(app(OcrManager::class), app(MetadataManager::class));

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->ocr_error)->toContain('refused')
        ->and($document->path)->not->toBeEmpty();
});

test('a failed document can be retried', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);
    $document = MedicalDocument::factory()->inDosar($dosar)->failed()->create();

    Queue::fake();

    $this->actingAs($owner)
        ->post(route('medical.documents.retry', $document))
        ->assertRedirect();

    expect($document->fresh()->status)->toBe(DocumentStatus::Pending)
        ->and($document->fresh()->ocr_error)->toBeNull();

    Queue::assertPushed(ProcessMedicalDocument::class);
});

test('the same bytes uploaded twice into one folder are flagged', function () {
    Queue::fake();
    Storage::fake('medical');

    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar, 'Maria');

    $this->actingAs($owner)->post(route('medical.documents.store', $dosar), [
        'files' => [fakePdf('analize.pdf', 'the-same-bytes')],
    ]);

    $this->actingAs($owner)->post(route('medical.documents.store', $dosar), [
        'files' => [fakePdf('analize-copy.pdf', 'the-same-bytes')],
    ]);

    $documents = MedicalDocument::query()->orderBy('id')->get();

    expect($documents)->toHaveCount(2)
        ->and($documents->first()->duplicate_of_id)->toBeNull()
        ->and($documents->last()->duplicate_of_id)->toBe($documents->first()->id);
});

test('the same bytes in a different folder is not a duplicate', function () {
    Queue::fake();

    [$calendar, $owner] = medicalHousehold();
    $maria = dosarIn($calendar, 'Maria');
    $andrei = dosarIn($calendar, 'Andrei');

    $this->actingAs($owner)->post(route('medical.documents.store', $maria), [
        'files' => [fakePdf('analize.pdf', 'shared-analyses')],
    ]);

    $this->actingAs($owner)->post(route('medical.documents.store', $andrei), [
        'files' => [fakePdf('analize.pdf', 'shared-analyses')],
    ]);

    expect(MedicalDocument::query()->whereNotNull('duplicate_of_id')->count())->toBe(0);
});

test('an upload from another household is refused', function () {
    Queue::fake();

    [$first] = medicalHousehold();
    [, $outsider] = medicalHousehold();

    $dosar = dosarIn($first);

    $this->actingAs($outsider)
        ->post(route('medical.documents.store', $dosar), [
            'files' => [fakePdf('x.pdf')],
        ])
        ->assertForbidden();

    expect(MedicalDocument::query()->count())->toBe(0);
});

test('an unsupported file type is rejected', function () {
    Queue::fake();

    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);

    $this->actingAs($owner)
        ->post(route('medical.documents.store', $dosar), [
            'files' => [new UploadedFile(
                tap(tempnam(sys_get_temp_dir(), 'medical-test-').'.docx', fn (string $path) => file_put_contents($path, 'PK')),
                'notes.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                null,
                true,
            )],
        ])
        ->assertSessionHasErrors('files.0');

    expect(MedicalDocument::query()->count())->toBe(0);
});
