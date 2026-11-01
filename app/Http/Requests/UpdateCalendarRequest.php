<?php

namespace App\Http\Requests;

class UpdateCalendarRequest extends StoreCalendarRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('calendar'));
    }
}
