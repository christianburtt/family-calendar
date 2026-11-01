<?php

namespace App\Concerns;

use App\Models\User;
use Closure;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Throwable;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, Closure|Unique|ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'color' => $this->colorRules(),
            'timezone' => $this->timezoneRules(),
        ];
    }

    /**
     * Get the validation rules used to validate a user's calendar color.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function colorRules(): array
    {
        return ['required', 'string', 'hex_color', 'size:7'];
    }

    /**
     * Get the validation rules used to validate user timezones.
     *
     * Backward-compatible identifiers are allowed because some browsers still
     * report legacy names, such as "Asia/Calcutta".
     *
     * Laravel's built-in `timezone:all_with_bc` rule only knows the identifiers
     * compiled into the running PHP build, so a build without the backward
     * links rejects names the browser happily reports. This accepts any named
     * zone PHP can actually resolve, while still refusing offsets ("+02:00"),
     * lowercase shortcuts ("est") and nonsense.
     *
     * @return array<int, Closure|Unique|ValidationRule|array<mixed>|string>
     */
    protected function timezoneRules(): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! $this->isValidTimezone($value)) {
                    $fail('validation.timezone')->translate();
                }
            },
        ];
    }

    /**
     * Whether the value is a timezone identifier PHP can resolve.
     */
    protected function isValidTimezone(string $value): bool
    {
        if (in_array($value, timezone_identifiers_list(DateTimeZone::ALL), true)) {
            return true;
        }

        // Only named identifiers, so offsets and lowercase aliases stay invalid.
        // A bare "Z" is a UTC shortcut, not a zone name, so require a region.
        if (preg_match('/^[A-Z][A-Za-z0-9_+\-]*(\/[A-Za-z0-9_+\-]+)+$/', $value) !== 1) {
            return false;
        }

        try {
            new DateTimeZone($value);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
