<?php

namespace App\Medical\Models;

use App\Medical\Support\OcrText;
use App\Models\Calendar;
use App\Models\User;
use Database\Factories\Medical\DosarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A person's medical folder. It does not need a login: membership of the
 * household decides who may open it, so a baby's folder is just as available as
 * a parent's.
 *
 * @property int $id
 * @property int $calendar_id
 * @property string $name
 * @property Carbon|null $date_of_birth
 * @property string|null $cnp
 * @property string $color
 * @property string|null $notes
 * @property string $search_text
 * @property int|null $created_by_id
 * @property Carbon|null $archived_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> inCalendar(Calendar|int $calendar)
 */
#[Fillable(['calendar_id', 'name', 'date_of_birth', 'cnp', 'color', 'notes', 'created_by_id'])]
#[UseFactory(DosarFactory::class)]
class Dosar extends Model
{
    /** @use HasFactory<DosarFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'medical_dosars';

    /**
     * Only the household's owner may remove a folder.
     *
     * Deleting a folder takes every document in it with it, so it is narrower
     * than deleting one document. This lives on the model because the delete
     * route has no household in its URL to check against.
     */
    protected static function booted(): void
    {
        static::deleting(function (Dosar $dosar): void {
            $user = auth()->user();

            if ($user !== null && ! $dosar->calendar?->isManagedBy($user)) {
                abort(403, __('Only the owner of the household can remove a folder.'));
            }
        });

        static::creating(function (Dosar $dosar): void {
            $dosar->search_text = OcrText::fold($dosar->name);
        });

        static::updating(function (Dosar $dosar): void {
            $dosar->search_text = OcrText::fold($dosar->name);
        });
    }

    /**
     * Get the household the folder belongs to.
     *
     * @return BelongsTo<Calendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * Get the person who created the folder.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get everything filed in the folder.
     *
     * @return HasMany<MedicalDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class, 'medical_dosar_id');
    }

    /**
     * Limit the query to one household's folders.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInCalendar(Builder $query, Calendar|int $calendar): void
    {
        $query->where('calendar_id', $calendar instanceof Calendar ? $calendar->id : $calendar);
    }

    /**
     * The first letters of the person's name, for the folder avatar.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->squish()
            ->explode(' ')
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'cnp' => 'encrypted',
            'archived_at' => 'datetime',
        ];
    }
}
