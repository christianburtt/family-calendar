<?php

namespace App\Medical\Metadata;

/**
 * What the extractor thinks a document is.
 *
 * Every field is optional: an extractor that is unsure should return nothing
 * rather than guess, and a suggested date on a lab result that is wrong by a
 * year is worse than a blank one.
 */
final readonly class DocumentMetadata
{
    /**
     * @param  list<string>|null  $tags
     */
    public function __construct(
        public ?string $title = null,
        public ?string $category = null,
        public ?string $documentDate = null,
        public ?string $clinic = null,
        public ?string $doctor = null,
        public ?array $tags = null,
    ) {}

    /**
     * Whether the extractor came back with anything worth showing.
     */
    public function isMeaningful(): bool
    {
        return $this->title !== null
            || $this->category !== null
            || $this->documentDate !== null
            || $this->clinic !== null
            || $this->doctor !== null;
    }

    /**
     * The shape stored on the document.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'category' => $this->category,
            'document_date' => $this->documentDate,
            'clinic' => $this->clinic,
            'doctor' => $this->doctor,
            'tags' => $this->tags,
        ], fn (mixed $value): bool => $value !== null);
    }
}
