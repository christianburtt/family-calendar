<?php

namespace App\Medical\Metadata;

class NullMetadataExtractor implements MetadataExtractor
{
    /**
     * Suggest nothing.
     */
    public function extract(string $markdown): ?DocumentMetadata
    {
        return null;
    }
}
