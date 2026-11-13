<?php

namespace App\Medical\Images;

/**
 * A converted copy of an upload.
 */
final readonly class NormalisedImage
{
    public function __construct(
        public string $mimeType,
        public string $bytes,
    ) {}

    /**
     * The extension the converted copy should carry.
     */
    public function extension(): string
    {
        return $this->mimeType === 'image/jpeg' ? 'jpg' : 'bin';
    }
}
