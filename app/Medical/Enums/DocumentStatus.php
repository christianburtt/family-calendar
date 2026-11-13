<?php

namespace App\Medical\Enums;

/**
 * How far a document has got through OCR.
 */
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    /**
     * Whether OCR has finished, successfully or not.
     */
    public function isSettled(): bool
    {
        return $this === self::Ready || $this === self::Failed;
    }

    /**
     * Whether the document is waiting on the queue or being read right now.
     */
    public function isBusy(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}
