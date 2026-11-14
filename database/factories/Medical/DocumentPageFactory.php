<?php

namespace Database\Factories\Medical;

use App\Medical\Models\DocumentPage;
use App\Medical\Models\MedicalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentPage>
 */
class DocumentPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medical_document_id' => MedicalDocument::factory(),
            'page_number' => 1,
            'markdown' => fake()->paragraph(),
        ];
    }

    /**
     * Number the page within its document.
     */
    public function numbered(int $number): static
    {
        return $this->state(fn (): array => ['page_number' => $number]);
    }
}
