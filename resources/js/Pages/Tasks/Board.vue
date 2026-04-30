<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AddColumnModal from '@/Components/AddColumnModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BoardColumn from '@/Components/BoardColumn.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TaskDetailsModal from '@/Components/TaskDetailsModal.vue';
import TaskFormModal from '@/Components/TaskFormModal.vue';
import {
    defaultStatusLabels,
    formatDate,
    formatDateInput,
    formatDateTime,
    formatPriority,
    formatStatus as formatStatusValue,
    priorityBadgeClass,
} from '@/lib/format';
import {
    appendReplyToComments,
    buildGroupedTasks as buildGroupedTasksFor,
    cloneTasks,
    flattenGroupedTasks as flattenGroupedTasksFor,
    normalizeComment,
    normalizeTask,
    sortTaskList,
} from '@/lib/task';
import { useColumnDragDrop } from '@/composables/useColumnDragDrop';
import { useTaskDragDrop } from '@/composables/useTaskDragDrop';
import axios from 'axios';

const props = defineProps({
    currentBoard: {
        type: Object,
        default: null,
    },
    tasks: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    statusLabels: {
        type: Object,
        default: () => ({}),
    },
    priorities: {
        type: Array,
        default: () => [],
    },
});

const tasks = ref(props.tasks.map(normalizeTask));
const updatingId = ref(null);
const movingColumnStatus = ref(null);
const editingStatusLabel = ref(null);
const statusLabelDraft = ref('');
const savingStatusLabel = ref(null);
const statusLabelInput = ref(null);
const editingBoardName = ref(false);
const boardNameDraft = ref('');
const savingBoardName = ref(false);
const boardNameInput = ref(null);
const editingBoardDescription = ref(false);
const boardDescriptionDraft = ref('');
const savingBoardDescription = ref(false);
const boardDescriptionInput = ref(null);
const showingColumnModal = ref(false);
const showingCreateModal = ref(false);
const showingDetailsModal = ref(false);
const showingEditModal = ref(false);
const selectedTaskId = ref(null);
const editingTaskId = ref(null);
const errorMessage = ref('');
const commentDraft = ref('');
const commentErrors = ref({});
const submittingComment = ref(false);
const activeReplyCommentId = ref(null);
const replyDraft = ref('');
const replyErrors = ref({});
const replyingCommentId = ref(null);
const fallbackBoardStatuses = ['pending', 'in-progress', 'completed'];
const normalizeBoardStatuses = (statuses = []) =>
    statuses.length ? [...statuses] : [...fallbackBoardStatuses];
const boardStatuses = ref(normalizeBoardStatuses(props.statuses));
const defaultStatus = boardStatuses.value.includes('pending')
    ? 'pending'
    : (boardStatuses.value[0] ?? 'pending');
const defaultPriority = props.priorities.includes('medium')
    ? 'medium'
    : (props.priorities[0] ?? 'medium');
const maxTaskTags = 10;
const maxTaskTagLength = 30;

const blankTaskData = () => ({
    title: '',
    description: '',
    status: defaultStatus,
    priority: defaultPriority,
    tags: [],
    progress: 0,
    deadline_at: '',
});

const form = useForm(blankTaskData());
const editForm = useForm(blankTaskData());
const columnForm = useForm({
    label: '',
});

const buildStatusLabels = (labels = {}) => ({
    ...defaultStatusLabels,
    ...Object.fromEntries(
        Object.entries(labels)
            .filter(
                ([status, label]) =>
                    boardStatuses.value.includes(status) &&
                    typeof label === 'string' &&
                    label.trim() !== '',
            )
            .map(([status, label]) => [status, label.trim()]),
    ),
});

const boardStatusLabels = ref(buildStatusLabels(props.statusLabels));
const currentBoardId = computed(() => props.currentBoard?.id ?? null);
const currentBoardName = ref(props.currentBoard?.name ?? 'Task Board');
const currentBoardDescription = ref(props.currentBoard?.description ?? '');
const priorityOptions = computed(() =>
    props.priorities.length
        ? props.priorities
        : ['low', 'medium', 'high'],
);

watch(
    () => props.currentBoard,
    (nextBoard) => {
        currentBoardName.value = nextBoard?.name ?? 'Task Board';
        currentBoardDescription.value = nextBoard?.description ?? '';
    },
);

watch(
    () => props.tasks,
    (nextTasks) => {
        tasks.value = nextTasks.map(normalizeTask);
    },
);

watch(
    () => props.statuses,
    (nextStatuses) => {
        boardStatuses.value = normalizeBoardStatuses(nextStatuses);
        boardStatusLabels.value = buildStatusLabels(props.statusLabels);
    },
);

watch(
    () => props.statusLabels,
    (nextStatusLabels) => {
        boardStatusLabels.value = buildStatusLabels(nextStatusLabels);
    },
);

const formatStatus = (status) =>
    formatStatusValue(status, boardStatusLabels.value);

const activeTask = computed(() =>
    tasks.value.find((task) => task.id === selectedTaskId.value) ?? null,
);

const setTaskFormValues = (taskForm, values = {}) => {
    taskForm.title = values.title ?? '';
    taskForm.description = values.description ?? '';
    taskForm.status = values.status ?? defaultStatus;
    taskForm.priority = values.priority ?? defaultPriority;
    taskForm.tags = Array.isArray(values.tags) ? [...values.tags] : [];
    taskForm.progress = values.progress ?? 0;
    taskForm.deadline_at = values.deadline_at ?? '';
};

const resolveFieldError = (errors = {}, field) =>
    errors[field] ??
    Object.entries(errors).find(([key]) => key.startsWith(`${field}.`))?.[1] ??
    '';

const resetCommentForm = () => {
    commentDraft.value = '';
    commentErrors.value = {};
    submittingComment.value = false;
    activeReplyCommentId.value = null;
    replyDraft.value = '';
    replyErrors.value = {};
    replyingCommentId.value = null;
};

const closeCreateModal = () => {
    showingCreateModal.value = false;
    setTaskFormValues(form, blankTaskData());
    form.clearErrors();
};

const closeColumnModal = () => {
    showingColumnModal.value = false;
    columnForm.reset();
    columnForm.clearErrors();
};

const setStatusLabelInput = (element) => {
    statusLabelInput.value = element;
};

const setBoardNameInput = (element) => {
    boardNameInput.value = element;
};

const setBoardDescriptionInput = (element) => {
    boardDescriptionInput.value = element;
};

const cancelBoardNameEdit = () => {
    editingBoardName.value = false;
    boardNameDraft.value = '';
};

const startBoardNameEdit = async () => {
    if (
        savingBoardName.value ||
        savingBoardDescription.value ||
        !currentBoardId.value
    ) {
        return;
    }

    cancelBoardDescriptionEdit();
    editingBoardName.value = true;
    boardNameDraft.value = currentBoardName.value;

    await nextTick();

    boardNameInput.value?.focus();
    boardNameInput.value?.select();
};

const cancelBoardDescriptionEdit = () => {
    editingBoardDescription.value = false;
    boardDescriptionDraft.value = '';
};

const startBoardDescriptionEdit = async () => {
    if (
        savingBoardName.value ||
        savingBoardDescription.value ||
        !currentBoardId.value
    ) {
        return;
    }

    cancelBoardNameEdit();
    editingBoardDescription.value = true;
    boardDescriptionDraft.value = currentBoardDescription.value;

    await nextTick();

    boardDescriptionInput.value?.focus();
    boardDescriptionInput.value?.select();
};

const saveBoardName = async () => {
    if (!editingBoardName.value || savingBoardName.value || !currentBoardId.value) {
        return;
    }

    const nextName = boardNameDraft.value.trim();

    if (!nextName) {
        errorMessage.value = 'Board title cannot be empty.';
        return;
    }

    if (nextName === currentBoardName.value) {
        cancelBoardNameEdit();
        return;
    }

    errorMessage.value = '';
    savingBoardName.value = true;

    try {
        const response = await axios.patch(
            route('boards.update', {
                board: currentBoardId.value,
            }),
            { name: nextName },
        );

        currentBoardName.value = response?.data?.board?.name ?? nextName;
        currentBoardDescription.value = response?.data?.board?.description ?? currentBoardDescription.value;

        cancelBoardNameEdit();
        router.reload({
            only: ['boards', 'currentBoard'],
            preserveScroll: true,
            preserveState: true,
        });
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to update board title. Please try again.';
    } finally {
        savingBoardName.value = false;
    }
};

const saveBoardDescription = async () => {
    if (
        !editingBoardDescription.value ||
        savingBoardDescription.value ||
        !currentBoardId.value
    ) {
        return;
    }

    const nextDescription = boardDescriptionDraft.value.trim();

    if (nextDescription === currentBoardDescription.value) {
        cancelBoardDescriptionEdit();
        return;
    }

    errorMessage.value = '';
    savingBoardDescription.value = true;

    try {
        const response = await axios.patch(
            route('boards.update', {
                board: currentBoardId.value,
            }),
            { description: nextDescription },
        );

        currentBoardName.value = response?.data?.board?.name ?? currentBoardName.value;
        currentBoardDescription.value = response?.data?.board?.description ?? '';

        cancelBoardDescriptionEdit();
        router.reload({
            only: ['boards', 'currentBoard'],
            preserveScroll: true,
            preserveState: true,
        });
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to update board description. Please try again.';
    } finally {
        savingBoardDescription.value = false;
    }
};

const cancelStatusLabelEdit = () => {
    editingStatusLabel.value = null;
    statusLabelDraft.value = '';
};

const startStatusLabelEdit = async (status) => {
    if (savingStatusLabel.value) {
        return;
    }

    editingStatusLabel.value = status;
    statusLabelDraft.value = formatStatus(status);

    await nextTick();

    statusLabelInput.value?.focus();
    statusLabelInput.value?.select();
};

const saveStatusLabel = async (status) => {
    if (
        editingStatusLabel.value !== status ||
        savingStatusLabel.value === status
    ) {
        return;
    }

    const nextLabel = statusLabelDraft.value.trim();

    if (!nextLabel) {
        errorMessage.value = 'Column title cannot be empty.';
        return;
    }

    if (nextLabel === formatStatus(status)) {
        cancelStatusLabelEdit();
        return;
    }

    errorMessage.value = '';
    savingStatusLabel.value = status;

    const previousLabels = { ...boardStatusLabels.value };
    boardStatusLabels.value = {
        ...boardStatusLabels.value,
        [status]: nextLabel,
    };

    let shouldClose = false;

    try {
        const response = await axios.patch(
            route('tasks.status-labels.update', {
                board: currentBoardId.value,
                status,
            }),
            { label: nextLabel },
        );

        boardStatusLabels.value = buildStatusLabels(
            response?.data?.status_labels,
        );
        shouldClose = true;
    } catch (error) {
        boardStatusLabels.value = previousLabels;
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to update column title. Please try again.';
    } finally {
        savingStatusLabel.value = null;

        if (shouldClose) {
            cancelStatusLabelEdit();
        }
    }
};

const moveColumnLocally = (status, beforeStatus = null) => {
    if (!boardStatuses.value.includes(status)) {
        return false;
    }

    const reorderedStatuses = boardStatuses.value.filter(
        (candidate) => candidate !== status,
    );
    const insertAt = beforeStatus === null
        ? reorderedStatuses.length
        : reorderedStatuses.findIndex((candidate) => candidate === beforeStatus);

    reorderedStatuses.splice(
        insertAt === -1 ? reorderedStatuses.length : insertAt,
        0,
        status,
    );

    if (
        reorderedStatuses.length === boardStatuses.value.length &&
        reorderedStatuses.every(
            (candidate, index) => candidate === boardStatuses.value[index],
        )
    ) {
        return false;
    }

    boardStatuses.value = reorderedStatuses;

    return true;
};

const getBeforeStatusForColumnDrop = (targetStatus, position) => {
    const destinationStatuses = boardStatuses.value.filter(
        (status) => status !== draggedColumnStatus.value,
    );
    const targetIndex = destinationStatuses.findIndex(
        (status) => status === targetStatus,
    );

    if (targetIndex === -1) {
        return null;
    }

    if (position === 'before') {
        return targetStatus;
    }

    return destinationStatuses[targetIndex + 1] ?? null;
};

const reorderBoardColumn = async (status, beforeStatus = null) => {
    if (movingColumnStatus.value || !status) {
        return;
    }

    errorMessage.value = '';
    movingColumnStatus.value = status;
    const previousStatuses = [...boardStatuses.value];
    const moved = moveColumnLocally(status, beforeStatus);

    if (!moved) {
        movingColumnStatus.value = null;
        columnDragDrop.reset();
        return;
    }

    try {
        const response = await axios.patch(
            route('tasks.columns.reorder', {
                board: currentBoardId.value,
                status,
            }),
            { before_status: beforeStatus },
        );

        if (Array.isArray(response?.data?.statuses)) {
            boardStatuses.value = response.data.statuses;
        }

        if (response?.data?.status_labels) {
            boardStatusLabels.value = buildStatusLabels(
                response.data.status_labels,
            );
        }
    } catch (error) {
        boardStatuses.value = previousStatuses;
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to reorder board columns. Please try again.';
    } finally {
        movingColumnStatus.value = null;
        columnDragDrop.reset();
    }
};

const columnDragDrop = useColumnDragDrop({
    isBusy: computed(() => movingColumnStatus.value !== null || updatingId.value !== null),
    computeBeforeStatus: getBeforeStatusForColumnDrop,
    onReorder: reorderBoardColumn,
});

const {
    draggedColumnStatus,
    isDraggingColumn,
    isColumnReorderDropTarget,
    onColumnDragStart: onBoardColumnDragStart,
    onColumnDragEnd: onBoardColumnDragEnd,
    onLaneDragOver: onBoardLaneDragOver,
    onLaneDrop: onBoardLaneDrop,
} = columnDragDrop;

// Both task and column drags fire on the same board section. Column drag
// takes precedence; otherwise we fall through to the task handlers.
const onBoardSectionDragOver = (event, status) => {
    if (columnDragDrop.onColumnDragOver(event, status)) {
        return;
    }

    onColumnDragOver(event, status);
};

const onBoardSectionDrop = async (status) => {
    if (await columnDragDrop.onColumnDrop(status)) {
        return;
    }

    await onColumnDrop(status);
};

const openTaskDetails = (task) => {
    resetCommentForm();
    selectedTaskId.value = task.id;
    showingDetailsModal.value = true;
};

const closeTaskDetails = () => {
    showingDetailsModal.value = false;
    selectedTaskId.value = null;
    resetCommentForm();
};

const openEditModal = (task) => {
    editingTaskId.value = task.id;
    setTaskFormValues(editForm, {
        title: task.title,
        description: task.description ?? '',
        status: task.status,
        priority: task.priority,
        tags: task.tags ?? [],
        progress: task.progress ?? 0,
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

    Object.keys(grouped).forEach((status) => {
        grouped[status] = sortTaskList(grouped[status]);
    });

    return grouped;
});

const buildGroupedTasks = (taskList) =>
    buildGroupedTasksFor(taskList, boardStatuses.value);

const flattenGroupedTasks = (grouped) =>
    flattenGroupedTasksFor(grouped, boardStatuses.value);

const applyTaskOrderUpdates = (orderUpdates) => {
    if (!Array.isArray(orderUpdates) || !orderUpdates.length) {
        return;
    }

    const updatesById = new Map(
        orderUpdates.map((update) => [update.id, update]),
    );

    tasks.value = tasks.value.map((task) => {
        const update = updatesById.get(task.id);

        if (!update) {
            return task;
        }

        return normalizeTask({
            ...task,
            status: update.status ?? task.status,
            sort_order: update.sort_order ?? task.sort_order,
        });
    });
};

const moveTaskLocally = (taskId, destinationStatus, beforeTaskId = null) => {
    const grouped = buildGroupedTasks(cloneTasks(tasks.value));
    let movingTask = null;
    let sourceStatus = null;

    Object.keys(grouped).forEach((status) => {
        grouped[status] = grouped[status].filter((task) => {
            if (task.id !== taskId) {
                return true;
            }

            movingTask = task;
            sourceStatus = status;

            return false;
        });
    });

    if (!movingTask || !sourceStatus) {
        return false;
    }

    if (!grouped[destinationStatus]) {
        grouped[destinationStatus] = [];
    }

    movingTask.status = destinationStatus;

    const destinationTasks = grouped[destinationStatus];
    const insertAt = beforeTaskId === null
        ? destinationTasks.length
        : destinationTasks.findIndex((task) => task.id === beforeTaskId);

    destinationTasks.splice(
        insertAt === -1 ? destinationTasks.length : insertAt,
        0,
        movingTask,
    );

    [sourceStatus, destinationStatus].forEach((status) => {
        if (!grouped[status]) {
            return;
        }

        grouped[status] = grouped[status].map((task, index) =>
            normalizeTask({
                ...task,
                sort_order: index + 1,
            }),
        );
    });

    tasks.value = flattenGroupedTasks(grouped);

    return true;
};

const reorderTask = async (task, destinationStatus, beforeTaskId = null) => {
    if (updatingId.value) {
        return;
    }

    if (!task) {
        return;
    }

    errorMessage.value = '';
    updatingId.value = task.id;
    const previousTasks = cloneTasks(tasks.value);
    const moved = moveTaskLocally(task.id, destinationStatus, beforeTaskId);

    if (!moved) {
        updatingId.value = null;
        resetDragState();
        return;
    }

    try {
        const response = await axios.patch(
            route('tasks.reorder', {
                board: currentBoardId.value,
                task: task.id,
            }),
            {
                status: destinationStatus,
                before_id: beforeTaskId,
            },
        );

        applyTaskOrderUpdates(response?.data?.orders);

        if (response?.data?.task) {
            applyTaskOrderUpdates([response.data.task]);
        }
    } catch (error) {
        tasks.value = previousTasks.map(normalizeTask);
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to reorder task. Please try again.';
    } finally {
        updatingId.value = null;
        taskDragDrop.reset();
    }
};

const taskDragDrop = useTaskDragDrop({
    tasks,
    tasksByStatus,
    isBusy: computed(() => updatingId.value !== null),
    onReorder: reorderTask,
});

const {
    draggedTaskId,
    isDraggingTask,
    isColumnDropTarget,
    isTaskDropTarget,
    onTaskDragStart,
    onTaskDragEnd,
    onColumnDragOver,
    onTaskDragOver,
    onTaskDrop,
    onColumnDrop,
} = taskDragDrop;

const appendCommentToTask = (taskId, comment) => {
    const normalizedComment = normalizeComment(comment);

    tasks.value = tasks.value.map((task) => {
        if (task.id !== taskId) {
            return task;
        }

        return normalizeTask({
            ...task,
            comments: [...(task.comments ?? []), normalizedComment],
        });
    });
};

const appendReplyToTask = (taskId, parentId, comment) => {
    const normalizedReply = normalizeComment(comment);

    tasks.value = tasks.value.map((task) => {
        if (task.id !== taskId) {
            return task;
        }

        return normalizeTask({
            ...task,
            comments: appendReplyToComments(
                task.comments ?? [],
                parentId,
                normalizedReply,
            ),
        });
    });
};

const submitComment = async ({ content, parentId = null }) => {
    if (!activeTask.value) {
        return false;
    }

    if (parentId === null && submittingComment.value) {
        return false;
    }

    if (parentId !== null && replyingCommentId.value === parentId) {
        return false;
    }

    if (parentId === null) {
        submittingComment.value = true;
        commentErrors.value = {};
    } else {
        replyingCommentId.value = parentId;
        replyErrors.value = {};
    }

    errorMessage.value = '';

    try {
        const response = await axios.post(
            route('tasks.comments.store', {
                task: activeTask.value.id,
            }),
            {
                content,
                parent_id: parentId,
            },
        );

        if (response?.data?.comment) {
            if (parentId === null) {
                appendCommentToTask(activeTask.value.id, response.data.comment);
            } else {
                appendReplyToTask(
                    activeTask.value.id,
                    parentId,
                    response.data.comment,
                );
            }
        }

        return true;
    } catch (error) {
        if (error?.response?.status === 422) {
            const errors = error.response.data.errors ?? {};

            if (parentId === null) {
                commentErrors.value = errors;
            } else {
                replyErrors.value = errors;
            }

            return false;
        }

        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to add a comment right now. Please try again.';

        return false;
    } finally {
        if (parentId === null) {
            submittingComment.value = false;
        } else {
            replyingCommentId.value = null;
        }
    }
};

const submitTaskComment = async () => {
    if (!activeTask.value) {
        return;
    }

    const success = await submitComment({
        content: commentDraft.value,
    });

    if (success) {
        commentDraft.value = '';
    }
};

const startReply = async (comment) => {
    if (activeReplyCommentId.value === comment.id) {
        activeReplyCommentId.value = null;
        replyDraft.value = '';
        replyErrors.value = {};
        return;
    }

    activeReplyCommentId.value = comment.id;
    replyDraft.value = '';
    replyErrors.value = {};

    await nextTick();
};

const cancelReply = () => {
    activeReplyCommentId.value = null;
    replyDraft.value = '';
    replyErrors.value = {};
};

const submitReply = async (comment) => {
    const success = await submitComment({
        content: replyDraft.value,
        parentId: comment.id,
    });

    if (success) {
        cancelReply();
    }
};

const submitTask = () => {
    form.post(route('tasks.store', { board: currentBoardId.value }), {
        preserveScroll: true,
        onSuccess: () => closeCreateModal(),
    });
};

const submitColumn = () => {
    columnForm.post(route('tasks.columns.store', { board: currentBoardId.value }), {
        preserveScroll: true,
        onSuccess: () => closeColumnModal(),
    });
};

const submitTaskUpdate = () => {
    if (!editingTaskId.value) {
        return;
    }

    editForm.patch(route('tasks.update', {
        board: currentBoardId.value,
        task: editingTaskId.value,
    }), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};
</script>

<template>
    <Head :title="`${currentBoardName} - Task Board`" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="min-w-0 flex-1 space-y-1">
                    <input
                        v-if="editingBoardName"
                        :ref="setBoardNameInput"
                        v-model="boardNameDraft"
                        type="text"
                        maxlength="100"
                        class="block w-full max-w-lg rounded-md border-gray-300 px-2 py-1 text-xl font-semibold leading-tight text-gray-800 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                        @keydown.enter.prevent="saveBoardName"
                        @keydown.esc.prevent="cancelBoardNameEdit"
                        @blur="saveBoardName"
                    />
                    <button
                        v-else
                        type="button"
                        class="-mx-2 block max-w-full rounded-md px-2 py-1 text-left text-xl font-semibold leading-tight text-gray-800 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="startBoardNameEdit"
                    >
                        <span class="block truncate">
                            {{ currentBoardName }}
                        </span>
                    </button>
                    <textarea
                        v-if="editingBoardDescription"
                        :ref="setBoardDescriptionInput"
                        v-model="boardDescriptionDraft"
                        rows="3"
                        maxlength="280"
                        class="block w-full max-w-none resize-y rounded-md border-gray-300 px-3 py-2 text-sm text-gray-600 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                        placeholder="Add board description"
                        @keydown.ctrl.enter.prevent="saveBoardDescription"
                        @keydown.meta.enter.prevent="saveBoardDescription"
                        @keydown.esc.prevent="cancelBoardDescriptionEdit"
                        @blur="saveBoardDescription"
                    />
                    <button
                        v-else
                        type="button"
                        class="-mx-2 block max-w-full rounded-md px-2 py-1 text-left text-sm transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        :class="currentBoardDescription
                            ? 'text-gray-500'
                            : 'text-gray-400'"
                        @click="startBoardDescriptionEdit"
                    >
                        <span class="board-description-preview block break-words">
                            {{
                                currentBoardDescription ||
                                    'Add board description'
                            }}
                        </span>
                    </button>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-3">
                    <SecondaryButton @click="showingColumnModal = true">
                        Add Column
                    </SecondaryButton>
                    <PrimaryButton @click="showingCreateModal = true">
                        New Task
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="min-h-[calc(100vh-9rem)] py-8">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <div class="h-full overflow-x-auto overflow-y-hidden pb-2">
                    <div
                        class="flex min-h-[calc(100vh-13rem)] w-max min-w-full items-stretch justify-center gap-6"
                        @dragover="onBoardLaneDragOver"
                        @drop.stop.prevent="onBoardLaneDrop"
                    >
                        <BoardColumn
                            v-for="status in boardStatuses"
                            :key="status"
                            v-model:label-draft="statusLabelDraft"
                            :status="status"
                            :label="formatStatus(status)"
                            :tasks="tasksByStatus[status] ?? []"
                            :is-editing-label="editingStatusLabel === status"
                            :is-dragging-column="isDraggingColumn(status)"
                            :is-moving="movingColumnStatus === status"
                            :is-task-drop-target="isColumnDropTarget(status)"
                            :is-reorder-drop-before="isColumnReorderDropTarget(status, 'before')"
                            :is-reorder-drop-after="isColumnReorderDropTarget(status, 'after')"
                            :columns-busy="movingColumnStatus !== null"
                            :updating-task-id="updatingId"
                            :is-task-dragging="isDraggingTask"
                            :is-task-drop-before="(taskId) => isTaskDropTarget(taskId, 'before')"
                            :is-task-drop-after="(taskId) => isTaskDropTarget(taskId, 'after')"
                            @section-drag-over="(event) => onBoardSectionDragOver(event, status)"
                            @section-drop="onBoardSectionDrop(status)"
                            @column-drag-start="(event) => onBoardColumnDragStart(event, status)"
                            @column-drag-end="onBoardColumnDragEnd"
                            @start-edit-label="startStatusLabelEdit(status)"
                            @save-label="saveStatusLabel(status)"
                            @cancel-edit-label="cancelStatusLabelEdit"
                            @task-drag-start="onTaskDragStart"
                            @task-drag-over="onTaskDragOver"
                            @task-drag-end="onTaskDragEnd"
                            @task-drop="onTaskDrop"
                            @task-open-details="openTaskDetails"
                            @task-open-edit="openEditModal"
                        />
                    </div>
                </div>

                <p
                    v-if="errorMessage"
                    class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ errorMessage }}
                </p>

                <AddColumnModal
                    :show="showingColumnModal"
                    :form="columnForm"
                    @close="closeColumnModal"
                    @submit="submitColumn"
                />

                <TaskDetailsModal
                    :show="showingDetailsModal"
                    :task="activeTask"
                    :format-status="formatStatus"
                    v-model:comment-draft="commentDraft"
                    v-model:reply-draft="replyDraft"
                    :comment-errors="commentErrors"
                    :submitting-comment="submittingComment"
                    :reply-errors="replyErrors"
                    :active-reply-comment-id="activeReplyCommentId"
                    :replying-comment-id="replyingCommentId"
                    @close="closeTaskDetails"
                    @open-edit="openEditFromDetails"
                    @submit-comment="submitTaskComment"
                    @start-reply="startReply"
                    @cancel-reply="cancelReply"
                    @submit-reply="submitReply"
                />

                <TaskFormModal
                    mode="create"
                    :show="showingCreateModal"
                    :form="form"
                    :statuses="boardStatuses"
                    :format-status="formatStatus"
                    :priorities="priorityOptions"
                    :max-tags="maxTaskTags"
                    :max-tag-length="maxTaskTagLength"
                    :resolve-field-error="resolveFieldError"
                    @close="closeCreateModal"
                    @submit="submitTask"
                />

                <TaskFormModal
                    mode="edit"
                    :show="showingEditModal"
                    :form="editForm"
                    :statuses="boardStatuses"
                    :format-status="formatStatus"
                    :priorities="priorityOptions"
                    :max-tags="maxTaskTags"
                    :max-tag-length="maxTaskTagLength"
                    :resolve-field-error="resolveFieldError"
                    @close="closeEditModal"
                    @submit="submitTaskUpdate"
                />
            </div>
        </div>
    </AuthenticatedLayout>
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
