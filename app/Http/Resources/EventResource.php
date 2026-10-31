<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
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

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'notes' => $this->notes,
            'starts_at' => $this->starts_at->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at->setTimezone($timezone)->format('Y-m-d\TH:i'),
        ];
    }
}
