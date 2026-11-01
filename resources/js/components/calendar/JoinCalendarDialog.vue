<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { codeFromInput } from '@/lib/joinCode';
import { show as showJoin } from '@/routes/join';

const open = defineModel<boolean>('open', { required: true });

const code = ref('');

// Start from a clean box every time the dialog is opened.
watch(open, (isOpen) => {
    if (isOpen) {
        code.value = '';
    }
});

/**
 * Hand the typed code to the confirmation screen rather than joining straight
 * away, so nobody is added by a stray keystroke.
 */
function submit(): void {
    const trimmed = code.value.trim();

    if (trimmed === '') {
        return;
    }

    open.value = false;
    router.visit(showJoin(codeFromInput(trimmed)));
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Join a calendar</DialogTitle>
                    <DialogDescription>
                        Enter the three word code someone on the calendar shared
                        with you. They will need to approve you before you can
                        see it.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="join-code-input">Join code</Label>
                    <Input
                        id="join-code-input"
                        v-model="code"
                        placeholder="brave-otter-teapot"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        class="font-mono"
                    />
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="code.trim() === ''">
                        Continue
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
