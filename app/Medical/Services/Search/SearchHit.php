<?php

namespace App\Medical\Services\Search;

use App\Medical\Models\MedicalDocument;

/**
 * One document in a set of search results.
 */
final readonly class SearchHit
{
    public function __construct(
        public MedicalDocument $document,
        public ?int $matchedPage,
        public ?string $snippet,
    ) {}

    /**
     * The shape the results list expects.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->document->id,
            'title' => $this->document->title,
            'category' => $this->document->category->value,
            'category_label' => $this->document->category->label(),
            'document_date' => $this->document->document_date?->toDateString(),
            'status' => $this->document->status->value,
            'dosar' => [
                'id' => $this->document->dosar->id,
                'name' => $this->document->dosar->name,
                'color' => $this->document->dosar->color,
            ],
            'matched_page' => $this->matchedPage,
            'snippet' => $this->snippet,
        ];
    }
}
