<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\TimezoneAliases;
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
     * Backward-compatible identifiers are accepted because some browsers still
     * report legacy names, such as "Asia/Calcutta". They are normalised to the
     * modern zone on save, so a stored value is always one the running PHP
     * build can construct.
     *
     * Laravel's built-in `timezone:all_with_bc` rule only knows the identifiers
     * compiled into the binary, so builds without the tzdb backward links
     * reject these names outright.
     *
     * @return array<int, Closure|Unique|ValidationRule|array<mixed>|string>
     */
    protected function timezoneRules(): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || $this->canonicalTimezone($value) === null) {
                    $fail('validation.timezone')->translate();
                }
            },
        ];
    }

    /**
     * Resolve an accepted timezone to a zone the running PHP build can use.
     *
     * Offsets ("+02:00"), abbreviations ("est") and junk resolve to null, so
     * they stay invalid. Legacy names resolve to their modern equivalent, so
     * the stored value is portable between builds.
     */
    protected function canonicalTimezone(string $value): ?string
    {
        if (in_array($value, timezone_identifiers_list(DateTimeZone::ALL), true)) {
            return $value;
        }

        $canonical = TimezoneAliases::CANONICAL[$value] ?? null;

        // Zones such as "Etc/UTC" are usable but not listed in ALL. Accept them
        // when they are plainly a named region, never an offset or abbreviation.
        if ($canonical === null) {
            if (preg_match('/^[A-Z][A-Za-z0-9_+\-]*(\/[A-Za-z0-9_+\-]+)+$/', $value) !== 1) {
                return null;
            }

            $canonical = $value;
        }

        // Storing a name the runtime cannot construct would break rendering.
        try {
            new DateTimeZone($canonical);
        } catch (Throwable) {
            return null;
        }

        return $canonical;
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
