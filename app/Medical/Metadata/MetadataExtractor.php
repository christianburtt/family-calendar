<?php

namespace App\Medical\Metadata;

/**
 * Suggests the details of a document from its extracted text.
 *
 * Suggestions are exactly that: they are stored as proposals until somebody
 * accepts them, because a wrong date on a lab result is worse than an empty
 * one.
 */
interface MetadataExtractor
{
    /**
     * Read the text and suggest a title, category, date and clinic.
     */
    public function extract(string $markdown): ?DocumentMetadata;
}
