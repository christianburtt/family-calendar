<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { AlertTriangle, FileText, Image, RotateCcw } from '@lucide/vue';
import DocumentUploader from '@/components/medical/DocumentUploader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import medicalRoutes from '@/routes/medical';
import { show as showDocument } from '@/routes/medical/documents';
import type {
    MedicalCategoryOption,
    MedicalDocumentSummary,
    MedicalFolder,
    MedicalUploadLimits,
} from '@/types/medical';

type Props = {
    dosar: MedicalFolder;
    documents: MedicalDocumentSummary[];
    categories: MedicalCategoryOption[];
    upload: MedicalUploadLimits;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Medical', href: medicalRoutes.index() }],
    },
});

// While anything is still being read, ask for the page again.
const processing = props.documents.some((document) =>
    ['pending', 'processing'].includes(document.status),
);

usePoll(4000, { only: ['documents'] }, { autoStart: processing });

function destroy(document: MedicalDocumentSummary): void {
    const confirmed = window.confirm(`Move "${document.title}" to the bin?`);

    if (confirmed) {
        router.delete(`/medical/documents/${document.id}`, {
            preserveScroll: true,
        });
    }
}

function statusLabel(status: string): string {
    return status === 'failed' ? 'Could not read' : 'Reading…';
}
</script>

<template>
    <Head :title="dosar.name" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4">
        <div class="flex items-start gap-4">
            <span
                class="flex size-12 shrink-0 items-center justify-center rounded-full text-base font-semibold text-white"
                :style="{ backgroundColor: dosar.color }"
            >
                {{ dosar.initials }}
            </span>
            <div class="min-w-0 flex-1 space-y-1">
                <h1 class="truncate text-xl font-semibold tracking-tight">
                    {{ dosar.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ dosar.document_count }}
                    {{ dosar.document_count === 1 ? 'document' : 'documents' }}
                    <template v-if="dosar.date_of_birth">
                        · born {{ dosar.date_of_birth }}
                    </template>
                </p>
                <p v-if="dosar.notes" class="text-xs text-muted-foreground">
                    {{ dosar.notes }}
                </p>
            </div>
        </div>

        <DocumentUploader
            :dosar-id="dosar.id"
            :limits="upload"
            :categories="categories"
        />

        <section class="space-y-3">
            <h2 class="text-sm font-medium">Documents</h2>

            <p
                v-if="documents.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                data-test="no-documents"
            >
                Nothing filed yet. Photograph a referral, or drop in the PDF the
                clinic emailed.
            </p>

            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="document in documents"
                    :key="document.id"
                    class="flex items-start gap-3 p-3"
                    data-test="document-row"
                >
                    <div class="mt-0.5 text-muted-foreground">
                        <FileText v-if="document.is_pdf" class="size-4" />
                        <Image v-else class="size-4" />
                    </div>

                    <div class="min-w-0 flex-1 space-y-1">
                        <Link
                            :href="showDocument(document.id)"
                            class="block truncate text-sm font-medium hover:underline"
                        >
                            {{ document.title }}
                        </Link>

                        <div
                            class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                        >
                            <Badge variant="secondary">{{
                                document.category_label
                            }}</Badge>
                            <span v-if="document.document_date">{{
                                document.document_date
                            }}</span>
                            <span v-if="document.pages">
                                {{ document.pages }}
                                {{ document.pages === 1 ? 'page' : 'pages' }}
                            </span>
                            <span v-if="document.uploaded_by">
                                added by {{ document.uploaded_by }}
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                v-if="
                                    ['pending', 'processing'].includes(
                                        document.status,
                                    )
                                "
                                variant="secondary"
                            >
                                <RotateCcw class="animate-spin" />
                                {{ statusLabel(document.status) }}
                            </Badge>
                            <Badge
                                v-else-if="document.status === 'failed'"
                                variant="destructive"
                            >
                                <AlertTriangle />
                                Could not read — open to retry
                            </Badge>
                            <Badge
                                v-if="document.is_duplicate"
                                variant="outline"
                            >
                                Possible duplicate
                            </Badge>
                        </div>
                    </div>

                    <Button
                        v-if="document.can_delete"
                        variant="ghost"
                        size="sm"
                        @click="destroy(document)"
                    >
                        Remove
                    </Button>
                </li>
            </ul>
        </section>
    </div>
</template>
