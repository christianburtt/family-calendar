<?php

namespace App\Medical\Metadata;

use App\Medical\Enums\DocumentCategory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads the OCR text with a small, cheap model and asks for a fixed shape back.
 *
 * The model is configuration (MEDICAL_METADATA_MODEL), so the same code works
 * against Opper, OpenAI or anything else that speaks the OpenAI chat API. The
 * category is constrained to the document enum, because a free-text label would
 * come back inventing near-duplicates the filters could never group.
 */
class OpperMetadataExtractor implements MetadataExtractor
{
    /**
     * @param  array{base_url: string, api_key: string|null, model: string, timeout: int}  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * Ask the model what the document is, returning null when it cannot say.
     */
    public function extract(string $markdown): ?DocumentMetadata
    {
        $markdown = trim($markdown);

        if ($markdown === '') {
            return null;
        }

        $response = Http::withToken((string) $this->config['api_key'])
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->post(rtrim($this->config['base_url'], '/').'/compat/chat/completions', [
                'model' => $this->config['model'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->instructions(),
                    ],
                    [
                        'role' => 'user',
                        'content' => mb_substr($markdown, 0, 12_000),
                    ],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'medical_document',
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Medical metadata extraction failed.', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            return null;
        }

        return $this->parse($content);
    }

    /**
     * Turn the model's JSON into a suggestion, guarding every field.
     */
    private function parse(string $content): ?DocumentMetadata
    {
        /** @var mixed $decoded */
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return null;
        }

        $category = $decoded['category'] ?? null;
        $date = $decoded['document_date'] ?? null;

        return new DocumentMetadata(
            title: $this->string($decoded['title'] ?? null, 120),
            category: in_array($category, DocumentCategory::values(), true) ? $category : null,
            documentDate: $this->date($date),
            clinic: $this->string($decoded['clinic'] ?? null, 120),
            doctor: $this->string($decoded['doctor'] ?? null, 120),
            tags: $this->tags($decoded['tags'] ?? null),
        );
    }

    /**
     * A trimmed string, or null when it is missing or empty.
     */
    private function string(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * An ISO date, or null when the model produced something else.
     */
    private function date(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    /**
     * A short list of tags.
     *
     * @return list<string>|null
     */
    private function tags(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $tags = [];

        foreach (array_slice($value, 0, 8) as $tag) {
            $clean = $this->string($tag, 40);

            if ($clean !== null) {
                $tags[] = $clean;
            }
        }

        return $tags === [] ? null : $tags;
    }

    /**
     * The prompt. Deliberately short: the model gets the text, not an argument.
     */
    private function instructions(): string
    {
        return implode("\n", [
            'You read Romanian medical paperwork and return facts about one document.',
            'The text comes from OCR, so it may contain errors; prefer silence over a guess.',
            'Return a short human title in the document\'s own language (for example "Rețetă - Dr. Popescu").',
            'Pick the category from the allowed list.',
            'document_date is the date printed on the document as YYYY-MM-DD, never today\'s date. Omit it if you cannot see one.',
            'clinic and doctor are the issuer when they are visible.',
            'tags are up to 5 short keywords such as a diagnosis or a test name.',
        ]);
    }

    /**
     * The JSON schema the response is constrained to.
     *
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'maxLength' => 120],
                'category' => ['type' => 'string', 'enum' => DocumentCategory::values()],
                'document_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, or omit'],
                'clinic' => ['type' => 'string', 'maxLength' => 120],
                'doctor' => ['type' => 'string', 'maxLength' => 120],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 5],
            ],
            'required' => ['title', 'category'],
        ];
    }
}
