import type { LucideIcon } from '@lucide/vue';

/**
 * A person's folder as the grid shows it.
 */
export type MedicalDosarSummary = {
    id: number;
    name: string;
    initials: string;
    color: string;
    date_of_birth: string | null;
    document_count: number;
    latest_document_date: string | null;
    processing: number;
};

/**
 * One document in a set of search results.
 */
export type MedicalSearchResult = {
    id: number;
    title: string;
    category: string;
    category_label: string;
    document_date: string | null;
    status: string;
    dosar: {
        id: number;
        name: string;
        color: string;
    };
    matched_page: number | null;
    snippet: string | null;
};

/**
 * A document in the folder list.
 */
export type MedicalDocumentSummary = {
    id: number;
    title: string;
    category: string;
    category_label: string;
    document_date: string | null;
    status: string;
    is_pdf: boolean;
    pages: number | null;
    uploaded_by: string | null;
    can_delete: boolean;
    is_duplicate: boolean;
};

export type MedicalCategoryOption = {
    value: string;
    label: string;
};

export type MedicalFolder = {
    id: number;
    name: string;
    initials: string;
    color: string;
    date_of_birth: string | null;
    notes: string | null;
    document_count: number;
    can_delete: boolean;
};

export type MedicalUploadLimits = {
    max_mb: number;
    max_files: number;
    accept: string;
};

export type MedicalActivityEntry = {
    action: string;
    user: string | null;
    at: string | null;
};

export type MedicalIcon = LucideIcon;
