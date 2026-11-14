<?php

namespace App\Medical\Enums;

/**
 * Whether the extracted metadata is waiting on a person or has been confirmed.
 */
enum MetadataState: string
{
    /** The extractor suggested values nobody has looked at yet. */
    case Suggested = 'suggested';

    /** Someone accepted the suggestions, or filled the fields in by hand. */
    case Accepted = 'accepted';

    /** The extractor was off, or produced nothing usable. */
    case None = 'none';
}
