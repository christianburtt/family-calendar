<?php

namespace App\Medical\Ocr;

use App\Medical\Ocr\Engines\NullOcrEngine;
use App\Medical\Ocr\Engines\OpperOcrEngine;
use Illuminate\Support\Manager;

/**
 * Builds the OCR engine the household's configuration asks for.
 *
 * Adding a vendor means adding one factory method here. Nothing else in the
 * module knows which engine is in play.
 */
class OcrManager extends Manager
{
    /**
     * The engine to fall back to when none is configured.
     */
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('medical.ocr.driver', 'null');
    }

    /**
     * An engine that reads nothing, for development and tests.
     */
    public function createNullDriver(): OcrEngine
    {
        return new NullOcrEngine;
    }

    /**
     * The Opper gateway, in front of whichever OCR model is configured.
     */
    public function createOpperDriver(): OcrEngine
    {
        /** @var array{base_url: string, api_key: string|null, model: string, timeout: int} $config */
        $config = $this->config->get('medical.ocr');

        return new OpperOcrEngine($config);
    }
}
