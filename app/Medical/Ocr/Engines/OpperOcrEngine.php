<?php

namespace App\Medical\Ocr\Engines;

use App\Medical\Models\MedicalDocument;
use App\Medical\Ocr\OcrEngine;
use App\Medical\Ocr\OcrException;
use App\Medical\Ocr\OcrPage;
use App\Medical\Ocr\OcrResult;
use Illuminate\Support\Facades\Http;

/**
 * Reads documents through an Opper gateway.
 *
 * Opper fronts several OCR models behind one EU-hosted, OpenAI-compatible API,
 * so the model is configuration rather than code: swap
 * MEDICAL_OCR_MODEL and the household is reading through a different vendor.
 * The file is sent inline as base64 so a private medical scan never needs a
 * public URL to be readable.
 */
class OpperOcrEngine implements OcrEngine
{
    /**
     * @param  array{base_url: string, api_key: string|null, model: string, timeout: int}  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * Send the stored original to the gateway and read back per-page markdown.
     */
    public function extract(MedicalDocument $document): OcrResult
    {
        $disk = $document->storage();

        if (! $disk->exists($document->path)) {
            throw OcrException::requestFailed(__('the stored file is missing'));
        }

        $request = [
            'model' => $this->config['model'],
            'document' => [
                'type' => 'base64',
                'content' => base64_encode((string) $disk->get($document->path)),
                'document_name' => $document->original_name,
            ],
            'parameters' => [
                'include_blocks' => true,
                'confidence_scores_granularity' => 'page',
            ],
        ];

        $response = Http::withToken((string) $this->config['api_key'])
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->post(rtrim($this->config['base_url'], '/').'/v3/ocr', $request);

        if ($response->failed()) {
            logger()->error('Opper OCR request failed: '.$response->status().' '.$response->body());
            throw OcrException::requestFailed(
                trim($response->json('error.message') ?? 'HTTP '.$response->status())
            );
        }

        $pages = $response->json('pages');

        if (! is_array($pages)) {
            throw OcrException::unreadableResponse();
        }

        $result = [];

        foreach ($pages as $index => $page) {
            $result[] = new OcrPage(
                number: (int) ($page['index'] ?? $index) + 1,
                markdown: (string) ($page['markdown'] ?? ''),
                blocks: is_array($page['blocks'] ?? null) ? array_values($page['blocks']) : null,
            );
        }

        return new OcrResult(
            pages: $result !== [] ? $result : [new OcrPage(number: 1, markdown: '')],
            model: $response->json('model') ?? $this->config['model'],
        );
    }
}
