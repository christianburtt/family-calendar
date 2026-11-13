<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Camera, FileText, Loader2, Upload, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { store as storeDocuments } from '@/routes/medical/documents';
import type { MedicalCategoryOption, MedicalUploadLimits } from '@/types/medical';

type Props = {
    dosarId: number;
    limits: MedicalUploadLimits;
    categories: MedicalCategoryOption[];
};

const props = defineProps<Props>();

const files = ref<File[]>([]);
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{
    files: File[];
    category: string;
    document_date: string;
}>({
    files: [],
    category: 'other',
    document_date: '',
});

const totalMb = computed(() =>
    (files.value.reduce((total, file) => total + file.size, 0) / 1024 / 1024).toFixed(1),
);

function addFiles(incoming: FileList | null): void {
    if (!incoming) {
        return;
    }

    const room = props.limits.max_files - files.value.length;

    files.value = [...files.value, ...Array.from(incoming).slice(0, room)];
}

function removeFile(index: number): void {
    files.value = files.value.filter((_, i) => i !== index);
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
}

function upload(): void {
    form.files = files.value;
    form.post(storeDocuments(props.dosarId).url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            files.value = [];
            form.reset('files');

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.round(bytes / 1024)} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="upload">
        <div
            :class="[
                'flex flex-col items-center gap-2 rounded-lg border border-dashed p-6 text-center transition-colors',
                dragging ? 'border-primary bg-muted/50' : 'border-input',
            ]"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <Upload class="size-5 text-muted-foreground" />
            <p class="text-sm">
                Drop scans here, or
                <button
                    type="button"
                    class="font-medium underline underline-offset-2"
                    @click="fileInput?.click()"
                >
                    choose files
                </button>
            </p>
            <p class="text-xs text-muted-foreground">
                PDF or photo · up to {{ limits.max_mb }} MB each ·
                {{ limits.max_files }} at a time
            </p>

            <input
                ref="fileInput"
                type="file"
                :accept="limits.accept"
                multiple
                class="hidden"
                @change="addFiles(($event.target as HTMLInputElement).files)"
            />

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="sm:hidden"
                @click="fileInput?.click()"
            >
                <Camera />
                Take a photo
            </Button>
        </div>

        <ul v-if="files.length > 0" class="space-y-2">
            <li
                v-for="(file, index) in files"
                :key="`${file.name}-${index}`"
                class="flex items-center gap-2 rounded-md border p-2 text-sm"
            >
                <FileText class="size-4 shrink-0 text-muted-foreground" />
                <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                <span class="text-xs text-muted-foreground">{{
                    formatSize(file.size)
                }}</span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    @click="removeFile(index)"
                >
                    <X />
                </Button>
            </li>

            <li class="text-xs text-muted-foreground">{{ totalMb }} MB total</li>
        </ul>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="space-y-1.5 text-sm">
                <span class="font-medium">Type</span>
                <select
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
            </label>

            <label class="space-y-1.5 text-sm">
                <span class="font-medium"
                    >Date on the document
                    <span class="text-xs text-muted-foreground">(optional)</span></span
                >
                <Input v-model="form.document_date" type="date" />
            </label>
        </div>

        <p v-if="form.errors.files" class="text-sm text-destructive">
            {{ form.errors.files }}
        </p>

        <Button :disabled="files.length === 0 || form.processing" type="submit">
            <Loader2 v-if="form.processing" class="animate-spin" />
            <Upload v-else />
            Upload {{ files.length || '' }}
        </Button>
    </form>
</template>
