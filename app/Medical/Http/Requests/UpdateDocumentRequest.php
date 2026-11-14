<?php

namespace App\Medical\Http\Requests;

use App\Medical\Enums\DocumentCategory;
use App\Medical\Models\MedicalDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Changing what a document is called, or correcting its text.
 */
class UpdateDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof MedicalDocument && Gate::allows('update', $document);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(DocumentCategory::values())],
            'document_date' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'max:40'],
            'text' => ['nullable', 'string', 'max:200000'],
            'accept_metadata' => ['boolean'],
        ];
    }
}
