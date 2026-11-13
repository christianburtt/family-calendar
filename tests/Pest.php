<?php

use App\Medical\Models\Dosar;
use App\Medical\Models\MedicalDocument;
use App\Models\Calendar;
use App\Models\CalendarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Medical module defaults
|--------------------------------------------------------------------------
|
| Config that every test needs is set once. Individual tests swap in their own
| OCR engine; the whole suite runs on the fake disk and never calls out.
|
*/

pest()->beforeEach(function () {
    config([
        'medical.disk' => 'medical',
        'medical.ocr.driver' => 'null',
        'medical.metadata.enabled' => false,
    ]);

    Storage::fake('medical');
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that the values of
| your data meet certain conditions. The "expect()" function gives you
| access to those assertion methods.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a calendar owned by the given person, with everyone else approved onto it.
 */
function calendarFor(User $owner, User ...$members): Calendar
{
    $calendar = Calendar::factory()->ownedBy($owner)->create();

    $calendar->members()->attach($owner, [
        'role' => CalendarUser::ROLE_OWNER,
        'status' => CalendarUser::STATUS_ACTIVE,
    ]);

    foreach ($members as $member) {
        $calendar->members()->attach($member, [
            'role' => CalendarUser::ROLE_MEMBER,
            'status' => CalendarUser::STATUS_ACTIVE,
        ]);
    }

    $owner->switchTo($calendar);

    return $calendar;
}

/**
 * A household with medical records switched on, and its first member.
 *
 * @return array{0: Calendar, 1: User}
 */
function medicalHousehold(?User $owner = null): array
{
    $owner ??= User::factory()->create();
    $calendar = calendarFor($owner);
    $calendar->setModule(Calendar::MODULE_MEDICAL, true);

    return [$calendar, $owner];
}

/**
 * A person's folder inside a household.
 */
function dosarIn(Calendar $calendar, string $name = 'Maria'): Dosar
{
    return Dosar::factory()->onCalendar($calendar)->forPerson($name)->create();
}

/**
 * A document filed in a folder, with one page of extracted text.
 */
function documentIn(Dosar $dosar, string $markdown = 'Analize: hemogramă, glicemie'): MedicalDocument
{
    $document = MedicalDocument::factory()->inDosar($dosar)->create();

    $document->pages()->create(['page_number' => 1, 'markdown' => $markdown]);

    return $document;
}

/**
 * A real PDF upload.
 *
 * `UploadedFile::fake()->create()` produces a zero-byte file in this version of
 * Laravel, which passes the mime check only because the client's guess is
 * trusted. Validation reads the bytes, so tests that upload have to hand over a
 * file with a PDF header like the clinic's scanner would.
 */
function fakePdf(string $name = 'document.pdf', string $marker = 'scan'): UploadedFile
{
    $body = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n".$marker;

    $path = tempnam(sys_get_temp_dir(), 'medical-test-').'.pdf';
    file_put_contents($path, $body);

    return new UploadedFile($path, $name, 'application/pdf', null, true);
}

/**
 * A real JPEG upload.
 */
function fakeJpeg(string $name = 'photo.jpg', string $marker = 'photo'): UploadedFile
{
    $image = imagecreatetruecolor(40, 40);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 200, 200));

    $path = tempnam(sys_get_temp_dir(), 'medical-test-').'.jpg';
    imagejpeg($image, $path);
    file_put_contents($path, $marker, FILE_APPEND);

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}
