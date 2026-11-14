<?php

namespace App\Medical\Http\Requests;

use App\Medical\Enums\DocumentCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Uploading one or more scans into a folder.
 *
 * Several files arrive together because that is how a visit comes home: six
 * pages photographed in a waiting room, dropped in at once.
 */
class StoreDocumentsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = ((int) config('medical.max_upload_mb')) * 1024;

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.config('medical.max_files_per_upload')],
            'files.*' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimetypes:'.implode(',', (array) config('medical.accepted_mime_types')),
            ],
            'category' => ['nullable', Rule::in(DocumentCategory::values())],
            'document_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Explain a rejection in terms of the household's own paperwork.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.*.mimetypes' => __('Medical files must be a PDF or a photo (JPEG, PNG, HEIC, TIFF, WEBP).'),
            'files.*.max' => __('Each file must be under :max MB.', ['max' => config('medical.max_upload_mb')]),
        ];
    }
}
