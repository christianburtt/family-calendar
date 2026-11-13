<?php

namespace Database\Factories\Medical;

use App\Medical\Enums\DocumentCategory;
use App\Medical\Enums\DocumentStatus;
use App\Medical\Models\Dosar;
use App\Medical\Models\MedicalDocument;
use App\Models\Calendar;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MedicalDocument>
 */
class MedicalDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dosar = Dosar::factory();
        $name = 'scan-'.fake()->numerify('####').'.pdf';

        return [
            'calendar_id' => Calendar::factory(),
            'medical_dosar_id' => $dosar,
            'title' => 'Rețetă '.fake()->monthName(),
            'category' => fake()->randomElement(DocumentCategory::cases()),
            'document_date' => fake()->dateTimeBetween('-2 years')->format('Y-m-d'),
            'original_name' => $name,
            'disk' => 'medical',
            'path' => 'medical/'.Str::uuid().'/'.$name,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(40_000, 900_000),
            'sha256' => hash('sha256', Str::random(32)),
            'status' => DocumentStatus::Ready,
        ];
    }

    /**
     * File the document in the given folder and household.
     */
    public function inDosar(Dosar $dosar): static
    {
        return $this->state(fn (): array => [
            'medical_dosar_id' => $dosar->id,
            'calendar_id' => $dosar->calendar_id,
        ]);
    }

    /**
     * Leave the document waiting for OCR.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => DocumentStatus::Pending]);
    }

    /**
     * Mark OCR as having failed.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => DocumentStatus::Failed,
            'ocr_error' => 'The OCR service refused the file.',
        ]);
    }
}
