<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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

const normalizeComment = (comment) => ({
    ...comment,
    id: Number(comment.id ?? 0),
    user: {
        id: Number(comment.user?.id ?? 0),
        name: comment.user?.name ?? 'Unknown user',
    },
});

const normalizeTask = (task) => ({
    ...task,
    progress: Number(task.progress ?? 0),
    sort_order: Number(task.sort_order ?? 0),
    comments: Array.isArray(task.comments)
        ? task.comments.map(normalizeComment)
        : [],
});

const cloneTasks = (taskList) => taskList.map((task) => ({ ...task }));

const sortTaskList = (taskList) =>
    [...taskList].sort(
        (left, right) =>
            (left.sort_order ?? 0) - (right.sort_order ?? 0) ||
            left.id - right.id,
    );

const tasks = ref(props.tasks.map(normalizeTask));
const updatingId = ref(null);
const movingColumnStatus = ref(null);
const draggedTaskId = ref(null);
const dragOverStatus = ref(null);
const dragOverTaskId = ref(null);
const dragInsertPosition = ref('before');
const draggedColumnStatus = ref(null);
const columnDragOverStatus = ref(null);
const columnDragInsertPosition = ref('before');
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

const blankTaskData = () => ({
    title: '',
    description: '',
    status: defaultStatus,
    priority: defaultPriority,
    progress: 0,
    deadline_at: '',
});

const form = useForm(blankTaskData());
const editForm = useForm(blankTaskData());
const columnForm = useForm({
    label: '',
});

const defaultStatusLabels = {
    pending: 'Pending',
    'in-progress': 'In Progress',
    completed: 'Completed',
};
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
    boardStatusLabels.value[status] ?? defaultStatusLabels[status] ?? status;

const formatPriority = (priority) => {
    if (!priority) {
        return 'Unspecified';
    }

    return `${priority.charAt(0).toUpperCase()}${priority.slice(1)}`;
};

const priorityBadgeClass = (priority) => {
    if (priority === 'low') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (priority === 'high') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    return 'border-amber-200 bg-amber-50 text-amber-700';
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
    taskForm.progress = values.progress ?? 0;
    taskForm.deadline_at = values.deadline_at ?? '';
};

const resetCommentForm = () => {
    commentDraft.value = '';
    commentErrors.value = {};
    submittingComment.value = false;
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

const isDraggingColumn = (status) => draggedColumnStatus.value === status;

const isColumnReorderDropTarget = (status, position) =>
    draggedColumnStatus.value !== null &&
    columnDragOverStatus.value === status &&
    columnDragInsertPosition.value === position;

const resetColumnDragState = () => {
    draggedColumnStatus.value = null;
    columnDragOverStatus.value = null;
    columnDragInsertPosition.value = 'before';
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
        resetColumnDragState();
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
        resetColumnDragState();
    }
};

const onBoardColumnDragStart = (event, status) => {
    if (movingColumnStatus.value || updatingId.value) {
        event.preventDefault();
        return;
    }

    draggedColumnStatus.value = status;
    columnDragOverStatus.value = status;
    columnDragInsertPosition.value = 'before';

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', status);
    }
};

const onBoardColumnDragEnd = () => {
    resetColumnDragState();
};

const onBoardSectionDragOver = (event, status) => {
    if (draggedColumnStatus.value !== null) {
        event.preventDefault();

        if (draggedColumnStatus.value === status) {
            columnDragOverStatus.value = status;
            columnDragInsertPosition.value = 'before';
            return;
        }

        const bounds = event.currentTarget.getBoundingClientRect();
        const midpoint = bounds.left + bounds.width / 2;

        columnDragOverStatus.value = status;
        columnDragInsertPosition.value =
            event.clientX < midpoint ? 'before' : 'after';

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }

        return;
    }

    onColumnDragOver(event, status);
};

const onBoardSectionDrop = async (status) => {
    if (draggedColumnStatus.value !== null) {
        if (draggedColumnStatus.value === status) {
            resetColumnDragState();
            return;
        }

        const beforeStatus = getBeforeStatusForColumnDrop(
            status,
            columnDragInsertPosition.value,
        );

        await reorderBoardColumn(draggedColumnStatus.value, beforeStatus);
        return;
    }

    await onColumnDrop(status);
};

const onBoardLaneDragOver = (event) => {
    if (draggedColumnStatus.value === null) {
        return;
    }

    event.preventDefault();
    columnDragOverStatus.value = null;
    columnDragInsertPosition.value = 'after';

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const onBoardLaneDrop = async () => {
    if (draggedColumnStatus.value === null) {
        return;
    }

    await reorderBoardColumn(draggedColumnStatus.value, null);
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

const isDraggingTask = (taskId) => draggedTaskId.value === taskId;

const isColumnDropTarget = (status) =>
    draggedTaskId.value !== null &&
    dragOverStatus.value === status &&
    dragOverTaskId.value === null;

const isTaskDropTarget = (taskId, position) =>
    draggedTaskId.value !== null &&
    dragOverTaskId.value === taskId &&
    dragInsertPosition.value === position;

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

const buildGroupedTasks = (taskList) => {
    const grouped = {};

    boardStatuses.value.forEach((status) => {
        grouped[status] = [];
    });

    taskList.forEach((task) => {
        if (!grouped[task.status]) {
            grouped[task.status] = [];
        }

        grouped[task.status].push({ ...normalizeTask(task) });
    });

    Object.keys(grouped).forEach((status) => {
        grouped[status] = sortTaskList(grouped[status]);
    });

    return grouped;
};

const flattenGroupedTasks = (grouped) => {
    const orderedStatuses = [
        ...boardStatuses.value,
        ...Object.keys(grouped).filter(
            (status) => !boardStatuses.value.includes(status),
        ),
    ];

    return orderedStatuses.flatMap((status) => grouped[status] ?? []);
};

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

const resetDragState = () => {
    draggedTaskId.value = null;
    dragOverStatus.value = null;
    dragOverTaskId.value = null;
    dragInsertPosition.value = 'before';
};

const onTaskDragStart = (event, task) => {
    if (updatingId.value) {
        event.preventDefault();
        return;
    }

    draggedTaskId.value = task.id;
    dragOverStatus.value = task.status;
    dragOverTaskId.value = null;
    dragInsertPosition.value = 'before';

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(task.id));
    }
};

const onTaskDragEnd = () => {
    resetDragState();
};

const onColumnDragOver = (event, status) => {
    if (draggedTaskId.value === null) {
        return;
    }

    event.preventDefault();
    dragOverStatus.value = status;
    dragOverTaskId.value = null;
    dragInsertPosition.value = 'after';

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const getBeforeTaskIdForDrop = (status, targetTaskId, position) => {
    const destinationTasks = (tasksByStatus.value[status] ?? []).filter(
        (task) => task.id !== draggedTaskId.value,
    );
    const targetIndex = destinationTasks.findIndex(
        (task) => task.id === targetTaskId,
    );

    if (targetIndex === -1) {
        return null;
    }

    if (position === 'before') {
        return targetTaskId;
    }

    return destinationTasks[targetIndex + 1]?.id ?? null;
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
        resetDragState();
    }
};

const onTaskDragOver = (event, task) => {
    if (draggedTaskId.value === null || draggedTaskId.value === task.id) {
        return;
    }

    event.preventDefault();

    const bounds = event.currentTarget.getBoundingClientRect();
    const midpoint = bounds.top + bounds.height / 2;

    dragOverStatus.value = task.status;
    dragOverTaskId.value = task.id;
    dragInsertPosition.value = event.clientY < midpoint ? 'before' : 'after';

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const onTaskDrop = async (task) => {
    if (draggedTaskId.value === null || draggedTaskId.value === task.id) {
        return;
    }

    const draggedTask = tasks.value.find(
        (candidate) => candidate.id === draggedTaskId.value,
    );

    const beforeTaskId = getBeforeTaskIdForDrop(
        task.status,
        task.id,
        dragInsertPosition.value,
    );

    await reorderTask(draggedTask, task.status, beforeTaskId);
};

const onColumnDrop = async (status) => {
    if (draggedTaskId.value === null) {
        return;
    }

    const task = tasks.value.find(
        (candidate) => candidate.id === draggedTaskId.value,
    );

    await reorderTask(task, status, null);
};

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

const submitTaskComment = async () => {
    if (!activeTask.value || submittingComment.value) {
        return;
    }

    submittingComment.value = true;
    commentErrors.value = {};
    errorMessage.value = '';

    try {
        const response = await axios.post(
            route('tasks.comments.store', {
                task: activeTask.value.id,
            }),
            { content: commentDraft.value },
        );

        if (response?.data?.comment) {
            appendCommentToTask(activeTask.value.id, response.data.comment);
        }

        commentDraft.value = '';
    } catch (error) {
        if (error?.response?.status === 422) {
            commentErrors.value = error.response.data.errors ?? {};
            return;
        }

        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to add a comment right now. Please try again.';
    } finally {
        submittingComment.value = false;
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
                                <article
                                    v-for="task in tasksByStatus[status]"
                                    :key="task.id"
                                    class="task-card rounded-md border border-gray-200 bg-gray-50 p-4 shadow-sm transition"
                                    :class="{
                                        'task-card--dragging': isDraggingTask(task.id),
                                        'task-card--drop-before': isTaskDropTarget(task.id, 'before'),
                                        'task-card--drop-after': isTaskDropTarget(task.id, 'after'),
                                    }"
                                    role="button"
                                    tabindex="0"
                                    :draggable="updatingId !== task.id && movingColumnStatus === null"
                                    @click="openTaskDetails(task)"
                                    @keydown.enter.prevent="openTaskDetails(task)"
                                    @keydown.space.prevent="openTaskDetails(task)"
                                    @dragstart="onTaskDragStart($event, task)"
                                    @dragover.stop="onTaskDragOver($event, task)"
                                    @drop.stop.prevent="onTaskDrop(task)"
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
                                            class="rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                            :class="priorityBadgeClass(task.priority)"
                                        >
                                            {{ formatPriority(task.priority) }}
                                        </span>
                                    </div>

                                    <div class="mt-3">
                                        <div
                                            class="h-2 w-full rounded-full bg-gray-200"
                                        >
                                            <div
                                                class="h-2 rounded-full bg-gray-800"
                                                :style="{
                                                    width: `${task.progress}%`,
                                                }"
                                            />
                                        </div>
                                        <div class="mt-1 text-[10px] text-gray-500">
                                            {{ task.progress }}% complete
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
                                            activeTask.comments.length
                                        }}
                                        {{
                                            activeTask.comments.length === 1
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
                                    <article
                                        v-for="comment in activeTask.comments"
                                        :key="comment.id"
                                        class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3"
                                    >
                                        <div
                                            class="flex flex-wrap items-center justify-between gap-2"
                                        >
                                            <div class="text-sm font-semibold text-gray-800">
                                                {{ comment.user.name }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{
                                                    formatDateTime(comment.created_at) ||
                                                    'Just now'
                                                }}
                                            </div>
                                        </div>
                                        <p
                                            class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-gray-600"
                                        >
                                            {{ comment.content }}
                                        </p>
                                    </article>
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

.task-card--drop-before {
    box-shadow:
        inset 0 3px 0 rgb(31 41 55),
        0 1px 2px rgb(15 23 42 / 0.08);
}

.task-card--drop-after {
    box-shadow:
        inset 0 -3px 0 rgb(31 41 55),
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
