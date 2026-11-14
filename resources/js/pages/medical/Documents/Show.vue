<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Download,
    ExternalLink,
    Loader2,
    RotateCcw,
    Save,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import medicalRoutes from '@/routes/medical';
import { show as showDosar } from '@/routes/medical/dosars';
import {
    destroy as destroyDocument,
    download as downloadDocument,
    file as fileDocument,
    retry as retryDocument,
    update as updateDocument,
} from '@/routes/medical/documents';
import type {
    MedicalActivityEntry,
    MedicalCategoryOption,
} from '@/types/medical';

type DocumentProps = {
    id: number;
    title: string;
    category: string;
    category_label: string;
    document_date: string | null;
    tags: string[];
    status: 'pending' | 'processing' | 'ready' | 'failed';
    ocr_error: string | null;
    ocr_model: string | null;
    ocr_pages: number | null;
    ocr_edited_at: string | null;
    is_pdf: boolean;
    size: number;
    original_name: string;
    uploaded_by: string | null;
    created_at: string | null;
    metadata: Record<string, unknown> | null;
    metadata_state: 'suggested' | 'accepted' | 'none';
    can_delete: boolean;
    is_duplicate: boolean;
    duplicate_of: number | null;
};

type Props = {
    document: DocumentProps;
    dosar: { id: number; name: string; color: string };
    pages: { number: number; markdown: string }[];
    categories: MedicalCategoryOption[];
    activity: MedicalActivityEntry[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Medical', href: medicalRoutes.index() }],
    },
});

const fullText = computed(() =>
    props.pages.map((page) => page.markdown).join('\n\n'),
);

const editing = ref(false);

const form = useForm({
    title: props.document.title,
    category: props.document.category,
    document_date: props.document.document_date ?? '',
    tags: props.document.tags.join(', '),
    text: fullText.value,
    accept_metadata: false,
});

const suggested = computed(() =>
    props.document.metadata_state === 'suggested' && props.document.metadata
        ? props.document.metadata
        : null,
);

function save(): void {
    form.tags = form.tags
        .split(',')
        .map((tag) => tag.trim())
        .filter((tag) => tag !== '') as unknown as string;

    form.transform((data) => ({
        ...data,
        tags: (data.tags as unknown as string)
            .split(',')
            .map((tag) => tag.trim())
            .filter((tag) => tag !== ''),
    })).patch(updateDocument(props.document.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
        },
    });
}

function acceptSuggestions(): void {
    const metadata = props.document.metadata ?? {};

    form.title = (metadata.title as string) ?? form.title;
    form.category = (metadata.category as string) ?? form.category;
    form.document_date =
        (metadata.document_date as string) ?? form.document_date;
    form.tags = ((metadata.tags as string[]) ?? []).join(', ');
    form.accept_metadata = true;

    save();
}

function retry(): void {
    router.post(
        retryDocument(props.document.id).url,
        {},
        { preserveScroll: true },
    );
}

function destroy(): void {
    if (window.confirm(`Move "${props.document.title}" to the bin?`)) {
        router.delete(destroyDocument(props.document.id).url);
    }
}

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.round(bytes / 1024)} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function formatDate(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '—';
}
</script>

<template>
    <Head :title="document.title" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="showDosar(dosar.id)">
                        <ArrowLeft />
                    </Link>
                </Button>
                <div class="min-w-0 space-y-1">
                    <h1 class="truncate text-lg font-semibold tracking-tight">
                        {{ document.title }}
                    </h1>
                    <p
                        class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span class="flex items-center gap-1">
                            <span
                                class="size-2 rounded-full"
                                :style="{ backgroundColor: dosar.color }"
                            />
                            {{ dosar.name }}
                        </span>
                        <span>·</span>
                        <span>{{ document.original_name }}</span>
                        <span>·</span>
                        <span>{{ formatSize(document.size) }}</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" as-child>
                    <a
                        :href="fileDocument(document.id).url"
                        target="_blank"
                        rel="noopener"
                    >
                        <ExternalLink />
                        Open
                    </a>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="downloadDocument(document.id).url">
                        <Download />
                        Download
                    </a>
                </Button>
            </div>
        </div>

        <div
            v-if="document.is_duplicate"
            class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <p>
                This looks like the same file that is already in this folder.
                <Link
                    v-if="document.duplicate_of"
                    :href="`/medical/documents/${document.duplicate_of}`"
                    class="font-medium underline underline-offset-2"
                >
                    Compare with the earlier copy
                </Link>
            </p>
        </div>

        <div
            v-if="document.status === 'failed'"
            class="flex flex-wrap items-center gap-3 rounded-lg border border-destructive/40 bg-destructive/5 p-3"
            data-test="ocr-failed"
        >
            <AlertTriangle class="size-4 text-destructive" />
            <p class="min-w-0 flex-1 text-sm">
                {{ document.ocr_error ?? 'The text could not be read.' }}
            </p>
            <Button variant="outline" size="sm" @click="retry">
                <RotateCcw />
                Try again
            </Button>
        </div>

        <div
            v-else-if="['pending', 'processing'].includes(document.status)"
            class="flex items-center gap-2 rounded-lg border p-3 text-sm text-muted-foreground"
            data-test="ocr-reading"
        >
            <Loader2 class="size-4 animate-spin" />
            Reading the file…
        </div>

        <section
            v-if="suggested"
            class="space-y-3 rounded-lg border border-primary/30 bg-primary/5 p-3"
            data-test="suggested-metadata"
        >
            <div class="space-y-1">
                <p class="text-sm font-medium">Suggested details</p>
                <p class="text-xs text-muted-foreground">
                    Read from the document. Check them before they are saved.
                </p>
            </div>

            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div v-if="suggested.title">
                    <dt class="text-xs text-muted-foreground">Title</dt>
                    <dd>{{ suggested.title }}</dd>
                </div>
                <div v-if="suggested.document_date">
                    <dt class="text-xs text-muted-foreground">Date</dt>
                    <dd>{{ suggested.document_date }}</dd>
                </div>
                <div v-if="suggested.clinic">
                    <dt class="text-xs text-muted-foreground">Clinic</dt>
                    <dd>{{ suggested.clinic }}</dd>
                </div>
                <div v-if="suggested.doctor">
                    <dt class="text-xs text-muted-foreground">Doctor</dt>
                    <dd>{{ suggested.doctor }}</dd>
                </div>
            </dl>

            <Button size="sm" @click="acceptSuggestions">Use these</Button>
        </section>

        <section class="space-y-3 rounded-lg border p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-medium">Details</h2>
                <Button
                    v-if="!editing"
                    variant="ghost"
                    size="sm"
                    @click="editing = true"
                >
                    Edit
                </Button>
            </div>

            <template v-if="!editing">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">Type</dt>
                        <dd>{{ document.category_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Date</dt>
                        <dd>{{ document.document_date ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Tags</dt>
                        <dd>
                            <span v-if="document.tags.length === 0">—</span>
                            <span
                                v-for="tag in document.tags"
                                :key="tag"
                                class="mr-1 inline-block rounded bg-muted px-1.5 py-0.5 text-xs"
                                >{{ tag }}</span
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Added</dt>
                        <dd>{{ formatDate(document.created_at) }}</dd>
                    </div>
                </dl>
            </template>

            <form v-else class="space-y-3" @submit.prevent="save">
                <div class="space-y-1.5">
                    <Label for="title">Title</Label>
                    <Input id="title" v-model="form.title" required />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="category">Type</Label>
                        <select
                            id="category"
                            v-model="form.category"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none"
                        >
                            <option
                                v-for="category in categories"
                                :key="category.value"
                                :value="category.value"
                            >
                                {{ category.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="document_date">Date on the document</Label>
                        <Input
                            id="document_date"
                            v-model="form.document_date"
                            type="date"
                        />
                        <InputError :message="form.errors.document_date" />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="tags">Tags</Label>
                    <Input
                        id="tags"
                        v-model="form.tags"
                        placeholder="Separated by commas"
                    />
                    <InputError :message="form.errors.tags" />
                </div>

                <div class="space-y-1.5">
                    <Label for="text">Text</Label>
                    <p class="text-xs text-muted-foreground">
                        What the OCR read, or what you type in its place. This
                        is what search looks at.
                    </p>
                    <textarea
                        id="text"
                        v-model="form.text"
                        rows="14"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="form.errors.text" />
                </div>

                <div class="flex items-center gap-2">
                    <Button :disabled="form.processing" type="submit">
                        <Save />
                        Save
                    </Button>
                    <Button
                        variant="ghost"
                        type="button"
                        @click="editing = false"
                    >
                        Cancel
                    </Button>
                </div>
            </form>
        </section>

        <section v-if="pages.length > 0 && !editing" class="space-y-3">
            <h2 class="text-sm font-medium">Extracted text</h2>

            <article
                v-for="page in pages"
                :key="page.number"
                class="rounded-lg border bg-muted/30 p-4"
                data-test="extracted-page"
            >
                <p class="mb-2 text-xs text-muted-foreground">
                    Page {{ page.number }}
                    <template v-if="document.ocr_edited_at">
                        · edited by hand
                    </template>
                </p>
                <pre class="font-sans text-sm whitespace-pre-wrap">{{
                    page.markdown
                }}</pre>
            </article>
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-medium">Who looked at this</h2>

            <p
                v-if="activity.length === 0"
                class="text-xs text-muted-foreground"
            >
                Nobody has opened it yet.
            </p>

            <ul v-else class="space-y-1 text-xs text-muted-foreground">
                <li v-for="(entry, index) in activity" :key="index">
                    {{ entry.user ?? 'Someone' }} · {{ entry.action }} ·
                    {{ formatDate(entry.at) }}
                </li>
            </ul>
        </section>

        <Button
            v-if="document.can_delete"
            variant="ghost"
            size="sm"
            class="self-start text-destructive"
            @click="destroy"
        >
            Move to bin
        </Button>
    </div>
</template>
