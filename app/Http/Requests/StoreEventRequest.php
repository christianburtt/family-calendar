<?php

namespace App\Http\Requests;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    /**
     * The format of the wall-clock times submitted by the event form.
     */
    protected const string DATETIME_FORMAT = 'Y-m-d\TH:i';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date_format:'.self::DATETIME_FORMAT],
            'ends_at' => ['required', 'date_format:'.self::DATETIME_FORMAT, 'after:starts_at'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after' => __('The event must end after it starts.'),
        ];
    }

    /**
     * Get the validated event attributes, with times converted from the user's timezone to UTC.
     *
     * @return array{title: string, notes: string|null, starts_at: CarbonInterface, ends_at: CarbonInterface}
     */
    public function eventAttributes(): array
    {
        $timezone = $this->user()->timezone;

        return [
            'title' => $this->validated('title'),
            'notes' => $this->validated('notes'),
            'starts_at' => $this->date('starts_at', '!'.self::DATETIME_FORMAT, $timezone)->utc(),
            'ends_at' => $this->date('ends_at', '!'.self::DATETIME_FORMAT, $timezone)->utc(),
        ];
    }
}
