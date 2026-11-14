<?php

namespace App\Medical\Ocr\Engines;

use App\Medical\Models\MedicalDocument;
use App\Medical\Ocr\OcrEngine;
use App\Medical\Ocr\OcrPage;
use App\Medical\Ocr\OcrResult;

/**
 * An engine that reads nothing.
 *
 * The module is fully usable without an OCR subscription: documents upload,
 * file, get previewed and can have their text typed or pasted in by hand. This
 * is also what the test suite binds so no test ever reaches the network.
 */
class NullOcrEngine implements OcrEngine
{
    /**
     * Return one empty page, so the document still has somewhere to hold text.
     */
    public function extract(MedicalDocument $document): OcrResult
    {
        return new OcrResult(
            pages: [new OcrPage(number: 1, markdown: '')],
            model: 'none',
        );
    }
}
