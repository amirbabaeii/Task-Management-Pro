<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    priorities: {
        type: Array,
        default: () => [],
    },
});

const normalizeTask = (task) => ({
    ...task,
    progress: task.progress ?? 0,
});

const tasks = ref(props.tasks.map(normalizeTask));
const updatingId = ref(null);
const progressDrafts = ref({});
const errorMessage = ref('');
const defaultStatus = props.statuses.includes('pending')
    ? 'pending'
    : (props.statuses[0] ?? 'pending');
const defaultPriority = props.priorities.includes('medium')
    ? 'medium'
    : (props.priorities[0] ?? 'medium');

const form = useForm({
    title: '',
    description: '',
    status: defaultStatus,
    priority: defaultPriority,
    deadline_at: '',
});

const statusLabels = {
    pending: 'Pending',
    'in-progress': 'In Progress',
    completed: 'Completed',
};

const boardStatuses = computed(() =>
    props.statuses.length
        ? props.statuses
        : ['pending', 'in-progress', 'completed'],
);
const priorityOptions = computed(() =>
    props.priorities.length
        ? props.priorities
        : ['low', 'medium', 'high'],
);

watch(
    () => props.tasks,
    (nextTasks) => {
        tasks.value = nextTasks.map(normalizeTask);
    },
);

const formatStatus = (status) => statusLabels[status] ?? status;

const formatPriority = (priority) => {
    if (!priority) {
        return 'Unspecified';
    }

    return `${priority.charAt(0).toUpperCase()}${priority.slice(1)}`;
};

const formatDate = (value) => {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(date);
};

const getProgressValue = (task) => progressDrafts.value[task.id] ?? task.progress;

const progressBarStyle = (task) => ({
    '--task-progress': `${getProgressValue(task)}%`,
});

const tasksByStatus = computed(() => {
    const grouped = {};
    boardStatuses.value.forEach((status) => {
        grouped[status] = [];
    });

    tasks.value.forEach((task) => {
        if (!grouped[task.status]) {
            grouped[task.status] = [];
        }
        grouped[task.status].push(task);
    });

    return grouped;
});

const patchTask = async (task, field, value, routeName, fallbackMessage) => {
    if (value === null || value === undefined || task[field] === value || updatingId.value) {
        return;
    }

    errorMessage.value = '';
    updatingId.value = task.id;
    const previousValue = task[field];
    task[field] = value;

    try {
        const response = await axios.patch(route(routeName, task.id), {
            [field]: value,
        });

        if (response?.data?.task?.[field] !== undefined) {
            task[field] = response.data.task[field];
        }
    } catch (error) {
        task[field] = previousValue;
        errorMessage.value =
            error?.response?.data?.message || fallbackMessage;
    } finally {
        updatingId.value = null;
    }
};

const updateStatus = async (task, nextStatus) => {
    await patchTask(
        task,
        'status',
        nextStatus,
        'tasks.status',
        'Unable to update task status. Please try again.',
    );
};

const onStatusChange = (event, task) => {
    updateStatus(task, event.target.value);
};

const parseProgress = (value) => {
    const parsed = Number.parseInt(value, 10);

    if (Number.isNaN(parsed)) {
        return null;
    }

    return Math.min(100, Math.max(0, parsed));
};

const clearProgressDraft = (taskId) => {
    if (!(taskId in progressDrafts.value)) {
        return;
    }

    const nextDrafts = { ...progressDrafts.value };
    delete nextDrafts[taskId];
    progressDrafts.value = nextDrafts;
};

const onProgressInput = (event, task) => {
    const nextProgress = parseProgress(event.target.value);

    if (nextProgress === null) {
        return;
    }

    progressDrafts.value = {
        ...progressDrafts.value,
        [task.id]: nextProgress,
    };
};

const updateProgress = async (task, nextProgress) => {
    if (
        nextProgress === null ||
        nextProgress === undefined ||
        task.progress === nextProgress ||
        updatingId.value
    ) {
        clearProgressDraft(task.id);
        return;
    }

    errorMessage.value = '';
    updatingId.value = task.id;

    try {
        const response = await axios.patch(route('tasks.progress', task.id), {
            progress: nextProgress,
        });

        task.progress = response?.data?.task?.progress ?? nextProgress;
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to update task progress. Please try again.';
    } finally {
        clearProgressDraft(task.id);
        updatingId.value = null;
    }
};

const onProgressChange = (event, task) => {
    updateProgress(task, getProgressValue(task));
};

const submitTask = () => {
    form.post(route('tasks.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Task Board" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Task Board
                </h2>
                <p class="text-sm text-gray-500">
                    Move tasks between statuses to keep work flowing.
                </p>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="space-y-6">
                    <section
                        class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex flex-col gap-1 border-b border-gray-100 pb-4 sm:flex-row sm:items-end sm:justify-between"
                        >
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    Create a new task
                                </h3>
                                <p class="text-sm text-gray-500">
                                    New tasks are automatically assigned to you
                                    and added to this board.
                                </p>
                            </div>
                            <p
                                v-if="form.recentlySuccessful"
                                class="text-sm font-medium text-green-600"
                            >
                                Task created successfully.
                            </p>
                        </div>

                        <form
                            class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                            @submit.prevent="submitTask"
                        >
                            <div class="xl:col-span-2">
                                <InputLabel for="title" value="Title" />
                                <TextInput
                                    id="title"
                                    v-model="form.title"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                    maxlength="150"
                                    autocomplete="off"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.title"
                                />
                            </div>

                            <div>
                                <InputLabel for="status" value="Status" />
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                    required
                                >
                                    <option
                                        v-for="status in boardStatuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ formatStatus(status) }}
                                    </option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.status"
                                />
                            </div>

                            <div>
                                <InputLabel for="priority" value="Priority" />
                                <select
                                    id="priority"
                                    v-model="form.priority"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                    required
                                >
                                    <option
                                        v-for="priority in priorityOptions"
                                        :key="priority"
                                        :value="priority"
                                    >
                                        {{ formatPriority(priority) }}
                                    </option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.priority"
                                />
                            </div>

                            <div class="md:col-span-2 xl:col-span-3">
                                <InputLabel
                                    for="description"
                                    value="Description"
                                />
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.description"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    for="deadline_at"
                                    value="Deadline"
                                />
                                <input
                                    id="deadline_at"
                                    v-model="form.deadline_at"
                                    type="date"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.deadline_at"
                                />
                            </div>

                            <div
                                class="flex items-center justify-end md:col-span-2 xl:col-span-4"
                            >
                                <PrimaryButton
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    {{
                                        form.processing
                                            ? 'Creating...'
                                            : 'Create Task'
                                    }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </section>

                    <div class="grid gap-6 lg:grid-cols-3">
                        <section
                            v-for="status in boardStatuses"
                            :key="status"
                            class="flex flex-col rounded-lg border border-gray-200 bg-white shadow-sm"
                        >
                            <div
                                class="flex items-center justify-between border-b border-gray-100 px-4 py-3"
                            >
                                <h3 class="text-sm font-semibold text-gray-700">
                                    {{ formatStatus(status) }}
                                </h3>
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                                >
                                    {{ tasksByStatus[status]?.length || 0 }}
                                </span>
                            </div>
                            <div class="flex-1 space-y-4 p-4">
                                <div
                                    v-if="!tasksByStatus[status]?.length"
                                    class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-6 text-center text-xs text-gray-500"
                                >
                                    No tasks in this status.
                                </div>
                                <article
                                    v-for="task in tasksByStatus[status]"
                                    :key="task.id"
                                    class="rounded-md border border-gray-200 bg-gray-50 p-4 shadow-sm"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <h4
                                                class="text-sm font-semibold text-gray-800"
                                            >
                                                {{ task.title }}
                                            </h4>
                                            <p
                                                v-if="task.description"
                                                class="mt-1 text-xs leading-relaxed text-gray-600"
                                            >
                                                {{ task.description }}
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-700"
                                        >
                                            {{ formatPriority(task.priority) }}
                                        </span>
                                    </div>

                                    <div class="mt-3">
                                        <label
                                            :for="`task-progress-${task.id}`"
                                            class="sr-only"
                                        >
                                            Progress
                                        </label>
                                        <input
                                            :id="`task-progress-${task.id}`"
                                            class="task-progress-slider block w-full"
                                            type="range"
                                            min="0"
                                            max="100"
                                            step="5"
                                            :value="getProgressValue(task)"
                                            :style="progressBarStyle(task)"
                                            :disabled="updatingId === task.id"
                                            @input="onProgressInput($event, task)"
                                            @change="
                                                onProgressChange($event, task)
                                            "
                                        />
                                        <div class="mt-1 text-[10px] text-gray-500">
                                            {{ getProgressValue(task) }}%
                                            complete
                                        </div>
                                    </div>

                                    <div
                                        class="mt-3 flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <span
                                            v-if="formatDate(task.deadline_at)"
                                            class="text-[11px] text-gray-500"
                                        >
                                            Due
                                            {{ formatDate(task.deadline_at) }}
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <span
                                                v-if="updatingId === task.id"
                                                class="text-[11px] text-gray-400"
                                            >
                                                Updating...
                                            </span>
                                            <select
                                                class="block w-32 rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                                :value="task.status"
                                                :disabled="updatingId === task.id"
                                                @change="
                                                    onStatusChange($event, task)
                                                "
                                            >
                                                <option
                                                    v-for="option in boardStatuses"
                                                    :key="option"
                                                    :value="option"
                                                >
                                                    {{ formatStatus(option) }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </section>
                    </div>
                </div>

                <p
                    v-if="errorMessage"
                    class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ errorMessage }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
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

.task-progress-slider:disabled {
    cursor: not-allowed;
    opacity: 0.7;
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
