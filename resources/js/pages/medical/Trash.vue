<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, RotateCcw, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import medicalRoutes from '@/routes/medical';
import { restore as restoreDocument } from '@/routes/medical/documents';

type TrashedDocument = {
    id: number;
    title: string;
    deleted_at: string | null;
    dosar: string | null;
    can_restore: boolean;
};

type Props = {
    restore_days: number;
    documents: TrashedDocument[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Medical', href: medicalRoutes.index() }],
    },
});

function restore(document: TrashedDocument): void {
    router.post(restoreDocument(document.id).url, {}, { preserveScroll: true });
}

function formatDate(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '—';
}
</script>

<template>
    <Head title="Bin" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <div class="flex items-start gap-3">
            <Button variant="ghost" size="icon" as-child>
                <Link :href="medicalRoutes.index()">
                    <ArrowLeft />
                </Link>
            </Button>
            <div class="space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">Bin</h1>
                <p class="text-sm text-muted-foreground">
                    Deleted documents stay here for
                    {{ props.restore_days }} days. After that the file goes too.
                </p>
            </div>
        </div>

        <p
            v-if="documents.length === 0"
            class="flex flex-col items-center gap-2 rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            <Trash2 class="size-5" />
            Nothing has been deleted.
        </p>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="document in documents"
                :key="document.id"
                class="flex items-center gap-3 p-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ document.title }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ document.dosar }} · deleted
                        {{ formatDate(document.deleted_at) }}
                    </p>
                </div>

                <Button
                    v-if="document.can_restore"
                    variant="outline"
                    size="sm"
                    @click="restore(document)"
                >
                    <RotateCcw />
                    Restore
                </Button>
            </li>
        </ul>
    </div>
</template>
