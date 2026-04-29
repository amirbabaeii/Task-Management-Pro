<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CommentThread from '@/Components/CommentThread.vue';
import InputError from '@/Components/InputError.vue';
import TaskCard from '@/Components/TaskCard.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TagInput from '@/Components/TagInput.vue';
import TextInput from '@/Components/TextInput.vue';
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
    commentCount,
    flattenGroupedTasks as flattenGroupedTasksFor,
    hiddenTagCount,
    normalizeComment,
    normalizeTask,
    sortTaskList,
    visibleTags,
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
                        <section
                            v-for="status in boardStatuses"
                            :key="status"
                            class="task-column flex h-full w-80 min-w-80 shrink-0 flex-col rounded-lg border border-gray-200 bg-white shadow-sm transition"
                            :class="{
                                'task-column--drop-target': isColumnDropTarget(status),
                                'task-column--dragging': isDraggingColumn(status),
                                'task-column--drop-before': isColumnReorderDropTarget(status, 'before'),
                                'task-column--drop-after': isColumnReorderDropTarget(status, 'after'),
                                'task-column--moving': movingColumnStatus === status,
                            }"
                            @dragover.stop="onBoardSectionDragOver($event, status)"
                            @drop.stop.prevent="onBoardSectionDrop(status)"
                        >
                            <div
                                class="flex items-center justify-between border-b border-gray-100 px-4 py-3"
                            >
                                <div class="flex min-w-0 flex-1 items-center gap-2 pr-3">
                                    <button
                                        type="button"
                                        class="column-drag-handle flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        title="Drag column"
                                        draggable="true"
                                        @click.stop
                                        @dragstart.stop="onBoardColumnDragStart($event, status)"
                                        @dragend.stop="onBoardColumnDragEnd"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 16 16"
                                            fill="currentColor"
                                            aria-hidden="true"
                                        >
                                            <circle cx="5" cy="4" r="1.25" />
                                            <circle cx="11" cy="4" r="1.25" />
                                            <circle cx="5" cy="8" r="1.25" />
                                            <circle cx="11" cy="8" r="1.25" />
                                            <circle cx="5" cy="12" r="1.25" />
                                            <circle cx="11" cy="12" r="1.25" />
                                        </svg>
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <input
                                            v-if="editingStatusLabel === status"
                                            :ref="setStatusLabelInput"
                                            v-model="statusLabelDraft"
                                            type="text"
                                            maxlength="40"
                                            class="block w-full rounded-md border-gray-300 px-2 py-1 text-sm font-semibold text-gray-700 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                            @click.stop
                                            @keydown.enter.prevent="saveStatusLabel(status)"
                                            @keydown.esc.prevent="cancelStatusLabelEdit"
                                            @blur="saveStatusLabel(status)"
                                        />
                                        <button
                                            v-else
                                            type="button"
                                            class="block max-w-full truncate rounded-md px-2 py-1 text-left text-sm font-semibold text-gray-700 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                            @click.stop="startStatusLabelEdit(status)"
                                        >
                                            {{ formatStatus(status) }}
                                        </button>
                                    </div>
                                </div>
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
                                <TaskCard
                                    v-for="task in tasksByStatus[status]"
                                    :key="task.id"
                                    :task="task"
                                    :is-dragging="isDraggingTask(task.id)"
                                    :is-drop-before-target="isTaskDropTarget(task.id, 'before')"
                                    :is-drop-after-target="isTaskDropTarget(task.id, 'after')"
                                    :can-drag="updatingId !== task.id && movingColumnStatus === null"
                                    :is-updating="updatingId === task.id"
                                    @open-details="openTaskDetails"
                                    @open-edit="openEditModal"
                                    @drag-start="onTaskDragStart"
                                    @drag-over="onTaskDragOver"
                                    @drop="onTaskDrop"
                                    @drag-end="onTaskDragEnd"
                                />
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

                <Modal
                    :show="showingColumnModal"
                    max-width="lg"
                    @close="closeColumnModal"
                >
                    <div class="p-6">
                        <div
                            class="flex flex-col gap-1 border-b border-gray-100 pb-4"
                        >
                            <h3 class="text-lg font-semibold text-gray-900">
                                Add a board column
                            </h3>
                            <p class="text-sm text-gray-500">
                                Create a new status column for this board.
                            </p>
                        </div>

                        <form
                            class="mt-6 space-y-4"
                            @submit.prevent="submitColumn"
                        >
                            <div>
                                <InputLabel
                                    for="column-label"
                                    value="Column Title"
                                />
                                <TextInput
                                    id="column-label"
                                    v-model="columnForm.label"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                    maxlength="40"
                                    autocomplete="off"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="columnForm.errors.label"
                                />
                            </div>

                            <div
                                class="flex items-center justify-end gap-3 pt-2"
                            >
                                <SecondaryButton @click="closeColumnModal">
                                    Cancel
                                </SecondaryButton>
                                <PrimaryButton
                                    :class="{
                                        'opacity-25': columnForm.processing,
                                    }"
                                    :disabled="columnForm.processing"
                                >
                                    {{
                                        columnForm.processing
                                            ? 'Adding...'
                                            : 'Add Column'
                                    }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </Modal>

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
                                        class="rounded-full border px-2.5 py-1 font-semibold uppercase tracking-wide"
                                        :class="priorityBadgeClass(activeTask.priority)"
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
                                        Tags
                                    </h4>
                                    <span class="text-xs text-gray-500">
                                        {{ activeTask.tags.length }}
                                        {{ activeTask.tags.length === 1 ? 'tag' : 'tags' }}
                                    </span>
                                </div>
                                <div
                                    v-if="activeTask.tags.length"
                                    class="flex flex-wrap gap-2"
                                >
                                    <span
                                        v-for="tag in activeTask.tags"
                                        :key="`${activeTask.id}-tag-${tag}`"
                                        class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-sky-700"
                                    >
                                        {{ tag }}
                                    </span>
                                </div>
                                <p
                                    v-else
                                    class="text-sm text-gray-500"
                                >
                                    No tags added.
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

                            <section class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-gray-900">
                                        Comments
                                    </h4>
                                    <span class="text-xs text-gray-500">
                                        {{
                                            commentCount(activeTask.comments)
                                        }}
                                        {{
                                            commentCount(activeTask.comments) ===
                                            1
                                                ? 'comment'
                                                : 'comments'
                                        }}
                                    </span>
                                </div>

                                <form
                                    class="space-y-3"
                                    @submit.prevent="submitTaskComment"
                                >
                                    <textarea
                                        v-model="commentDraft"
                                        rows="3"
                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                        placeholder="Add a comment..."
                                    />
                                    <InputError
                                        :message="commentErrors.content?.[0]"
                                    />
                                    <div class="flex justify-end">
                                        <PrimaryButton
                                            :class="{
                                                'opacity-25':
                                                    submittingComment ||
                                                    !commentDraft.trim(),
                                            }"
                                            :disabled="
                                                submittingComment ||
                                                !commentDraft.trim()
                                            "
                                        >
                                            {{
                                                submittingComment
                                                    ? 'Posting...'
                                                    : 'Post Comment'
                                            }}
                                        </PrimaryButton>
                                    </div>
                                </form>

                                <div
                                    v-if="activeTask.comments.length"
                                    class="space-y-3"
                                >
                                    <CommentThread
                                        v-for="comment in activeTask.comments"
                                        :key="comment.id"
                                        v-model:reply-draft="replyDraft"
                                        :comment="comment"
                                        :active-reply-comment-id="activeReplyCommentId"
                                        :reply-errors="replyErrors"
                                        :replying-comment-id="replyingCommentId"
                                        @start-reply="startReply"
                                        @cancel-reply="cancelReply"
                                        @submit-reply="submitReply"
                                    />
                                </div>
                                <div
                                    v-else
                                    class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-500"
                                >
                                    No comments yet.
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

                            <div class="md:col-span-2">
                                <InputLabel for="tags" value="Tags" />
                                <TagInput
                                    id="tags"
                                    v-model="form.tags"
                                    placeholder="Add a tag, e.g. backend"
                                    :max-tags="maxTaskTags"
                                    :max-tag-length="maxTaskTagLength"
                                    :error="resolveFieldError(form.errors, 'tags')"
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

                            <div class="md:col-span-2">
                                <InputLabel for="edit-tags" value="Tags" />
                                <TagInput
                                    id="edit-tags"
                                    v-model="editForm.tags"
                                    placeholder="Add a tag, e.g. backend"
                                    :max-tags="maxTaskTags"
                                    :max-tag-length="maxTaskTagLength"
                                    :error="resolveFieldError(editForm.errors, 'tags')"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel
                                    for="edit-progress"
                                    value="Progress"
                                />
                                <div class="mt-2">
                                    <input
                                        id="edit-progress"
                                        v-model.number="editForm.progress"
                                        class="task-progress-slider block w-full"
                                        type="range"
                                        min="0"
                                        max="100"
                                        step="5"
                                        :style="{
                                            '--task-progress': `${editForm.progress}%`,
                                        }"
                                    />
                                    <div class="mt-1 text-sm text-gray-500">
                                        {{ editForm.progress }}% complete
                                    </div>
                                </div>
                                <InputError
                                    class="mt-2"
                                    :message="editForm.errors.progress"
                                />
                            </div>

                            <div class="md:col-span-2">
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
.column-drag-handle {
    cursor: grab;
}

.column-drag-handle:active {
    cursor: grabbing;
}

.task-column--drop-target {
    border-color: rgb(55 65 81);
    background: rgb(249 250 251);
    box-shadow: inset 0 0 0 1px rgb(55 65 81 / 0.1);
}

.task-column--moving {
    opacity: 0.75;
}

.task-column--dragging {
    opacity: 0.55;
}

.task-column--drop-before {
    box-shadow:
        inset 4px 0 0 rgb(31 41 55),
        0 1px 2px rgb(15 23 42 / 0.08);
}

.task-column--drop-after {
    box-shadow:
        inset -4px 0 0 rgb(31 41 55),
        0 1px 2px rgb(15 23 42 / 0.08);
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

.board-description-preview {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 4;
    line-clamp: 4;
}

</style>
