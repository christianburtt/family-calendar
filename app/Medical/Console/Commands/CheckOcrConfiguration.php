<?php

namespace App\Medical\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Checks the OCR and metadata configuration before anything is uploaded.
 *
 * Model identifiers are provider-specific and change as vendors release new
 * versions. Getting that wrong only shows up when a household uploads a scan,
 * so this asks the gateway what it actually serves and compares.
 */
class CheckOcrConfiguration extends Command
{
    protected $signature = 'medical:check-ocr';

    protected $description = 'Check the medical OCR and metadata models against the configured gateway';

    public function handle(): int
    {
        $apiKey = (string) config('medical.ocr.api_key');

        if ($apiKey === '') {
            $this->error('No OPPER_API_KEY is set. OCR will fail until one is in .env.');

            return self::FAILURE;
        }

        $baseUrl = rtrim((string) config('medical.ocr.base_url'), '/');
        $model = (string) config('medical.ocr.model');

        $this->line('Gateway: '.$baseUrl);
        $this->line('OCR model: '.$model);

        $response = Http::withToken($apiKey)->acceptJson()->timeout(20)->get($baseUrl.'/v3/ocr/models');

        if ($response->failed()) {
            $this->error('Could not list OCR models (HTTP '.$response->status().').');

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $models */
        $models = $response->json('data') ?? [];

        $ids = array_filter(array_map(
            fn (array $entry): ?string => is_string($entry['id'] ?? null) ? $entry['id'] : null,
            $models,
        ));

        if (! in_array($model, $ids, true)) {
            $this->warn("MEDICAL_OCR_MODEL [{$model}] is not in the gateway's list.");
            $this->line('Available:');

            foreach ($ids as $id) {
                $this->line('  - '.$id);
            }
        } else {
            $this->info('OCR model is available.');
        }

        $metadataModel = (string) config('medical.metadata.model');

        if (config('medical.metadata.enabled')) {
            $this->line('Metadata model: '.$metadataModel);
            $this->line('Metadata extraction is on; it runs after OCR in the same job.');
        } else {
            $this->line('Metadata extraction is off.');
        }

        return self::SUCCESS;
    }
}
