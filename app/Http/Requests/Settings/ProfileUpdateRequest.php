<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }

    /**
     * Replace a legacy timezone name with the zone it now stands for, so the
     * stored value can always be constructed on any PHP build.
     */
    protected function prepareForValidation(): void
    {
        $timezone = $this->input('timezone');

        if (is_string($timezone)) {
            $this->merge(['timezone' => $this->canonicalTimezone($timezone) ?? $timezone]);
        }
    }
}
