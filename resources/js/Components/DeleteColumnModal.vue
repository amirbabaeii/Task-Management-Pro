<script setup>
import { computed, ref, watch } from 'vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    columnLabel: {
        type: String,
        default: '',
    },
    taskCount: {
        type: Number,
        default: 0,
    },
    /**
     * Available destination columns for moving the tasks. Each item:
     *   { status: 'pending', label: 'Pending' }
     *
     * The current column being deleted should NOT be in this list.
     */
    destinations: {
        type: Array,
        default: () => [],
    },
    processing: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'confirm']);

const moveTasksTo = ref('');

const taskLabel = computed(() =>
    props.taskCount === 1 ? '1 task' : `${props.taskCount} tasks`,
);

const canSubmit = computed(() => moveTasksTo.value !== '' && ! props.processing);

watch(
    () => props.show,
    (next) => {
        if (next) {
            moveTasksTo.value = props.destinations[0]?.status ?? '';
        }
    },
);
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <form
            class="p-6"
            @submit.prevent="canSubmit && emit('confirm', moveTasksTo)"
        >
            <div class="flex flex-col gap-1 border-b border-gray-100 pb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    Delete column “{{ columnLabel }}”?
                </h3>
                <p class="text-sm text-gray-500">
                    This column has {{ taskLabel }}. Pick a column to move
                    {{ taskCount === 1 ? 'it' : 'them' }} to before deleting.
                </p>
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <InputLabel for="move-tasks-to" value="Move tasks to" />
                    <select
                        id="move-tasks-to"
                        v-model="moveTasksTo"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                        required
                    >
                        <option
                            v-for="destination in destinations"
                            :key="destination.status"
                            :value="destination.status"
                        >
                            {{ destination.label }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="error" />
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <SecondaryButton type="button" @click="emit('close')">
                    Cancel
                </SecondaryButton>
                <DangerButton
                    :class="{ 'opacity-25': ! canSubmit }"
                    :disabled="! canSubmit"
                >
                    {{ processing ? 'Deleting...' : 'Move & Delete' }}
                </DangerButton>
            </div>
        </form>
    </Modal>
</template>
