<?php

use App\Medical\Models\Activity;
use App\Medical\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('everyone in the household can read a folder', function () {
    [$calendar, $owner] = medicalHousehold();
    $member = User::factory()->create();
    $calendar->members()->attach($member, ['role' => 'member', 'status' => 'active']);

    $dosar = dosarIn($calendar, 'Maria');

    $this->actingAs($member)
        ->get(route('medical.dosars.show', $dosar))
        ->assertOk();
});

test('a member can upload but not delete someone else\'s document', function () {
    [$calendar, $owner] = medicalHousehold();
    $member = User::factory()->create();
    $calendar->members()->attach($member, ['role' => 'member', 'status' => 'active']);

    $dosar = dosarIn($calendar);
    $ownersDocument = MedicalDocument::factory()->inDosar($dosar)->create(['uploaded_by_id' => $owner->id]);

    expect($member->can('update', $ownersDocument))->toBeTrue()
        ->and($member->can('delete', $ownersDocument))->toBeFalse()
        ->and($owner->can('delete', $ownersDocument))->toBeTrue();
});

test('the household owner can delete anyone\'s document', function () {
    [$calendar, $owner] = medicalHousehold();
    $member = User::factory()->create();
    $calendar->members()->attach($member, ['role' => 'member', 'status' => 'active']);

    $dosar = dosarIn($calendar);
    $membersDocument = MedicalDocument::factory()->inDosar($dosar)->create(['uploaded_by_id' => $member->id]);

    $this->actingAs($owner)
        ->delete(route('medical.documents.destroy', $membersDocument))
        ->assertRedirect();

    expect($membersDocument->fresh()->trashed())->toBeTrue();
});

test('a member cannot delete the whole folder', function () {
    [$calendar, $owner] = medicalHousehold();
    $member = User::factory()->create();
    $calendar->members()->attach($member, ['role' => 'member', 'status' => 'active']);

    $dosar = dosarIn($calendar, 'Maria');

    $this->actingAs($member)
        ->delete(route('medical.dosars.destroy', $dosar))
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('medical.dosars.destroy', $dosar))
        ->assertRedirect();

    expect($dosar->fresh()->trashed())->toBeTrue();
});

test('deleting a document keeps the file until the bin is emptied', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);
    $document = MedicalDocument::factory()->inDosar($dosar)->create(['uploaded_by_id' => $owner->id]);

    Storage::disk('local')->put($document->path, 'bytes');

    $this->actingAs($owner)->delete(route('medical.documents.destroy', $document));

    expect(MedicalDocument::withTrashed()->find($document->id)->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists($document->path))->toBeTrue();
});

test('a restored document comes back into its folder', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);
    $document = MedicalDocument::factory()->inDosar($dosar)->create(['uploaded_by_id' => $owner->id]);
    $document->delete();

    $this->actingAs($owner)
        ->post(route('medical.documents.restore', $document->id))
        ->assertRedirect();

    expect($document->fresh()->trashed())->toBeFalse();
});

test('the bin lists deleted documents and nothing else', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar, 'Maria');

    $kept = MedicalDocument::factory()->inDosar($dosar)->create(['title' => 'Keep me']);
    $bin = MedicalDocument::factory()->inDosar($dosar)->create(['title' => 'Bin me']);
    $bin->delete();

    $this->actingAs($owner)
        ->get(route('medical.trash'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('medical/Trash')
            ->has('documents', 1)
            ->where('documents.0.title', 'Bin me')
        );
});

test('opening a document is recorded', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);
    $document = documentIn($dosar);

    $this->actingAs($owner)->get(route('medical.documents.show', $document));

    $entry = Activity::query()->sole();

    expect($entry->action)->toBe(Activity::ACTION_VIEW)
        ->and($entry->user_id)->toBe($owner->id)
        ->and($entry->medical_document_id)->toBe($document->id)
        ->and($entry->calendar_id)->toBe($calendar->id);
});

test('downloading is recorded separately from opening', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);
    $document = MedicalDocument::factory()->inDosar($dosar)->create();

    Storage::disk('local')->put($document->path, 'bytes');

    $this->actingAs($owner)->get(route('medical.documents.download', $document));

    expect(Activity::query()->where('action', Activity::ACTION_DOWNLOAD)->count())->toBe(1);
});

test('the activity log keeps the record when the person is gone', function () {
    [$calendar, $owner] = medicalHousehold();
    $dosar = dosarIn($calendar);
    $document = documentIn($dosar);

    $this->actingAs($owner)->get(route('medical.documents.show', $document));

    $owner->delete();

    expect(Activity::query()->sole()->user_id)->toBeNull();
});
