<?php

namespace App\Medical\Http\Requests;

use App\Medical\Models\Dosar;
use Illuminate\Support\Facades\Gate;

class UpdateDosarRequest extends StoreDosarRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $dosar = $this->route('dosar');

        return $dosar instanceof Dosar && Gate::allows('update', $dosar);
    }
}
