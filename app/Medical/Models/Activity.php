<?php

namespace App\Medical\Models;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A record of someone opening or downloading a medical document.
 *
 * This is the one place whose whole purpose is to answer "who looked at this",
 * so it keeps working when the document or the person is gone: the foreign keys
 * null out and the row survives.
 *
 * @property int $id
 * @property int $calendar_id
 * @property int|null $medical_document_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
#[Fillable(['calendar_id', 'medical_document_id', 'user_id', 'action', 'ip', 'user_agent'])]
class Activity extends Model
{
    /**
     * Someone opened a document's details.
     */
    public const string ACTION_VIEW = 'view';

    /**
     * Someone downloaded the original file.
     */
    public const string ACTION_DOWNLOAD = 'download';

    /**
     * Someone opened the file inline, to read it.
     */
    public const string ACTION_PREVIEW = 'preview';

    /**
     * The table associated with the model.
     */
    protected $table = 'medical_activity';

    /**
     * Activity has no updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * Get the document that was looked at.
     *
     * @return BelongsTo<MedicalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(MedicalDocument::class, 'medical_document_id');
    }

    /**
     * Get the person who looked.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the household the activity happened in.
     *
     * @return BelongsTo<Calendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }
}
