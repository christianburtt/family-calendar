<?php

namespace App\Http\Resources;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @param  CarbonInterface|null  $occurrenceStartsAt  The expanded occurrence's start, or null for a one-off event.
     * @param  CarbonInterface|null  $occurrenceEndsAt  The expanded occurrence's end, or null for a one-off event.
     */
    public function __construct(
        Event $resource,
        private readonly ?CarbonInterface $occurrenceStartsAt = null,
        private readonly ?CarbonInterface $occurrenceEndsAt = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * Times are converted to the viewer's timezone and sent as wall-clock
     * strings, so the frontend never has to do timezone math.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = $request->user()->timezone;
        $startsAt = $this->occurrenceStartsAt ?? $this->starts_at;
        $endsAt = $this->occurrenceEndsAt ?? $this->ends_at;

        return [
            'id' => $this->id,
            'occurrence_id' => $this->id.'@'.$startsAt->copy()->utc()->format('Ymd\THis\Z'),
            'user_id' => $this->user_id,
            'title' => $this->title,
            'notes' => $this->notes,
            'starts_at' => $startsAt->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'ends_at' => $endsAt->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'series_starts_at' => $this->starts_at->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'series_ends_at' => $this->ends_at->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'recurrence_rule' => $this->recurrence_rule,
            'recurrence' => $this->recurrence()?->toArray($timezone),
        ];
    }
}
