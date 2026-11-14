<?php

namespace App\Medical\Services;

use App\Medical\Models\Activity;
use App\Medical\Models\MedicalDocument;
use Illuminate\Http\Request;

/**
 * Records who opened or downloaded a medical document.
 *
 * The OCR text is deliberately not recorded, only the action: the log answers
 * "who looked at this" without becoming a second copy of the medical data.
 */
final class ActivityLog
{
    /**
     * Record that the current request touched the document.
     */
    public function record(Request $request, MedicalDocument $document, string $action): void
    {
        $user = $request->user();

        Activity::query()->create([
            'calendar_id' => $document->calendar_id,
            'medical_document_id' => $document->id,
            'user_id' => $user?->id,
            'action' => $action,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
