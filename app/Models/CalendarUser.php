<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A person's membership of a calendar, which may still be awaiting approval.
 *
 * @property int $id
 * @property int $calendar_id
 * @property int $user_id
 * @property string $role
 * @property string $status
 */
#[Fillable(['calendar_id', 'user_id', 'role', 'status'])]
class CalendarUser extends Pivot
{
    /**
     * The role of the person who created the calendar.
     */
    public const string ROLE_OWNER = 'owner';

    /**
     * The role of everyone else on the calendar.
     */
    public const string ROLE_MEMBER = 'member';

    /**
     * A membership that has been requested but not approved yet.
     */
    public const string STATUS_PENDING = 'pending';

    /**
     * A membership that can see and add to the calendar.
     */
    public const string STATUS_ACTIVE = 'active';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'calendar_user';

    /**
     * The pivot table has its own auto-incrementing key.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Get the calendar being joined.
     *
     * @return BelongsTo<Calendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * Get the person joining.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this membership belongs to the calendar's owner.
     */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /**
     * Whether this membership has been approved.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Whether this membership is still waiting for approval.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
