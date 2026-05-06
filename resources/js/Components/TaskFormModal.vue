<script setup>
import AssigneePicker from '@/Components/AssigneePicker.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TagInput from '@/Components/TagInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatPriority } from '@/lib/format';
import { computed } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: 'create',
        validator: (value) => ['create', 'edit'].includes(value),
    },
    form: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    formatStatus: {
        type: Function,
        required: true,
    },
    priorities: {
        type: Array,
        default: () => [],
    },
    maxTags: {
        type: Number,
        default: 10,
    },
    maxTagLength: {
        type: Number,
        default: 30,
    },
    resolveFieldError: {
        type: Function,
        required: true,
    },
    members: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'submit']);

const idPrefix = computed(() => (props.mode === 'edit' ? 'edit-' : ''));
const heading = computed(() =>
    props.mode === 'edit' ? 'Update task' : 'Create a new task',
);
const subheading = computed(() =>
    props.mode === 'edit'
        ? 'Edit the task details without leaving the board.'
        : 'New tasks are automatically assigned to you and added to this board.',
);
const submitIdle = computed(() =>
    props.mode === 'edit' ? 'Save Changes' : 'Create Task',
);
const submitBusy = computed(() =>
    props.mode === 'edit' ? 'Saving...' : 'Creating...',
);
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-6">
            <div class="flex flex-col gap-1 border-b border-gray-100 pb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ heading }}
                </h3>
                <p class="text-sm text-gray-500">
                    {{ subheading }}
                </p>
            </div>

            <form
                class="mt-6 grid gap-4 md:grid-cols-2"
                @submit.prevent="emit('submit')"
            >
                <div class="md:col-span-2">
                    <InputLabel :for="`${idPrefix}title`" value="Title" />
                    <TextInput
                        :id="`${idPrefix}title`"
                        v-model="form.title"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        maxlength="150"
                        autocomplete="off"
                    />
                    <InputError class="mt-2" :message="form.errors.title" />
                </div>

                <div>
                    <InputLabel :for="`${idPrefix}status`" value="Status" />
                    <select
                        :id="`${idPrefix}status`"
                        v-model="form.status"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                        required
                    >
                        <option
                            v-for="status in statuses"
                            :key="status"
                            :value="status"
                        >
                            {{ formatStatus(status) }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.status" />
                </div>

                <div>
                    <InputLabel :for="`${idPrefix}priority`" value="Priority" />
                    <select
                        :id="`${idPrefix}priority`"
                        v-model="form.priority"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                        required
                    >
                        <option
                            v-for="priority in priorities"
                            :key="priority"
                            :value="priority"
                        >
                            {{ formatPriority(priority) }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.priority" />
                </div>

                <div class="md:col-span-2">
                    <InputLabel
                        :for="`${idPrefix}description`"
                        value="Description"
                    />
                    <textarea
                        :id="`${idPrefix}description`"
                        v-model="form.description"
                        rows="5"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                    />
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>

                <div class="md:col-span-2">
                    <InputLabel :for="`${idPrefix}tags`" value="Tags" />
                    <TagInput
                        :id="`${idPrefix}tags`"
                        v-model="form.tags"
                        placeholder="Add a tag, e.g. backend"
                        :max-tags="maxTags"
                        :max-tag-length="maxTagLength"
                        :error="resolveFieldError(form.errors, 'tags')"
                    />
                </div>

                <div v-if="mode === 'edit'" class="md:col-span-2">
                    <InputLabel
                        :for="`${idPrefix}progress`"
                        value="Progress"
                    />
                    <div class="mt-2">
                        <input
                            :id="`${idPrefix}progress`"
                            v-model.number="form.progress"
                            class="task-progress-slider block w-full"
                            type="range"
                            min="0"
                            max="100"
                            step="5"
                            :style="{ '--task-progress': `${form.progress}%` }"
                        />
                        <div class="mt-1 text-sm text-gray-500">
                            {{ form.progress }}% complete
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.progress" />
                </div>

                <div :class="mode === 'edit' ? 'md:col-span-2' : ''">
                    <InputLabel
                        :for="`${idPrefix}deadline_at`"
                        value="Deadline"
                    />
                    <input
                        :id="`${idPrefix}deadline_at`"
                        v-model="form.deadline_at"
                        type="date"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                    />
                    <InputError class="mt-2" :message="form.errors.deadline_at" />
                </div>

                <div class="md:col-span-2">
                    <InputLabel value="Assignees" />
                    <AssigneePicker
                        v-model:selected-ids="form.assignee_ids"
                        :members="members"
                        class="mt-1"
                    />
                    <InputError
                        class="mt-2"
                        :message="resolveFieldError(form.errors, 'assignee_ids')"
                    />
                </div>

                <div
                    class="flex items-center justify-end gap-3 pt-2 md:col-span-2"
                >
                    <SecondaryButton @click="emit('close')">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? submitBusy : submitIdle }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>

<style scoped>
.task-progress-slider {
    --task-progress: 0%;
    -webkit-appearance: none;
    appearance: none;
    height: 0.5rem;
    border-radius: 9999px;
    background: transparent;
    cursor: pointer;
}

.task-progress-slider::-webkit-slider-runnable-track {
    height: 0.5rem;
    border-radius: 9999px;
    background: linear-gradient(
        to right,
        rgb(31 41 55) 0%,
        rgb(31 41 55) var(--task-progress),
        rgb(229 231 235) var(--task-progress),
        rgb(229 231 235) 100%
    );
}

.task-progress-slider::-moz-range-track {
    height: 0.5rem;
    border: 0;
    border-radius: 9999px;
    background: linear-gradient(
        to right,
        rgb(31 41 55) 0%,
        rgb(31 41 55) var(--task-progress),
        rgb(229 231 235) var(--task-progress),
        rgb(229 231 235) 100%
    );
}

.task-progress-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 0.9rem;
    height: 0.9rem;
    margin-top: -0.2rem;
    border: 2px solid #fff;
    border-radius: 9999px;
    background: rgb(31 41 55);
    box-shadow: 0 1px 3px rgb(15 23 42 / 0.25);
}

.task-progress-slider::-moz-range-thumb {
    width: 0.9rem;
    height: 0.9rem;
    border: 2px solid #fff;
    border-radius: 9999px;
    background: rgb(31 41 55);
    box-shadow: 0 1px 3px rgb(15 23 42 / 0.25);
}
</style>
