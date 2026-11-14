<?php

namespace App\Medical\Ocr;

/**
 * Everything an OCR run produced.
 */
final readonly class OcrResult
{
    /**
     * @param  list<OcrPage>  $pages
     */
    public function __construct(
        public array $pages,
        public ?string $model = null,
    ) {}

    /**
     * The number of pages the engine read.
     */
    public function pageCount(): int
    {
        return count($this->pages);
    }

    /**
     * Whether the run produced no text at all.
     *
     * A scan can come back empty without the engine failing: a blank page, or a
     * form where only handwriting matters. That is not an error worth throwing.
     */
    public function isEmpty(): bool
    {
        foreach ($this->pages as $page) {
            if (trim($page->markdown) !== '') {
                return false;
            }
        }

        return true;
    }
}
