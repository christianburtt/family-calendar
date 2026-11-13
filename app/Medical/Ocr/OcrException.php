<?php

namespace App\Medical\Ocr;

use RuntimeException;

/**
 * Something went wrong reading a document.
 *
 * The message is written for the person looking at the failed document, not for
 * the log, so it is deliberately plain.
 */
class OcrException extends RuntimeException
{
    /**
     * The engine could not be reached, or refused the request.
     */
    public static function requestFailed(string $reason): self
    {
        return new self(__('The OCR service could not read this file: :reason', ['reason' => $reason]));
    }

    /**
     * The engine answered, but with something that could not be understood.
     */
    public static function unreadableResponse(): self
    {
        return new self(__('The OCR service answered with something that could not be understood.'));
    }

    /**
     * The file itself is in a shape the engine cannot take.
     */
    public static function unsupportedFile(string $mimeType): self
    {
        return new self(__('This file type (:type) cannot be read automatically.', ['type' => $mimeType]));
    }
}
