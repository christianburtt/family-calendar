<?php

namespace App\Medical\Policies;

use App\Medical\Models\MedicalDocument;
use App\Models\Calendar;
use App\Models\User;

/**
 * Who may read, change and delete a medical document.
 *
 * Everyone in the household can read and re-label a document. Deleting is
 * narrower, because a wrong tap costs someone years of records: the person who
 * uploaded it, or the owner of the household, may remove it.
 */
class MedicalDocumentPolicy
{
    /**
     * Determine whether the user can open the document.
     */
    public function view(User $user, MedicalDocument $document): bool
    {
        return $this->isHouseholdMember($user, $document);
    }

    /**
     * Determine whether the user can change its title, date, category or text.
     */
    public function update(User $user, MedicalDocument $document): bool
    {
        return $this->isHouseholdMember($user, $document);
    }

    /**
     * Determine whether the user can delete and restore the document.
     */
    public function delete(User $user, MedicalDocument $document): bool
    {
        if (! $this->isHouseholdMember($user, $document)) {
            return false;
        }

        return $document->uploaded_by_id === $user->id
            || $document->calendar?->isManagedBy($user) === true;
    }

    /**
     * Whether the document's household has the module and the user is on it.
     */
    private function isHouseholdMember(User $user, MedicalDocument $document): bool
    {
        $calendar = $document->calendar;

        return $calendar !== null
            && $calendar->hasModule(Calendar::MODULE_MEDICAL)
            && $calendar->hasActiveMember($user);
    }
}
