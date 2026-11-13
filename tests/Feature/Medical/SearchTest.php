<?php

use App\Medical\Models\MedicalDocument;

test('search finds Romanian text typed without diacritics', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar, 'Maria');

    documentIn($dosar, 'Rețetă medicală — Dr. Popescu, Spitalul Județean de Urgență');
    documentIn($dosar, 'Concediu medical pentru Șos. Nordului 12');

    $this->actingAs($owner)
        ->get(route('medical.index', ['q' => 'reteta']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results', 1)
            ->where('results.0.title', fn (string $title): bool => str_contains($title, 'Rețetă'))
        );
});

test('search matches text with diacritics typed in', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);

    documentIn($dosar, 'Hemogramă completă');

    $this->actingAs($owner)
        ->get(route('medical.index', ['q' => 'hemogramă']))
        ->assertInertia(fn ($page) => $page->has('results', 1));
});

test('search matches the cedilla spelling of Romanian letters', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);

    documentIn($dosar, 'Bilet de trimitere către Spitalul Județean');

    $this->actingAs($owner)
        ->get(route('medical.index', ['q' => 'judetean']))
        ->assertInertia(fn ($page) => $page->has('results', 1));
});

test('search finds a document by the person it belongs to', function () {
    [$calendar, $owner] = medicalHousehold();
    $maria = dosarIn($calendar, 'Maria');
    $andrei = dosarIn($calendar, 'Andrei');

    documentIn($maria, 'Analize');
    documentIn($andrei, 'Analize');

    $this->actingAs($owner)
        ->get(route('medical.index', ['q' => 'maria']))
        ->assertInertia(fn ($page) => $page
            ->has('results', 1)
            ->where('results.0.dosar.name', 'Maria')
        );
});

test('search finds a document by its category label', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);

    $document = documentIn($dosar, 'something unrelated');
    $document->update(['category' => 'referral', 'title' => 'Unrelated']);
    $document->syncSearchText();

    $this->actingAs($owner)
        ->get(route('medical.index', ['q' => 'trimitere']))
        ->assertInertia(fn ($page) => $page->has('results', 1));
});

test('search reports which page matched', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);

    $document = MedicalDocument::factory()->inDosar($dosar)->create();
    $document->pages()->create(['page_number' => 1, 'markdown' => 'Rețetă pentru ibuprofen']);
    $document->pages()->create(['page_number' => 3, 'markdown' => 'Colesterol total 5.2 mmol/L']);

    $this->actingAs($owner)
        ->get(route('medical.index', ['q' => 'colesterol']))
        ->assertInertia(fn ($page) => $page
            ->has('results', 1)
            ->where('results.0.matched_page', 3)
            ->where('results.0.snippet', fn (string $snippet): bool => str_contains($snippet, 'colesterol'))
        );
});

test('search never returns another household\'s documents', function () {
    [$firstCalendar, $firstOwner] = medicalHousehold();
    documentIn(dosarIn($firstCalendar, 'Maria'), 'Rețetă ibuprofen');

    [, $secondOwner] = medicalHousehold();

    $this->actingAs($secondOwner)
        ->get(route('medical.index', ['q' => 'reteta']))
        ->assertInertia(fn ($page) => $page->has('results', 0));

    $this->actingAs($firstOwner)
        ->get(route('medical.index', ['q' => 'reteta']))
        ->assertInertia(fn ($page) => $page->has('results', 1));
});

test('search skips documents that have not been read yet', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);

    $pending = MedicalDocument::factory()
        ->inDosar($dosar)
        ->pending()
        ->create(['title' => 'Referral']);

    $pending->update(['search_text' => 'referral reteta']);

    $this->actingAs($owner)
        ->get(route('medical.index', ['q' => 'reteta']))
        ->assertInertia(fn ($page) => $page->has('results', 0));
});

test('renaming a folder updates the documents\' search text', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar, 'Maria');
    $document = documentIn($dosar, 'Analize');

    $this->actingAs($owner)->patch(route('medical.dosars.update', $dosar), [
        'name' => 'Ioana',
        'color' => '#2563eb',
    ]);

    expect(MedicalDocumentSearchText($document))->toContain('ioana');

    $this->actingAs($owner)
        ->get(route('medical.index', ['q' => 'ioana']))
        ->assertInertia(fn ($page) => $page->has('results', 1));
});

/**
 * The folded search text stored on a document.
 */
function MedicalDocumentSearchText(MedicalDocument $document): string
{
    return (string) $document->fresh()->search_text;
}
