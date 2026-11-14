<?php

namespace App\Medical\Enums;

/**
 * The kinds of paperwork a Romanian household accumulates.
 *
 * A closed set keeps filters trivial and gives the metadata extractor a finite
 * list to choose from, which it is far more reliable at than an open vocabulary.
 */
enum DocumentCategory: string
{
    case Prescription = 'prescription';
    case Referral = 'referral';
    case LabResults = 'lab_results';
    case MedicalLetter = 'medical_letter';
    case Imaging = 'imaging';
    case Consultation = 'consultation';
    case Certificate = 'certificate';
    case SickLeave = 'sick_leave';
    case Invoice = 'invoice';
    case Other = 'other';

    /**
     * The Romanian label printed on this kind of document.
     *
     * The UI is English; these are the words a household recognises on paper,
     * and the values the metadata extractor is allowed to produce.
     */
    public function label(): string
    {
        return match ($this) {
            self::Prescription => 'Rețetă',
            self::Referral => 'Bilet de trimitere',
            self::LabResults => 'Analize',
            self::MedicalLetter => 'Scrisoare medicală',
            self::Imaging => 'Imagistică',
            self::Consultation => 'Consultație',
            self::Certificate => 'Adeverință',
            self::SickLeave => 'Concediu medical',
            self::Invoice => 'Factură',
            self::Other => 'Altele',
        };
    }

    /**
     * The values the metadata extractor may return.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $category): string => $category->value, self::cases());
    }
}
