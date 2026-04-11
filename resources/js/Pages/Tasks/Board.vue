<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
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
const draggedTaskId = ref(null);
const dragOverStatus = ref(null);
const dragBlockedTaskId = ref(null);
const showingCreateModal = ref(false);
const showingDetailsModal = ref(false);
const showingEditModal = ref(false);
const selectedTaskId = ref(null);
const editingTaskId = ref(null);
const errorMessage = ref('');
const defaultStatus = props.statuses.includes('pending')
    ? 'pending'
    : (props.statuses[0] ?? 'pending');
const defaultPriority = props.priorities.includes('medium')
    ? 'medium'
    : (props.priorities[0] ?? 'medium');

const blankTaskData = () => ({
    title: '',
    description: '',
    status: defaultStatus,
    priority: defaultPriority,
    deadline_at: '',
});

const form = useForm(blankTaskData());
const editForm = useForm(blankTaskData());

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

const formatDateTime = (value) => {
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
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
};

const getProgressValue = (task) => progressDrafts.value[task.id] ?? task.progress;

const progressBarStyle = (task) => ({
    '--task-progress': `${getProgressValue(task)}%`,
});

const activeTask = computed(() =>
    tasks.value.find((task) => task.id === selectedTaskId.value) ?? null,
);

const formatDateInput = (value) => {
    if (!value) {
        return '';
    }

    if (typeof value === 'string') {
        return value.slice(0, 10);
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toISOString().slice(0, 10);
};

const setTaskFormValues = (taskForm, values = {}) => {
    taskForm.title = values.title ?? '';
    taskForm.description = values.description ?? '';
    taskForm.status = values.status ?? defaultStatus;
    taskForm.priority = values.priority ?? defaultPriority;
    taskForm.deadline_at = values.deadline_at ?? '';
};

const closeCreateModal = () => {
    showingCreateModal.value = false;
    setTaskFormValues(form, blankTaskData());
    form.clearErrors();
};

const openTaskDetails = (task) => {
    selectedTaskId.value = task.id;
    showingDetailsModal.value = true;
};

const closeTaskDetails = () => {
    showingDetailsModal.value = false;
    selectedTaskId.value = null;
};

const openEditModal = (task) => {
    editingTaskId.value = task.id;
    setTaskFormValues(editForm, {
        title: task.title,
        description: task.description ?? '',
        status: task.status,
        priority: task.priority,
        deadline_at: formatDateInput(task.deadline_at),
    });
    editForm.clearErrors();
    showingEditModal.value = true;
};

const closeEditModal = () => {
    showingEditModal.value = false;
    editingTaskId.value = null;
    setTaskFormValues(editForm, blankTaskData());
    editForm.clearErrors();
};

const openEditFromDetails = () => {
    if (!activeTask.value) {
        return;
    }

    const task = activeTask.value;
    closeTaskDetails();
    openEditModal(task);
};

const isDraggingTask = (taskId) => draggedTaskId.value === taskId;

const isDropTarget = (status) =>
    draggedTaskId.value !== null && dragOverStatus.value === status;

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

const resetDragState = () => {
    draggedTaskId.value = null;
    dragOverStatus.value = null;
};

const blockTaskDrag = (taskId) => {
    dragBlockedTaskId.value = taskId;
};

const clearTaskDragBlock = (taskId = null) => {
    if (taskId === null || dragBlockedTaskId.value === taskId) {
        dragBlockedTaskId.value = null;
    }
};

const onTaskDragStart = (event, task) => {
    if (updatingId.value || dragBlockedTaskId.value === task.id) {
        event.preventDefault();
        clearTaskDragBlock(task.id);
        return;
    }

    draggedTaskId.value = task.id;
    dragOverStatus.value = task.status;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(task.id));
    }
};

const onTaskDragEnd = () => {
    clearTaskDragBlock();
    resetDragState();
};

const onColumnDragOver = (event, status) => {
    if (draggedTaskId.value === null) {
        return;
    }

    event.preventDefault();
    dragOverStatus.value = status;

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const onColumnDrop = async (status) => {
    if (draggedTaskId.value === null) {
        return;
    }

    const task = tasks.value.find(
        (candidate) => candidate.id === draggedTaskId.value,
    );

    resetDragState();

    if (!task) {
        return;
    }

    await updateStatus(task, status);
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
    clearTaskDragBlock(task.id);
};

const submitTask = () => {
    form.post(route('tasks.store'), {
        preserveScroll: true,
        onSuccess: () => closeCreateModal(),
    });
};

const submitTaskUpdate = () => {
    if (!editingTaskId.value) {
        return;
    }

    editForm.patch(route('tasks.update', editingTaskId.value), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};
</script>

<template>
    <Head title="Task Board" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Task Board
                    </h2>
                    <p class="text-sm text-gray-500">
                        Move tasks between statuses to keep work flowing.
                    </p>
                </div>
                <PrimaryButton @click="showingCreateModal = true">
                    New Task
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-3">
                        <section
                            v-for="status in boardStatuses"
                            :key="status"
                            class="task-column flex flex-col rounded-lg border border-gray-200 bg-white shadow-sm transition"
                            :class="{
                                'task-column--drop-target': isDropTarget(status),
                            }"
                            @dragover="onColumnDragOver($event, status)"
                            @drop.prevent="onColumnDrop(status)"
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
                                    class="task-card rounded-md border border-gray-200 bg-gray-50 p-4 shadow-sm transition"
                                    :class="{
                                        'task-card--dragging': isDraggingTask(task.id),
                                    }"
                                    role="button"
                                    tabindex="0"
                                    :draggable="updatingId !== task.id"
                                    @click="openTaskDetails(task)"
                                    @keydown.enter.prevent="openTaskDetails(task)"
                                    @keydown.space.prevent="openTaskDetails(task)"
                                    @dragstart="onTaskDragStart($event, task)"
                                    @dragend="onTaskDragEnd"
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

                                    <div
                                        class="mt-3"
                                        data-no-drag
                                        @pointerdown="blockTaskDrag(task.id)"
                                        @pointerup="clearTaskDragBlock(task.id)"
                                        @pointercancel="clearTaskDragBlock(task.id)"
                                        @click.stop
                                        @dragstart.prevent
                                    >
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

                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <span
                                            v-if="formatDate(task.deadline_at)"
                                            class="text-[11px] text-gray-500"
                                        >
                                            Due
                                            {{ formatDate(task.deadline_at) }}
                                        </span>
                                        <div class="ml-auto flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                                                :disabled="updatingId === task.id"
                                                @click.stop="openEditModal(task)"
                                            >
                                                Edit
                                            </button>
                                            <span
                                                v-if="updatingId === task.id"
                                                class="text-[11px] text-gray-400"
                                            >
                                                Updating...
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </section>
                </div>

                <p
                    v-if="errorMessage"
                    class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ errorMessage }}
                </p>

                <Modal
                    :show="showingDetailsModal"
                    max-width="2xl"
                    @close="closeTaskDetails"
                >
                    <div
                        v-if="activeTask"
                        class="p-6"
                    >
                        <div
                            class="flex flex-col gap-4 border-b border-gray-100 pb-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ activeTask.title }}
                                </h3>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span
                                        class="rounded-full bg-gray-100 px-2.5 py-1 font-semibold uppercase tracking-wide text-gray-700"
                                    >
                                        {{ formatStatus(activeTask.status) }}
                                    </span>
                                    <span
                                        class="rounded-full bg-gray-100 px-2.5 py-1 font-semibold uppercase tracking-wide text-gray-700"
                                    >
                                        {{ formatPriority(activeTask.priority) }}
                                    </span>
                                </div>
                            </div>
                            <SecondaryButton @click="openEditFromDetails">
                                Edit Task
                            </SecondaryButton>
                        </div>

                        <div class="mt-6 space-y-6">
                            <section class="space-y-2">
                                <h4 class="text-sm font-semibold text-gray-900">
                                    Description
                                </h4>
                                <p class="text-sm leading-6 text-gray-600">
                                    {{
                                        activeTask.description ||
                                        'No description provided.'
                                    }}
                                </p>
                            </section>

                            <section class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-gray-900">
                                        Progress
                                    </h4>
                                    <span class="text-sm text-gray-500">
                                        {{ activeTask.progress }}%
                                    </span>
                                </div>
                                <div
                                    class="h-2 w-full rounded-full bg-gray-200"
                                >
                                    <div
                                        class="h-2 rounded-full bg-gray-800"
                                        :style="{
                                            width: `${activeTask.progress}%`,
                                        }"
                                    />
                                </div>
                            </section>

                            <section class="grid gap-4 sm:grid-cols-3">
                                <div class="rounded-lg bg-gray-50 px-4 py-3">
                                    <div
                                        class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                    >
                                        Due Date
                                    </div>
                                    <div class="mt-1 text-sm text-gray-700">
                                        {{
                                            formatDate(activeTask.deadline_at) ||
                                            'No deadline set'
                                        }}
                                    </div>
                                </div>
                                <div class="rounded-lg bg-gray-50 px-4 py-3">
                                    <div
                                        class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                    >
                                        Created At
                                    </div>
                                    <div class="mt-1 text-sm text-gray-700">
                                        {{
                                            formatDateTime(activeTask.created_at) ||
                                            'Unavailable'
                                        }}
                                    </div>
                                </div>
                                <div class="rounded-lg bg-gray-50 px-4 py-3">
                                    <div
                                        class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                    >
                                        Task ID
                                    </div>
                                    <div class="mt-1 text-sm text-gray-700">
                                        #{{ activeTask.id }}
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </Modal>

                <Modal
                    :show="showingCreateModal"
                    max-width="2xl"
                    @close="closeCreateModal"
                >
                    <div class="p-6">
                        <div
                            class="flex flex-col gap-1 border-b border-gray-100 pb-4"
                        >
                            <h3 class="text-lg font-semibold text-gray-900">
                                Create a new task
                            </h3>
                            <p class="text-sm text-gray-500">
                                New tasks are automatically assigned to you and
                                added to this board.
                            </p>
                        </div>

                        <form
                            class="mt-6 grid gap-4 md:grid-cols-2"
                            @submit.prevent="submitTask"
                        >
                            <div class="md:col-span-2">
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

                            <div class="md:col-span-2">
                                <InputLabel
                                    for="description"
                                    value="Description"
                                />
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="5"
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
                                class="md:col-span-2 flex items-center justify-end gap-3 pt-2"
                            >
                                <SecondaryButton @click="closeCreateModal">
                                    Cancel
                                </SecondaryButton>
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
                    </div>
                </Modal>

                <Modal
                    :show="showingEditModal"
                    max-width="2xl"
                    @close="closeEditModal"
                >
                    <div class="p-6">
                        <div
                            class="flex flex-col gap-1 border-b border-gray-100 pb-4"
                        >
                            <h3 class="text-lg font-semibold text-gray-900">
                                Update task
                            </h3>
                            <p class="text-sm text-gray-500">
                                Edit the task details without leaving the board.
                            </p>
                        </div>

                        <form
                            class="mt-6 grid gap-4 md:grid-cols-2"
                            @submit.prevent="submitTaskUpdate"
                        >
                            <div class="md:col-span-2">
                                <InputLabel for="edit-title" value="Title" />
                                <TextInput
                                    id="edit-title"
                                    v-model="editForm.title"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                    maxlength="150"
                                    autocomplete="off"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="editForm.errors.title"
                                />
                            </div>

                            <div>
                                <InputLabel for="edit-status" value="Status" />
                                <select
                                    id="edit-status"
                                    v-model="editForm.status"
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
                                    :message="editForm.errors.status"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    for="edit-priority"
                                    value="Priority"
                                />
                                <select
                                    id="edit-priority"
                                    v-model="editForm.priority"
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
                                    :message="editForm.errors.priority"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel
                                    for="edit-description"
                                    value="Description"
                                />
                                <textarea
                                    id="edit-description"
                                    v-model="editForm.description"
                                    rows="5"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="editForm.errors.description"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    for="edit-deadline_at"
                                    value="Deadline"
                                />
                                <input
                                    id="edit-deadline_at"
                                    v-model="editForm.deadline_at"
                                    type="date"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="editForm.errors.deadline_at"
                                />
                            </div>

                            <div
                                class="md:col-span-2 flex items-center justify-end gap-3 pt-2"
                            >
                                <SecondaryButton @click="closeEditModal">
                                    Cancel
                                </SecondaryButton>
                                <PrimaryButton
                                    :class="{ 'opacity-25': editForm.processing }"
                                    :disabled="editForm.processing"
                                >
                                    {{
                                        editForm.processing
                                            ? 'Saving...'
                                            : 'Save Changes'
                                    }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </Modal>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.task-column--drop-target {
    border-color: rgb(55 65 81);
    background: rgb(249 250 251);
    box-shadow: inset 0 0 0 1px rgb(55 65 81 / 0.1);
}

.task-card {
    cursor: grab;
}

.task-card:focus-visible {
    outline: 2px solid rgb(99 102 241);
    outline-offset: 2px;
}

.task-card--dragging {
    opacity: 0.55;
    transform: rotate(1deg);
    cursor: grabbing;
}

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
