<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $color
 * @property string $timezone
 * @property int|null $current_calendar_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'color', 'timezone', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the events on the user's calendar.
     *
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Get every calendar the user has asked to join, approved or not.
     *
     * @return BelongsToMany<Calendar, $this, CalendarUser>
     */
    public function calendars(): BelongsToMany
    {
        return $this->belongsToMany(Calendar::class)
            ->using(CalendarUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Get the calendars the user is approved onto.
     *
     * @return BelongsToMany<Calendar, $this, CalendarUser>
     */
    public function activeCalendars(): BelongsToMany
    {
        return $this->calendars()->wherePivot('status', CalendarUser::STATUS_ACTIVE);
    }

    /**
     * Get the calendars the user is waiting to be approved onto.
     *
     * @return BelongsToMany<Calendar, $this, CalendarUser>
     */
    public function pendingCalendars(): BelongsToMany
    {
        return $this->calendars()->wherePivot('status', CalendarUser::STATUS_PENDING);
    }

    /**
     * Resolve the calendar the user is currently looking at.
     *
     * Falls back to their oldest approved calendar when the stored choice is
     * missing or no longer accessible.
     */
    public function currentCalendar(): ?Calendar
    {
        if ($this->current_calendar_id !== null) {
            $calendar = $this->activeCalendars()
                ->whereKey($this->current_calendar_id)
                ->first();

            if ($calendar !== null) {
                return $calendar;
            }
        }

        return $this->activeCalendars()->orderBy('calendars.id')->first();
    }

    /**
     * Remember which calendar the user is looking at.
     */
    public function switchTo(Calendar $calendar): void
    {
        $this->forceFill(['current_calendar_id' => $calendar->id])->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
