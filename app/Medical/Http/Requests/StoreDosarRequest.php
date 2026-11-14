<?php

namespace App\Medical\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a person's folder.
 *
 * Two shapes arrive here: one person with their details, or the "create for
 * these people" shortcut carrying only names and colours. The CNP is optional
 * and stored encrypted: it is useful for telling two people apart and for
 * matching paperwork later, but it never needs to be searchable.
 */
class StoreDosarRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required_without:people', 'nullable', 'string', 'max:80'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'cnp' => ['nullable', 'string', 'digits:13'],
            'color' => ['required_with:name', 'nullable', 'string', Rule::in($this->palette())],
            'notes' => ['nullable', 'string', 'max:2000'],

            'people' => ['required_without:name', 'array', 'min:1', 'max:20'],
            'people.*.name' => ['required', 'string', 'max:80'],
            'people.*.color' => ['required', 'string', Rule::in($this->palette())],
        ];
    }

    /**
     * The colours a folder may use, matching the family palette.
     *
     * @return list<string>
     */
    private function palette(): array
    {
        return [
            '#dc2626', '#ea580c', '#d97706', '#16a34a', '#0d9488',
            '#0284c7', '#2563eb', '#7c3aed', '#c026d3', '#db2777',
        ];
    }
}
