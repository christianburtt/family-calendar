<?php

namespace App\Http\Requests;

use App\Support\RecurrenceRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    /**
     * The format of the wall-clock times submitted by the event form.
     */
    protected const string DATETIME_FORMAT = 'Y-m-d\TH:i';

    /**
     * The recurrence presets the event form can submit.
     *
     * @var list<string>
     */
    protected const array REPEATS = ['never', 'daily', 'weekly', 'monthly', 'yearly'];

    /**
     * Fill in the recurrence defaults so one-off events stay valid without extra fields.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'repeats' => $this->input('repeats', 'never'),
            'repeat_interval' => $this->input('repeat_interval', 1),
            'repeat_ends' => $this->input('repeat_ends', 'never'),
        ]);
    }

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
            'repeats' => ['required', Rule::in(self::REPEATS)],
            'repeat_interval' => ['required', 'integer', 'min:1', 'max:99'],
            'repeat_on' => [
                'array',
                'required_if:repeats,weekly',
            ],
            'repeat_on.*' => [Rule::in(RecurrenceRule::WEEKDAYS)],
            'repeat_ends' => ['required', Rule::in(['never', 'on', 'after'])],
            'repeat_until' => [
                'nullable',
                'required_if:repeat_ends,on',
                'date_format:Y-m-d',
            ],
            'repeat_count' => [
                'nullable',
                'required_if:repeat_ends,after',
                'integer',
                'min:1',
                'max:999',
            ],
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
            'repeat_on.required_if' => __('Pick at least one day to repeat on.'),
            'repeat_until.required_if' => __('Pick the date the event stops repeating.'),
            'repeat_count.required_if' => __('Choose how many times the event repeats.'),
        ];
    }

    /**
     * Get the validated event attributes, with times converted from the user's timezone to UTC.
     *
     * @return array{title: string, notes: string|null, starts_at: CarbonInterface, ends_at: CarbonInterface, recurrence_rule: string|null}
     */
    public function eventAttributes(): array
    {
        $timezone = $this->user()->timezone;

        return [
            'title' => $this->validated('title'),
            'notes' => $this->validated('notes'),
            'starts_at' => $this->date('starts_at', '!'.self::DATETIME_FORMAT, $timezone)->utc(),
            'ends_at' => $this->date('ends_at', '!'.self::DATETIME_FORMAT, $timezone)->utc(),
            'recurrence_rule' => $this->recurrenceRule($timezone)?->toString(),
        ];
    }

    /**
     * Build the stored RRULE from the form's structured recurrence fields.
     */
    private function recurrenceRule(string $timezone): ?RecurrenceRule
    {
        $frequency = $this->validated('repeats');

        if ($frequency === 'never') {
            return null;
        }

        $ends = $this->validated('repeat_ends');

        $until = $ends === 'on'
            ? CarbonImmutable::parse((string) $this->validated('repeat_until'), $timezone)->endOfDay()->utc()
            : null;

        $count = $ends === 'after' ? (int) $this->validated('repeat_count') : null;

        $days = $frequency === 'weekly'
            ? array_values((array) $this->validated('repeat_on'))
            : [];

        return RecurrenceRule::make(
            $frequency,
            (int) $this->validated('repeat_interval'),
            $days,
            $until,
            $count,
        );
    }
}
