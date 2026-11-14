<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, UserPlus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import medicalRoutes from '@/routes/medical';
import { store as storeDosar } from '@/routes/medical/dosars';

type Suggestion = {
    id: number;
    name: string;
    color: string;
};

type Props = {
    suggestions: Suggestion[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Medical', href: medicalRoutes.index() },
            { title: 'New folder', href: medicalRoutes.dosars.create() },
        ],
    },
});

const selected = ref<number[]>([]);

const form = useForm({
    name: '',
    date_of_birth: '',
    cnp: '',
    color: '#2563eb',
    notes: '',
});

const peopleForm = useForm({
    people: [] as { name: string; color: string }[],
});

function toggle(member: Suggestion): void {
    selected.value = selected.value.includes(member.id)
        ? selected.value.filter((id) => id !== member.id)
        : [...selected.value, member.id];
}

function createForSelected(): void {
    peopleForm.people = props.suggestions
        .filter((member) => selected.value.includes(member.id))
        .map((member) => ({ name: member.name, color: member.color }));

    peopleForm.post(storeDosar().url);
}

function submit(): void {
    form.post(storeDosar().url);
}
</script>

<template>
    <Head title="New folder" />

    <div class="mx-auto flex w-full max-w-xl flex-col gap-8 p-4">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">New folder</h1>
            <p class="text-sm text-muted-foreground">
                A folder is a person, not an account. A child without a login
                gets one just the same.
            </p>
        </div>

        <section
            v-if="suggestions.length > 0"
            class="space-y-3 rounded-lg border p-4"
            data-test="create-for-people"
        >
            <div class="space-y-1">
                <h2 class="text-sm font-medium">Create a folder for…</h2>
                <p class="text-xs text-muted-foreground">
                    Everyone on this calendar who does not have one yet.
                </p>
            </div>

            <ul class="space-y-2">
                <li
                    v-for="member in suggestions"
                    :key="member.id"
                    class="flex items-center gap-2"
                >
                    <Checkbox
                        :id="`member-${member.id}`"
                        :checked="selected.includes(member.id)"
                        @update:checked="toggle(member)"
                    />
                    <Label :for="`member-${member.id}`" class="font-normal">
                        {{ member.name }}
                    </Label>
                </li>
            </ul>

            <Button
                :disabled="selected.length === 0 || peopleForm.processing"
                size="sm"
                @click="createForSelected"
            >
                <UserPlus />
                Create {{ selected.length }} folder{{
                    selected.length === 1 ? '' : 's'
                }}
            </Button>
        </section>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    autofocus
                    placeholder="Maria"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="date_of_birth">Date of birth</Label>
                    <Input
                        id="date_of_birth"
                        v-model="form.date_of_birth"
                        type="date"
                    />
                    <InputError :message="form.errors.date_of_birth" />
                </div>

                <div class="space-y-2">
                    <Label for="cnp"
                        >CNP
                        <span class="text-xs text-muted-foreground"
                            >(optional)</span
                        ></Label
                    >
                    <Input
                        id="cnp"
                        v-model="form.cnp"
                        inputmode="numeric"
                        maxlength="13"
                        placeholder="1234567890123"
                    />
                    <InputError :message="form.errors.cnp" />
                </div>
            </div>

            <div class="space-y-2">
                <Label for="color">Colour</Label>
                <Input
                    id="color"
                    v-model="form.color"
                    type="color"
                    class="h-10 w-20 cursor-pointer p-1"
                />
                <InputError :message="form.errors.color" />
            </div>

            <div class="space-y-2">
                <Label for="notes">Notes</Label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    placeholder="Family doctor, allergies, anything worth remembering."
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex items-center gap-2">
                <Button :disabled="form.processing" type="submit">
                    <Plus />
                    Create folder
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="medicalRoutes.index()">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
