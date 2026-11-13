<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FileText,
    FolderOpen,
    Plus,
    RotateCcw,
    Search,
    Trash2,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import medicalRoutes from '@/routes/medical';
import { create as createDosar, show as showDosar } from '@/routes/medical/dosars';
import { show as showDocument } from '@/routes/medical/documents';
import type { MedicalDosarSummary, MedicalSearchResult } from '@/types/medical';

type Props = {
    dosars: MedicalDosarSummary[];
    search: string;
    results: MedicalSearchResult[];
    can_create: boolean;
    trash_count: number;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Medical', href: medicalRoutes.index() }],
    },
});

const term = ref(props.search);
let timeout: number | undefined;

watch(term, (value) => {
    window.clearTimeout(timeout);

    timeout = window.setTimeout(() => {
        router.get(
            medicalRoutes.index().url,
            value.trim() === '' ? {} : { q: value.trim() },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Medical" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-8 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">Medical</h1>
                <p class="text-sm text-muted-foreground">
                    One folder per person. Every page's text is searchable.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="trash_count > 0"
                    variant="ghost"
                    size="sm"
                    as-child
                >
                    <Link :href="medicalRoutes.trash()">
                        <Trash2 />
                        Bin ({{ trash_count }})
                    </Link>
                </Button>
                <Button v-if="can_create" as-child>
                    <Link :href="createDosar()">
                        <Plus />
                        New folder
                    </Link>
                </Button>
            </div>
        </div>

        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="term"
                type="search"
                placeholder="Search every folder — diagnoses, clinics, drug names…"
                class="pl-9"
                data-test="medical-search"
            />
        </div>

        <section
            v-if="search.trim() !== ''"
            class="space-y-3"
            data-test="search-results"
        >
            <p class="text-xs text-muted-foreground">
                {{ results.length }}
                {{ results.length === 1 ? 'document' : 'documents' }} found
            </p>

            <ul v-if="results.length > 0" class="divide-y rounded-lg border">
                <li v-for="result in results" :key="result.id">
                    <Link
                        :href="showDocument(result.id)"
                        class="flex flex-col gap-1 p-3 hover:bg-muted/50"
                        data-test="search-result"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="size-2.5 rounded-full"
                                :style="{ backgroundColor: result.dosar.color }"
                            />
                            <span class="text-sm font-medium">{{
                                result.title
                            }}</span>
                            <Badge variant="secondary">{{
                                result.category_label
                            }}</Badge>
                            <span class="text-xs text-muted-foreground">{{
                                result.dosar.name
                            }}</span>
                            <span
                                v-if="result.document_date"
                                class="text-xs text-muted-foreground"
                                >{{ result.document_date }}</span
                            >
                            <span
                                v-if="result.matched_page"
                                class="text-xs text-muted-foreground"
                                >p. {{ result.matched_page }}</span
                            >
                        </div>
                        <p
                            v-if="result.snippet"
                            class="line-clamp-2 text-xs text-muted-foreground"
                        >
                            {{ result.snippet }}
                        </p>
                    </Link>
                </li>
            </ul>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                Nothing matched. Try a word from the document itself, or the
                person's name.
            </p>
        </section>

        <section v-else class="space-y-3">
            <div
                v-if="dosars.length === 0"
                class="flex flex-col items-center gap-3 rounded-lg border border-dashed p-10 text-center"
                data-test="empty-state"
            >
                <FolderOpen class="size-6 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">
                    No folders yet. Create one for each person whose paperwork
                    lives here.
                </p>
                <Button v-if="can_create" as-child>
                    <Link :href="createDosar()">
                        <Plus />
                        New folder
                    </Link>
                </Button>
            </div>

            <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="dosar in dosars" :key="dosar.id">
                    <Link
                        :href="showDosar(dosar.id)"
                        class="flex h-full flex-col gap-3 rounded-lg border p-4 transition-colors hover:bg-muted/50"
                        data-test="dosar-card"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-9 items-center justify-center rounded-full text-sm font-semibold text-white"
                                :style="{ backgroundColor: dosar.color }"
                            >
                                {{ dosar.initials }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ dosar.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ dosar.document_count }}
                                    {{
                                        dosar.document_count === 1
                                            ? 'document'
                                            : 'documents'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-auto flex flex-wrap items-center gap-2">
                            <Badge
                                v-if="dosar.processing > 0"
                                variant="secondary"
                            >
                                <RotateCcw class="animate-spin" />
                                {{ dosar.processing }} reading
                            </Badge>
                            <span
                                v-if="dosar.latest_document_date"
                                class="flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <FileText class="size-3" />
                                {{ dosar.latest_document_date }}
                            </span>
                        </div>
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
