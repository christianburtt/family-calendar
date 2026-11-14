# Medical Module

A document archive for household medical records: one dosar per person, scanned documents text-searchable in Romanian and English, OCR and metadata extraction behind swappable drivers.

This document is the agreed design. It is the spec the implementation follows, and it records the decisions and the evidence behind them so a later change can tell what was deliberate.

---

## 1. Tenancy: the calendar _is_ the household

There is no `households` table. A household is a calendar; membership is `calendar_user` where `status = active`. The module gate is a JSON column on the calendar.

```php
// calendars
$table->json('modules')->nullable()->after('join_code');
```

```php
// App\Models\Calendar
public function hasModule(string $module): bool
{
    return (bool) ($this->modules[$module] ?? false);
}
```

- Migration backfills `{"medical": true}` on every existing calendar.
- New calendars start with `modules = null`; the owner enables medical from the People page.
- Every medical table carries `calendar_id`, so scoping is a single `where` and never a join through a pivot.

**Accepted consequence:** approving a join request grants medical access. There is no separate consent step; `medical_activity` makes it auditable after the fact.

## 2. Schema

```
calendars            + modules json

dosars               id, calendar_id ->cascade, name, date_of_birth?, cnp? (encrypted),
                     color, notes?, search_text, created_by_id ->nullOnDelete,
                     archived_at?, deleted_at, timestamps
                     index(calendar_id, archived_at)

medical_documents    id, calendar_id ->cascade, dosar_id ->cascade,
                     title, category, tags json?, document_date?,
                     original_name, disk, path, mime_type, size, sha256,
                     thumbnail_path?, duplicate_of_id?,
                     status(pending|processing|ready|failed), ocr_error?,
                     ocr_model?, ocr_pages?, ocr_edited_at?,
                     metadata_state(suggested|accepted|none), metadata json?,
                     uploaded_by_id ->nullOnDelete, search_text,
                     deleted_at, timestamps
                     index(calendar_id, sha256)

document_pages       id, document_id ->cascade, page_number, markdown,
                     blocks json?, search_text, timestamps
                     unique(document_id, page_number)

medical_activity     id, calendar_id, document_id ->nullOnDelete,
                     user_id ->nullOnDelete, action, ip?, user_agent?, created_at
                     index(calendar_id, created_at)
```

`cnp` uses Laravel's `encrypted` cast: never indexed, never searched. Patient names carry the search weight.

## 3. Search

Text is folded to ASCII and lowercased on write, and searched with `LIKE`.

```php
final class OcrText
{
    public static function fold(string $text): string
    {
        return mb_strtolower(
            Transliterator::create('Any-Latin; Latin-ASCII')?->transliterate($text)
                ?? Str::ascii($text)
        );
    }
}
```

| Column                          | Contents                           |
| ------------------------------- | ---------------------------------- |
| `dosars.search_text`            | folded patient name                |
| `medical_documents.search_text` | folded title, tags, category label |
| `document_pages.search_text`    | folded OCR markdown                |

The query matches a document when any of the three match, so `reteta` finds `Rețetă`, `Maria` finds Maria's documents, and a lab value finds the page it is printed on. The snippet is cut from the folded text and clamped to the original page text, because folding is length-preserving for Romanian but not for `ß` or ligatures.

No FULLTEXT index, no search service: identical behaviour on MySQL and SQLite. `MATCH ... AGAINST` does not exist in SQLite, and `LIKE` at household scale is microseconds.

## 4. Files

```php
// config/filesystems.php
'medical' => [
    'driver' => 'local',
    'root' => storage_path('app/medical'),
    'serve' => false,   // safe to drop in; the pill is why opening this file saves time
    'throw' => false,
],
```

`serve => false` is load-bearing: a disk with `serve => true` registers a public `GET /storage/{path}` route, and medical files must never be reachable outside a policy check.

```php
'disk' => env('MEDICAL_DISK', 'medical'),
```

`Storage::disk(config('medical.disk'))` is the whole abstraction. `MEDICAL_DISK=s3` plus `composer require league/flysystem-aws-s3-v3` moves the archive to object storage with no application change.

`ImageNormaliser` chain:

1. PDF passes through untouched.
2. HEIC/HEIF via Imagick; fall back to macOS `sips`; otherwise reject with a readable message.
3. JPEG/PNG re-encoded at 2500 px, quality 85.

## 5. OCR and metadata

```php
interface OcrEngine
{
    public function extract(MedicalDocument $document): OcrResult;
}

interface MetadataExtractor
{
    public function extract(string $markdown): ?DocumentMetadata;
}
```

| Driver                   | Env                                            |
| ------------------------ | ---------------------------------------------- |
| `OpperOcrEngine`         | `MEDICAL_OCR_MODEL=mistral/mistral-ocr-latest` |
| `NullOcrEngine`          | `MEDICAL_OCR_DRIVER=null`                      |
| `OpperMetadataExtractor` | `MEDICAL_METADATA_MODEL=deepseek-v4.1-flash`   |

Opper is the default because it is one EU-hosted key in front of both models, its `/v3/ocr` endpoint accepts base64 so a private file never needs a public URL, and its `/v3/compat` endpoint is OpenAI-compatible so the metadata model is the same key with a different model string.

## 6. Pipeline

```
UploadMedicalDocuments
  -> store original, hash, create rows (status=pending)
  -> ProcessMedicalDocument (tries 3, backs off)

ProcessMedicalDocument
  -> normalise -> store -> sha256 -> OCR (pages + blocks)
  -> metadata suggestions -> fold search text -> status=ready
     on failure -> status=failed, ocr_error, document kept
```

A failed OCR never rejects the upload: the scan is the artefact that matters, and the text can be typed in by hand.

## 7. Permissions

| Action                    | Who                                    |
| ------------------------- | -------------------------------------- |
| View anything             | active calendar member, module enabled |
| Create dosar / upload     | any active member                      |
| Edit metadata or OCR text | any active member                      |
| Delete document           | uploader **or** calendar owner         |
| Delete dosar              | calendar owner                         |
| Restore                   | same as delete                         |

Policies resolve through `calendar_id`; a document id from another calendar 404s before any query runs.

## 8. Routes

```
GET    medical                                  medical.index
GET    medical/dosars/create                    dosars.create
POST   medical/dosars                           dosars.store
GET    medical/dosars/{dosar}                   dosars.show
PATCH  medical/dosars/{dosar}                   dosars.update
DELETE medical/dosars/{dosar}                   dosars.destroy
POST   medical/dosars/{dosar}/restore           dosars.restore
POST   medical/dosars/{dosar}/documents         documents.store
GET    medical/documents/{document}             documents.show
GET    medical/documents/{document}/file        documents.file
GET    medical/documents/{document}/download    documents.download
PATCH  medical/documents/{document}             documents.update
DELETE medical/documents/{document}             documents.destroy
POST   medical/documents/{document}/retry       documents.retry
POST   medical/documents/{document}/restore     documents.restore
GET    medical/trash                            medical.trash
PATCH  calendars/{calendar}/modules             calendars.modules.update
```

`calendars.modules.update` deliberately sits **outside** the `medical.enabled`
middleware: switching the module on has to work while it is still off. Its
authorisation is `Gate::authorize('manage', $calendar)`, the household's owner.

## 9. Build order

1. Tenancy and module gate.
2. Dosars CRUD, policies, "Create dosar for..." checklist.
3. Upload, `ImageNormaliser`, storage, duplicate warning.
4. `OcrEngine` interface, `NullOcrEngine`, job, pages — a working app with no API key.
5. `OpperOcrEngine`, `OpperMetadataExtractor`, `medical:check-ocr`.
6. Search, snippets, page numbers.
7. Trash, restore, activity log, People-page toggle.

Steps 1–4 need no credentials.

## 10. Environment

```dotenv
OPPER_API_KEY=
MEDICAL_DISK=            # empty means the environment's default disk
MEDICAL_MAX_UPLOAD_MB=25
MEDICAL_OCR_DRIVER=opper
MEDICAL_OCR_BASE_URL=https://api.opper.ai
MEDICAL_OCR_MODEL=mistral/mistral-ocr-latest
MEDICAL_METADATA_ENABLED=true
MEDICAL_METADATA_MODEL=deepseek-v4.1-flash
```

`MEDICAL_DISK` is empty by default, which means "the environment's default disk".
Laravel Cloud attaches a private bucket as the default and injects its
credentials, so nothing in a row or a config file names a disk that exists on
only one host. `medical_documents.disk` is nullable for the same reason.

Host changes required locally before step 5: both `upload_max_filesize` and
`post_max_size` in the PHP ini must be at least 64M. A 12-page clinic PDF exceeds
the 20M default. Laravel Cloud's base image is already generous.

## 10a. Laravel Cloud

- **Object storage**: attach a **private** bucket and make it the default disk,
  with the EU jurisdiction. `league/flysystem-aws-s3-v3` is required and is now in
  `composer.json`.
- **Queue**: add a **managed queue** named `default`. Cloud sets
  `QUEUE_CONNECTION=cloud`; every job dispatched without an explicit connection
  goes there, which is only `ProcessMedicalDocument`. Flex is enough.
- **Secrets**: `OPPER_API_KEY` belongs in Secrets, not a plain variable.
- **`APP_KEY`**: must match whatever encrypted an existing `dosars.cnp` value, or
  that column cannot be decrypted.
- **Not needed**: `storage:link`, `queue:restart`, `horizon:terminate`,
  `optimize:clear`.

## 10b. Image conversion across hosts

There is no single image tool present everywhere, so the normaliser tries, in
order: the `imagick` extension, the `magick` command, the `convert` command, then
macOS `sips`. A local Mac hits the extension; a Laravel Cloud PHP image has no
extension but does ship `magick` with libheif; a bare container may have none, in
which case a HEIC is refused with a clear message rather than stored unreadable.
A thumbnail that cannot be generated is simply omitted: it is never a reason to
lose a scan.

## 11. Where it lives

```
app/Medical/
  Console/Commands/CheckOcrConfiguration.php
  Enums/            DocumentCategory, DocumentStatus, MetadataState
  Http/Controllers/ DosarController, MedicalDocumentController, MedicalModuleController
  Http/Middleware/  EnsureMedicalModuleIsEnabled
  Http/Requests/    StoreDosarRequest, UpdateDosarRequest,
                    StoreDocumentsRequest, UpdateDocumentRequest
  Images/           ImageNormaliser, NormalisedImage
  Jobs/             ProcessMedicalDocument
  Metadata/         MetadataExtractor, OpperMetadataExtractor, MetadataManager
  Models/           Dosar, MedicalDocument, DocumentPage, Activity
  Ocr/              OcrEngine, OcrManager, OpperOcrEngine, NullOcrEngine,
                    OcrResult, OcrPage, OcrException
  Policies/         DosarPolicy, MedicalDocumentPolicy
  Providers/        MedicalServiceProvider
  Services/         DocumentStorage, ActivityLog, Search/{DocumentSearcher, SearchHit}
  Support/          OcrText
config/medical.php
database/migrations/2026_11_13_090000_add_modules_to_calendars_table.php
database/migrations/2026_11_13_090100_create_medical_tables.php
database/factories/Medical/
resources/js/pages/medical/
resources/js/components/medical/DocumentUploader.vue
resources/js/types/medical.ts
routes/medical.php
tests/Feature/Medical/
tests/Support/FakeOcrEngine.php
```

## 12. Decisions taken during the build

These changed from the first draft, or were settled while writing the code:

- **`dosars` became `medical_dosars`.** Every table is prefixed `medical_` so the
  next module never collides with a generic word.
- **Folder deletion is owner-only.** An earlier draft let any member delete,
  which would take every document in the folder with it.
- **Thumbnails are image-only.** Rasterising a PDF needs Ghostscript, which is
  not installed; PDFs open in the browser's viewer instead.
- **`ImageNormaliser` only converts convertible types.** PDF and JPEG pass
  through untouched rather than being forced through Imagick.
- **The upload request stores before it queues.** Storing is cheap and makes the
  document real; reading is the part that can fail and be retried.
- **`UploadedFile::fake()->create()` produces zero-byte files** in this Laravel
  version, so tests upload real bodies through the `fakePdf` and `fakeJpeg`
  helpers in `tests/Pest.php`.
  *. **The `medical` disk sets `serve => false`.** The existing `local` disk has
  `serve => true`, which registers a public `GET /storage/{path}` route.
- **Documents store a null disk**, meaning the environment's default. Laravel
  Cloud attaches a private bucket as the default and injects `FILESYSTEM_DISK`,
  so a hardcoded disk name would break on deployment.
- **`league/flysystem-aws-s3-v3` was missing** and is now required. Without it,
  `Storage::disk()` against any S3 disk throws at runtime rather than at build.

## 13. Out of scope in v1

Doctor share links, calendar-event linkage, backup automation, PDF thumbnails (they need Ghostscript, which is not installed), and a per-document permission model.
