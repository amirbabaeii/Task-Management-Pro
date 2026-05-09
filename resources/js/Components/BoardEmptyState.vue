<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    boardName: {
        type: String,
        default: 'your board',
    },
});

defineEmits(['create-task', 'add-column']);
</script>

<template>
    <div class="flex min-h-[calc(100vh-13rem)] items-center justify-center px-4">
        <div
            class="w-full max-w-xl rounded-lg border border-dashed border-gray-300 bg-white px-8 py-12 text-center shadow-sm"
        >
            <div
                class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600"
            >
                <svg
                    class="h-7 w-7"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <rect x="3" y="4" width="18" height="16" rx="2" />
                    <path d="M8 4v16" />
                    <path d="M16 4v16" />
                </svg>
            </div>
            <h3 class="mt-5 text-lg font-semibold text-gray-900">
                Welcome to {{ boardName }}
            </h3>
            <p class="mx-auto mt-2 max-w-sm text-sm text-gray-600">
                This board is empty. Capture your first task to start tracking progress, or add another column to shape your workflow.
            </p>
            <ul
                class="mx-auto mt-6 max-w-sm space-y-2 text-left text-xs text-gray-500"
            >
                <li class="flex items-start gap-2">
                    <span
                        class="mt-1 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400"
                    />
                    Drag tasks between columns to update their status.
                </li>
                <li class="flex items-start gap-2">
                    <span
                        class="mt-1 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400"
                    />
                    Click a column title to rename it. Drag the handle to reorder.
                </li>
                <li class="flex items-start gap-2">
                    <span
                        class="mt-1 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400"
                    />
                    Invite teammates from the Members button to collaborate.
                </li>
            </ul>
            <div class="mt-7 flex flex-wrap justify-center gap-3">
                <PrimaryButton @click="$emit('create-task')">
                    Create your first task
                </PrimaryButton>
                <SecondaryButton @click="$emit('add-column')">
                    Add a column
                </SecondaryButton>
            </div>
        </div>
    </div>
</template>
