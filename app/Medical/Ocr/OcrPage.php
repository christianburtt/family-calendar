<?php

namespace App\Medical\Ocr;

/**
 * One page of text as an OCR engine reported it.
 */
final readonly class OcrPage
{
    /**
     * @param  list<array<string, mixed>>|null  $blocks  Layout blocks, in reading order, when the engine reports them.
     */
    public function __construct(
        public int $number,
        public string $markdown,
        public ?array $blocks = null,
    ) {}
}
