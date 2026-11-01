<?php

namespace App\Models;

use App\Support\RecurrenceRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $calendar_id
 * @property string $title
 * @property string|null $notes
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $recurrence_rule
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'notes', 'starts_at', 'ends_at', 'recurrence_rule', 'calendar_id'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * Get the calendar the event appears on.
     *
     * @return BelongsTo<Calendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * Get the family member whose calendar the event is on.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The parsed recurrence rule, if this event repeats.
     */
    public function recurrence(): ?RecurrenceRule
    {
        return RecurrenceRule::fromRRule($this->recurrence_rule);
    }

    /**
     * Expand the event into occurrences that overlap the given range.
     *
     * Recurring events are expanded in the owner's timezone, while one-off
     * events are returned as they are.
     *
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function occurrencesBetween(CarbonInterface $rangeStart, CarbonInterface $rangeEnd): array
    {
        $startsAt = CarbonImmutable::instance($this->starts_at);
        $endsAt = CarbonImmutable::instance($this->ends_at);
        $rangeStart = CarbonImmutable::instance($rangeStart);
        $rangeEnd = CarbonImmutable::instance($rangeEnd);

        $rule = $this->recurrence();

        if ($rule === null) {
            return $startsAt->lessThan($rangeEnd) && $endsAt->greaterThan($rangeStart)
                ? [['starts_at' => $startsAt, 'ends_at' => $endsAt]]
                : [];
        }

        return $rule->occurrencesBetween(
            $startsAt,
            $endsAt,
            $rangeStart,
            $rangeEnd,
            $this->user->timezone,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
