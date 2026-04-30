<script setup>
import { nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    boardId: {
        type: Number,
        default: null,
    },
    name: {
        type: String,
        default: 'Task Board',
    },
    description: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:name', 'update:description', 'error']);

const editingName = ref(false);
const nameDraft = ref('');
const savingName = ref(false);
const nameInput = ref(null);

const editingDescription = ref(false);
const descriptionDraft = ref('');
const savingDescription = ref(false);
const descriptionInput = ref(null);

const cancelNameEdit = () => {
    editingName.value = false;
    nameDraft.value = '';
};

const cancelDescriptionEdit = () => {
    editingDescription.value = false;
    descriptionDraft.value = '';
};

const startNameEdit = async () => {
    if (savingName.value || savingDescription.value || !props.boardId) {
        return;
    }

    cancelDescriptionEdit();
    editingName.value = true;
    nameDraft.value = props.name;

    await nextTick();
    nameInput.value?.focus();
    nameInput.value?.select();
};

const startDescriptionEdit = async () => {
    if (savingName.value || savingDescription.value || !props.boardId) {
        return;
    }

    cancelNameEdit();
    editingDescription.value = true;
    descriptionDraft.value = props.description;

    await nextTick();
    descriptionInput.value?.focus();
    descriptionInput.value?.select();
};

const saveName = async () => {
    if (!editingName.value || savingName.value || !props.boardId) {
        return;
    }

    const nextName = nameDraft.value.trim();

    if (!nextName) {
        emit('error', 'Board title cannot be empty.');
        return;
    }

    if (nextName === props.name) {
        cancelNameEdit();
        return;
    }

    emit('error', '');
    savingName.value = true;

    try {
        const response = await axios.patch(
            route('boards.update', { board: props.boardId }),
            { name: nextName },
        );

        emit('update:name', response?.data?.board?.name ?? nextName);
        if (response?.data?.board?.description !== undefined) {
            emit('update:description', response.data.board.description ?? '');
        }

        cancelNameEdit();
        router.reload({
            only: ['boards', 'currentBoard'],
            preserveScroll: true,
            preserveState: true,
        });
    } catch (error) {
        emit(
            'error',
            error?.response?.data?.message ||
                'Unable to update board title. Please try again.',
        );
    } finally {
        savingName.value = false;
    }
};

const saveDescription = async () => {
    if (!editingDescription.value || savingDescription.value || !props.boardId) {
        return;
    }

    const nextDescription = descriptionDraft.value.trim();

    if (nextDescription === props.description) {
        cancelDescriptionEdit();
        return;
    }

    emit('error', '');
    savingDescription.value = true;

    try {
        const response = await axios.patch(
            route('boards.update', { board: props.boardId }),
            { description: nextDescription },
        );

        if (response?.data?.board?.name !== undefined) {
            emit('update:name', response.data.board.name);
        }
        emit(
            'update:description',
            response?.data?.board?.description ?? '',
        );

        cancelDescriptionEdit();
        router.reload({
            only: ['boards', 'currentBoard'],
            preserveScroll: true,
            preserveState: true,
        });
    } catch (error) {
        emit(
            'error',
            error?.response?.data?.message ||
                'Unable to update board description. Please try again.',
        );
    } finally {
        savingDescription.value = false;
    }
};
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0 flex-1 space-y-1">
            <input
                v-if="editingName"
                ref="nameInput"
                v-model="nameDraft"
                type="text"
                maxlength="100"
                class="block w-full max-w-lg rounded-md border-gray-300 px-2 py-1 text-xl font-semibold leading-tight text-gray-800 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                @keydown.enter.prevent="saveName"
                @keydown.esc.prevent="cancelNameEdit"
                @blur="saveName"
            />
            <button
                v-else
                type="button"
                class="-mx-2 block max-w-full rounded-md px-2 py-1 text-left text-xl font-semibold leading-tight text-gray-800 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="startNameEdit"
            >
                <span class="block truncate">{{ name }}</span>
            </button>

            <textarea
                v-if="editingDescription"
                ref="descriptionInput"
                v-model="descriptionDraft"
                rows="3"
                maxlength="280"
                class="block w-full max-w-none resize-y rounded-md border-gray-300 px-3 py-2 text-sm text-gray-600 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                placeholder="Add board description"
                @keydown.ctrl.enter.prevent="saveDescription"
                @keydown.meta.enter.prevent="saveDescription"
                @keydown.esc.prevent="cancelDescriptionEdit"
                @blur="saveDescription"
            />
            <button
                v-else
                type="button"
                class="-mx-2 block max-w-full rounded-md px-2 py-1 text-left text-sm transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                :class="description ? 'text-gray-500' : 'text-gray-400'"
                @click="startDescriptionEdit"
            >
                <span class="board-description-preview block break-words">
                    {{ description || 'Add board description' }}
                </span>
            </button>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-3">
            <slot name="actions" />
        </div>
    </div>
</template>

<style scoped>
.board-description-preview {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 4;
    line-clamp: 4;
}
</style>
