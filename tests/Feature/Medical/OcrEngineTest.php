<?php

use App\Medical\Models\MedicalDocument;
use App\Medical\Ocr\Engines\NullOcrEngine;
use App\Medical\Ocr\Engines\OpperOcrEngine;
use App\Medical\Ocr\OcrException;
use App\Medical\Support\OcrText;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('the opper engine sends the file as base64 and never as a public url', function () {
    Storage::fake('medical');
    Storage::disk('medical')->put('medical/1/scan.pdf', 'PDF-BYTES');

    $document = new MedicalDocument([
        'disk' => 'medical',
        'path' => 'medical/1/scan.pdf',
        'original_name' => 'reteta.pdf',
    ]);

    Http::fake([
        'api.opper.ai/v3/ocr' => Http::response([
            'id' => 'ocr_1',
            'model' => 'mistral/mistral-ocr-4-1',
            'pages' => [
                ['index' => 0, 'markdown' => 'Rețetă', 'blocks' => [['type' => 'text', 'content' => 'Rețetă']]],
            ],
            'usage' => ['cost' => 0.002, 'pages_processed' => 1],
        ]),
    ]);

    $engine = new OpperOcrEngine([
        'base_url' => 'https://api.opper.ai',
        'api_key' => 'test-key',
        'model' => 'mistral/mistral-ocr-4-1',
        'timeout' => 30,
    ]);

    $result = $engine->extract($document);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.opper.ai/v3/ocr'
            && $request['model'] === 'mistral/mistral-ocr-4-1'
            && $request['document']['type'] === 'base64'
            && $request['document']['content'] === base64_encode('PDF-BYTES')
            && $request['document']['document_name'] === 'reteta.pdf'
            && $request->hasHeader('Authorization', 'Bearer test-key');
    });

    expect($result->pageCount())->toBe(1)
        ->and($result->pages[0]->markdown)->toBe('Rețetă')
        ->and($result->pages[0]->number)->toBe(1)
        ->and($result->model)->toBe('mistral/mistral-ocr-4-1');
});

test('page numbers are one-based even when the engine reports zero-based indices', function () {
    Storage::fake('medical');
    Storage::disk('medical')->put('scan.pdf', 'bytes');

    Http::fake([
        'api.opper.ai/v3/ocr' => Http::response([
            'pages' => [
                ['index' => 0, 'markdown' => 'one'],
                ['index' => 1, 'markdown' => 'two'],
            ],
        ]),
    ]);

    $document = new MedicalDocument(['disk' => 'medical', 'path' => 'scan.pdf', 'original_name' => 'x.pdf']);

    $engine = new OpperOcrEngine([
        'base_url' => 'https://api.opper.ai',
        'api_key' => 'k',
        'model' => 'm',
        'timeout' => 30,
    ]);

    expect(array_map(fn ($page) => $page->number, $engine->extract($document)->pages))->toBe([1, 2]);
});

test('a refused request becomes a readable failure', function () {
    Storage::fake('medical');
    Storage::disk('medical')->put('scan.pdf', 'bytes');

    Http::fake([
        'api.opper.ai/v3/ocr' => Http::response(['error' => ['code' => 'bad', 'message' => 'File too large']], 413),
    ]);

    $document = new MedicalDocument(['disk' => 'medical', 'path' => 'scan.pdf', 'original_name' => 'x.pdf']);

    $engine = new OpperOcrEngine([
        'base_url' => 'https://api.opper.ai',
        'api_key' => 'k',
        'model' => 'm',
        'timeout' => 30,
    ]);

    expect(fn () => $engine->extract($document))
        ->toThrow(OcrException::class, 'File too large');
});

test('a missing file is reported rather than sent', function () {
    Storage::fake('medical');

    Http::fake();

    $document = new MedicalDocument(['disk' => 'medical', 'path' => 'gone.pdf', 'original_name' => 'x.pdf']);

    $engine = new OpperOcrEngine([
        'base_url' => 'https://api.opper.ai',
        'api_key' => 'k',
        'model' => 'm',
        'timeout' => 30,
    ]);

    expect(fn () => $engine->extract($document))->toThrow(OcrException::class);

    Http::assertNothingSent();
});

test('the null engine returns one empty page so text can be typed in', function () {
    $document = new MedicalDocument(['disk' => 'medical', 'path' => 'x.pdf', 'original_name' => 'x.pdf']);

    $result = (new NullOcrEngine)->extract($document);

    expect($result->pageCount())->toBe(1)
        ->and($result->isEmpty())->toBeTrue()
        ->and($result->pages[0]->number)->toBe(1);
});

test('folding Romanian keeps the string length so snippets stay aligned', function () {
    $samples = [
        'Rețetă medicală — Spitalul Clinic Județean de Urgență',
        'Bilet de trimitere către Șos. Nordului nr. 12, București',
        'Înmormântare: ăâîșț',
    ];

    foreach ($samples as $sample) {
        $folded = OcrText::fold($sample);

        expect(mb_strlen($folded))->toBe(mb_strlen(Str::squish($sample)));
    }
});

test('folding strips both the comma and the cedilla forms of Romanian letters', function () {
    $fold = fn (string $text): string => OcrText::fold($text);

    expect($fold('șarpe'))->toBe('sarpe')
        ->and($fold('şarpe'))->toBe('sarpe')
        ->and($fold('țară'))->toBe('tara')
        ->and($fold('ţară'))->toBe('tara')
        ->and($fold('ÎNMORMÂNTARE'))->toBe('inmormantare');
});
