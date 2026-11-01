<?php

namespace App\Models;

use App\Support\JoinCode;
use Database\Factories\CalendarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A shared calendar that a group of people can all see and add events to.
 *
 * @property int $id
 * @property int|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string $join_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'join_code', 'owner_id'])]
class Calendar extends Model
{
    /** @use HasFactory<CalendarFactory> */
    use HasFactory;

    /**
     * Get the person who created the calendar.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get everyone who has ever asked to join, approved or not.
     *
     * @return BelongsToMany<User, $this, CalendarUser>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(CalendarUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Get the people who are allowed to see and add to the calendar.
     *
     * @return BelongsToMany<User, $this, CalendarUser>
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', CalendarUser::STATUS_ACTIVE);
    }

    /**
     * Get the people whose requests are still waiting for approval.
     *
     * @return BelongsToMany<User, $this, CalendarUser>
     */
    public function pendingMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', CalendarUser::STATUS_PENDING);
    }

    /**
     * Get the events on the calendar.
     *
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Get the raw membership rows, including pending requests.
     *
     * @return HasMany<CalendarUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CalendarUser::class);
    }

    /**
     * Find a person's membership of this calendar, if they have one.
     */
    public function membershipFor(User $user): ?CalendarUser
    {
        return CalendarUser::query()
            ->where('calendar_id', $this->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Whether the person has been approved onto the calendar.
     */
    public function hasActiveMember(User $user): bool
    {
        return $this->activeMembers()->whereKey($user->id)->exists();
    }

    /**
     * Whether the person has asked to join but is still waiting.
     */
    public function hasPendingMember(User $user): bool
    {
        return $this->pendingMembers()->whereKey($user->id)->exists();
    }

    /**
     * Whether the person may approve members, remove them, or rotate the join code.
     *
     * A calendar whose owner has deleted their account is managed by its active members.
     */
    public function isManagedBy(User $user): bool
    {
        if ($this->owner_id === null) {
            return $this->hasActiveMember($user);
        }

        return $this->owner_id === $user->id;
    }

    /**
     * Replace the join code, which immediately invalidates the old one.
     */
    public function rotateJoinCode(): void
    {
        $this->forceFill(['join_code' => self::unusedJoinCode()])->save();
    }

    /**
     * Generate a join code that no calendar is using yet.
     */
    public static function unusedJoinCode(): string
    {
        do {
            $code = JoinCode::generate();
        } while (self::query()->where('join_code', $code)->exists());

        return $code;
    }

    /**
     * Build a URL-friendly slug from a calendar name, keeping it unique.
     */
    public static function unusedSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'calendar';
        $slug = $base;
        $suffix = 2;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
