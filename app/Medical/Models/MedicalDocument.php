<?php

namespace App\Medical\Models;

use App\Medical\Enums\DocumentCategory;
use App\Medical\Enums\DocumentStatus;
use App\Medical\Enums\MetadataState;
use App\Medical\Support\OcrText;
use App\Models\Calendar;
use App\Models\User;
use Database\Factories\Medical\MedicalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A scanned or photographed medical document, its extracted text and the
 * metadata suggested for it.
 *
 * The scan is the artefact that matters: OCR failing leaves the row intact with
 * a status the UI can retry, and a document is never rejected because its text
 * could not be read.
 *
 * @property int $id
 * @property int $calendar_id
 * @property int $medical_dosar_id
 * @property string $title
 * @property DocumentCategory $category
 * @property list<string>|null $tags
 * @property Carbon|null $document_date
 * @property string $original_name
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property string $sha256
 * @property string|null $thumbnail_path
 * @property DocumentStatus $status
 * @property string|null $ocr_error
 * @property string|null $ocr_model
 * @property int|null $ocr_pages
 * @property Carbon|null $ocr_edited_at
 * @property MetadataState $metadata_state
 * @property array<string, mixed>|null $metadata
 * @property int|null $uploaded_by_id
 * @property int|null $duplicate_of_id
 * @property string $search_text
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> inCalendar(Calendar|int $calendar)
 */
#[Fillable([
    'calendar_id',
    'medical_dosar_id',
    'title',
    'category',
    'tags',
    'document_date',
    'original_name',
    'disk',
    'path',
    'mime_type',
    'size',
    'sha256',
    'thumbnail_path',
    'status',
    'ocr_error',
    'ocr_model',
    'ocr_pages',
    'ocr_edited_at',
    'metadata_state',
    'metadata',
    'uploaded_by_id',
    'duplicate_of_id',
])]
#[UseFactory(MedicalDocumentFactory::class)]
class MedicalDocument extends Model
{
    /** @use HasFactory<MedicalDocumentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'medical_documents';

    /**
     * Get the household the document belongs to.
     *
     * @return BelongsTo<Calendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * Get the folder the document is filed in.
     *
     * @return BelongsTo<Dosar, $this>
     */
    public function dosar(): BelongsTo
    {
        return $this->belongsTo(Dosar::class, 'medical_dosar_id');
    }

    /**
     * Get the person who uploaded the document, if they are still around.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /**
     * Get the earlier copy this document duplicates.
     *
     * @return BelongsTo<self, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    /**
     * Get the extracted text, one row per page.
     *
     * @return HasMany<DocumentPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(DocumentPage::class)->orderBy('page_number');
    }

    /**
     * Limit the query to one household's documents.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInCalendar(Builder $query, Calendar|int $calendar): void
    {
        $query->where('calendar_id', $calendar instanceof Calendar ? $calendar->id : $calendar);
    }

    /**
     * Whether the file is a PDF rather than a photograph.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * The full text of every page, joined for display and editing.
     */
    public function fullText(): string
    {
        return $this->pages->pluck('markdown')->implode("\n\n");
    }

    /**
     * The storage disk the original lives on.
     */
    public function storage(): FilesystemAdapter
    {
        return Storage::disk($this->disk);
    }

    /**
     * Rebuild the folded text search matches against.
     *
     * Covers what the household would type: the title, the category's Romanian
     * label, every tag and the person's name. The OCR text lives in its own
     * folded column per page.
     */
    public function syncSearchText(): void
    {
        $label = $this->category->label();

        $parts = [
            $this->title,
            $label,
            ...($this->tags ?? []),
            $this->dosar?->name,
        ];

        $this->forceFill(['search_text' => OcrText::foldAll($parts)])->saveQuietly();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'tags' => 'array',
            'document_date' => 'date',
            'size' => 'integer',
            'ocr_pages' => 'integer',
            'ocr_edited_at' => 'datetime',
            'status' => DocumentStatus::class,
            'metadata_state' => MetadataState::class,
            'metadata' => 'array',
        ];
    }
}
