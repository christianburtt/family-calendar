<?php

namespace App\Medical\Ocr;

use App\Medical\Models\MedicalDocument;

/**
 * Reads the text out of a stored document.
 *
 * The application never talks to an OCR vendor directly: it asks this contract
 * for text. Which vendor answers is configuration, so a household running this
 * as open source can point it at whatever endpoint they already pay for.
 */
interface OcrEngine
{
    /**
     * Extract the text of every page of the document.
     *
     * @throws OcrException when the engine cannot read the file
     */
    public function extract(MedicalDocument $document): OcrResult;
}
