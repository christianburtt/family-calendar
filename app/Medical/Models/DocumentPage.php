<?php

namespace App\Medical\Models;

use App\Medical\Support\OcrText;
use Database\Factories\Medical\DocumentPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One page of extracted text, with the layout blocks the OCR engine reported so
 * a later version can point at where a match sits on the scan.
 *
 * @property int $id
 * @property int $medical_document_id
 * @property int $page_number
 * @property string $markdown
 * @property list<array<string, mixed>>|null $blocks
 * @property string|null $search_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['medical_document_id', 'page_number', 'markdown', 'blocks', 'search_text'])]
#[UseFactory(DocumentPageFactory::class)]
class DocumentPage extends Model
{
    /** @use HasFactory<DocumentPageFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'medical_document_pages';

    /**
     * Get the document the page belongs to.
     *
     * @return BelongsTo<MedicalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(MedicalDocument::class, 'medical_document_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'blocks' => 'array',
        ];
    }

    /**
     * Keep the folded copy of the page in step with its text.
     */
    protected static function booted(): void
    {
        static::saving(function (DocumentPage $page): void {
            $page->search_text = OcrText::fold($page->markdown);
        });
    }
}
