<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AddColumnModal from '@/Components/AddColumnModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BoardColumn from '@/Components/BoardColumn.vue';
import BoardEmptyState from '@/Components/BoardEmptyState.vue';
import BoardFilters from '@/Components/BoardFilters.vue';
import BoardHeader from '@/Components/BoardHeader.vue';
import BoardMembersModal from '@/Components/BoardMembersModal.vue';
import DeleteColumnModal from '@/Components/DeleteColumnModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TaskDetailsModal from '@/Components/TaskDetailsModal.vue';
import TaskCard from '@/Components/TaskCard.vue';
import TaskFormModal from '@/Components/TaskFormModal.vue';
import UndoToast from '@/Components/UndoToast.vue';
import {
    defaultStatusLabels,
    formatDateInput,
    formatStatus as formatStatusValue,
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
import {
    defaultBoardFilterPreferences,
    normalizeBoardFilterPreferences,
    useBoardFilter,
} from '@/composables/useBoardFilter';
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
    archivedTasks: {
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
    members: {
        type: Array,
        default: () => [],
    },
    filterPreferences: {
        type: Object,
        default: () => ({}),
    },
});

const initialFilterPreferences = normalizeBoardFilterPreferences(
    props.filterPreferences,
);
const tasks = ref(props.tasks.map(normalizeTask));
const archivedTasks = ref(props.archivedTasks.map(normalizeTask));
const taskMembers = ref([...props.members]);
const page = usePage();
const updatingId = ref(null);
const movingColumnStatus = ref(null);
const editingStatusLabel = ref(null);
const statusLabelDraft = ref('');
const savingStatusLabel = ref(null);
const statusLabelInput = ref(null);
const showingColumnModal = ref(false);
const showingCreateModal = ref(false);
const showingDetailsModal = ref(false);
const showingEditModal = ref(false);
const showingArchived = ref(initialFilterPreferences.view === 'archived');
const savedFilterPreferences = ref(initialFilterPreferences);
const savingFilterPreferences = ref(false);
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
const checklistDraft = ref('');
const checklistErrors = ref({});
const addingChecklistItem = ref(false);
const updatingChecklistItemId = ref(null);
const deletingChecklistItemId = ref(null);
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
    assignee_ids: [],
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
const isBoardOwner = computed(() => props.currentBoard?.is_owner === true);

// Members management state
const showingMembersModal = ref(false);
const boardMembers = ref([]);
const availableBoardAgents = ref([]);
const loadingMembers = ref(false);
const invitingMember = ref(false);
const addingAgentId = ref(null);
const removingMemberId = ref(null);
const memberInviteError = ref('');

const openMembersModal = async () => {
    if (!currentBoardId.value) {
        return;
    }

    showingMembersModal.value = true;
    memberInviteError.value = '';
    loadingMembers.value = true;

    try {
        const response = await axios.get(
            route('boards.members.index', { board: currentBoardId.value }),
        );
        boardMembers.value = response?.data?.members ?? [];
        taskMembers.value = boardMembers.value;
        availableBoardAgents.value = response?.data?.available_agents ?? [];
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to load members. Please try again.';
        showingMembersModal.value = false;
    } finally {
        loadingMembers.value = false;
    }
};

const closeMembersModal = () => {
    showingMembersModal.value = false;
    memberInviteError.value = '';
};

const applyMemberPayload = (response) => {
    const members = response?.data?.members;

    if (members) {
        boardMembers.value = members;
        taskMembers.value = members;
    }

    availableBoardAgents.value =
        response?.data?.available_agents ?? availableBoardAgents.value;
};

const memberRequestError = (error, fallback) =>
    error?.response?.data?.errors?.email?.[0] ||
    error?.response?.data?.data?.errors?.email?.[0] ||
    error?.response?.data?.message ||
    fallback;

const inviteMember = async (email) => {
    if (!currentBoardId.value || !email) {
        return;
    }

    invitingMember.value = true;
    memberInviteError.value = '';

    try {
        const response = await axios.post(
            route('boards.members.store', { board: currentBoardId.value }),
            { email },
        );
        applyMemberPayload(response);
    } catch (error) {
        if (error?.response?.status === 422) {
            memberInviteError.value = memberRequestError(
                error,
                'Unable to invite that user.',
            );
        } else {
            memberInviteError.value =
                error?.response?.data?.message ||
                'Unable to invite that user. Please try again.';
        }
    } finally {
        invitingMember.value = false;
    }
};

const addAgentMember = async (agent) => {
    if (!currentBoardId.value || !agent?.email || addingAgentId.value !== null) {
        return;
    }

    addingAgentId.value = agent.id;
    memberInviteError.value = '';

    try {
        const response = await axios.post(
            route('boards.members.store', { board: currentBoardId.value }),
            { email: agent.email },
        );
        applyMemberPayload(response);
    } catch (error) {
        memberInviteError.value = memberRequestError(
            error,
            'Unable to add that agent. Please try again.',
        );
    } finally {
        addingAgentId.value = null;
    }
};

const removeMember = async (userId) => {
    if (!currentBoardId.value || !userId) {
        return;
    }

    removingMemberId.value = userId;

    try {
        const response = await axios.delete(
            route('boards.members.destroy', {
                board: currentBoardId.value,
                user: userId,
            }),
        );
        applyMemberPayload(response);
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to remove that member. Please try again.';
    } finally {
        removingMemberId.value = null;
    }
};
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
    () => props.archivedTasks,
    (nextTasks) => {
        archivedTasks.value = nextTasks.map(normalizeTask);
    },
);

watch(
    () => props.members,
    (nextMembers) => {
        taskMembers.value = [...nextMembers];
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
    [...tasks.value, ...archivedTasks.value].find(
        (task) => task.id === selectedTaskId.value,
    ) ?? null,
);
const editingTask = computed(() =>
    [...tasks.value, ...archivedTasks.value].find(
        (task) => task.id === editingTaskId.value,
    ) ?? null,
);
const checklistTask = computed(() => activeTask.value ?? editingTask.value);
const lastOpenedQueryTaskId = ref(null);

const taskIdFromPageUrl = (url) => {
    const parsed = new URL(url, 'http://task-management.local');
    const id = Number.parseInt(parsed.searchParams.get('task') ?? '', 10);

    return Number.isNaN(id) ? null : id;
};

const openTaskFromQuery = (url) => {
    const taskId = taskIdFromPageUrl(url);

    if (!taskId) {
        lastOpenedQueryTaskId.value = null;
        return;
    }

    if (lastOpenedQueryTaskId.value === taskId) {
        return;
    }

    const task = [...tasks.value, ...archivedTasks.value].find(
        (candidate) => candidate.id === taskId,
    );

    if (!task) {
        return;
    }

    lastOpenedQueryTaskId.value = taskId;
    showingArchived.value = Boolean(task.archived_at);
    openTaskDetails(task);
};

const clearTaskQueryFromUrl = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);

    if (!url.searchParams.has('task')) {
        return;
    }

    url.searchParams.delete('task');
    window.history.replaceState(
        window.history.state,
        '',
        `${url.pathname}${url.search}${url.hash}`,
    );
    lastOpenedQueryTaskId.value = null;
};

const currentBrowserUrl = () =>
    typeof window === 'undefined'
        ? page.url
        : `${window.location.pathname}${window.location.search}${window.location.hash}`;

const currentTaskList = computed(() =>
    showingArchived.value ? archivedTasks.value : tasks.value,
);

const setTaskFormValues = (taskForm, values = {}) => {
    taskForm.title = values.title ?? '';
    taskForm.description = values.description ?? '';
    taskForm.status = values.status ?? defaultStatus;
    taskForm.priority = values.priority ?? defaultPriority;
    taskForm.tags = Array.isArray(values.tags) ? [...values.tags] : [];
    taskForm.progress = values.progress ?? 0;
    taskForm.deadline_at = values.deadline_at ?? '';
    taskForm.assignee_ids = Array.isArray(values.assignee_ids)
        ? [...values.assignee_ids]
        : [];
};

const resolveFieldError = (errors = {}, field) =>
    errors[field] ??
    Object.entries(errors).find(([key]) => key.startsWith(`${field}.`))?.[1] ??
    '';

const resetChecklistForm = () => {
    checklistDraft.value = '';
    checklistErrors.value = {};
    addingChecklistItem.value = false;
    updatingChecklistItemId.value = null;
    deletingChecklistItemId.value = null;
};

const resetCommentForm = () => {
    commentDraft.value = '';
    commentErrors.value = {};
    submittingComment.value = false;
    activeReplyCommentId.value = null;
    replyDraft.value = '';
    replyErrors.value = {};
    replyingCommentId.value = null;
    resetChecklistForm();
};

const closeCreateModal = () => {
    showingCreateModal.value = false;
    setTaskFormValues(form, blankTaskData());
    form.clearErrors();
};

const openCreateModal = (status = null) => {
    setTaskFormValues(form, blankTaskData());
    if (status && boardStatuses.value.includes(status)) {
        form.status = status;
    }
    form.clearErrors();
    showingCreateModal.value = true;
};

const closeColumnModal = () => {
    showingColumnModal.value = false;
    columnForm.reset();
    columnForm.clearErrors();
};

const setStatusLabelInput = (element) => {
    statusLabelInput.value = element;
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

// ---------------------------------------------------------------------------
// Column deletion
//
// Empty columns: optimistic remove + 5s undo toast (toast expiry fires the
// actual server delete; the user can undo until then).
// Non-empty columns: open a modal that asks where to move the tasks, then
// optimistic remove + server delete with the chosen destination.
// ---------------------------------------------------------------------------

const pendingColumnDeletion = ref(null);
let pendingColumnDeletionTimer = null;
const pendingTaskDeletion = ref(null);
let pendingTaskDeletionTimer = null;
const showingDeleteColumnModal = ref(false);
const deleteColumnContext = ref(null);
const deleteColumnError = ref('');
const deleteColumnProcessing = ref(false);

const canDeleteColumn = computed(() => boardStatuses.value.length > 1);

const destinationOptionsForDelete = computed(() => {
    if (!deleteColumnContext.value) {
        return [];
    }

    return boardStatuses.value
        .filter((status) => status !== deleteColumnContext.value.status)
        .map((status) => ({
            status,
            label: formatStatus(status),
        }));
});

const snapshotColumnState = (status) => ({
    statuses: [...boardStatuses.value],
    statusLabels: { ...boardStatusLabels.value },
    deletedStatus: status,
});

const removeStatusLocally = (status) => {
    boardStatuses.value = boardStatuses.value.filter(
        (candidate) => candidate !== status,
    );

    const nextLabels = { ...boardStatusLabels.value };
    delete nextLabels[status];
    boardStatusLabels.value = nextLabels;
};

const clearPendingDeletionTimer = () => {
    if (pendingColumnDeletionTimer !== null) {
        window.clearTimeout(pendingColumnDeletionTimer);
        pendingColumnDeletionTimer = null;
    }
};

const undoPendingColumnDeletion = () => {
    if (!pendingColumnDeletion.value) {
        return;
    }

    const pending = pendingColumnDeletion.value;
    pendingColumnDeletion.value = null;
    clearPendingDeletionTimer();

    boardStatuses.value = pending.snapshot.statuses;
    boardStatusLabels.value = pending.snapshot.statusLabels;
};

const flushPendingColumnDeletion = async () => {
    if (!pendingColumnDeletion.value) {
        return;
    }

    const pending = pendingColumnDeletion.value;
    pendingColumnDeletion.value = null;
    clearPendingDeletionTimer();

    try {
        const response = await axios.delete(
            route('tasks.columns.destroy', {
                board: currentBoardId.value,
                status: pending.snapshot.deletedStatus,
            }),
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
        boardStatuses.value = pending.snapshot.statuses;
        boardStatusLabels.value = pending.snapshot.statusLabels;
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to delete column. Please try again.';
    }
};

const requestDeleteColumn = (status) => {
    if (!canDeleteColumn.value) {
        return;
    }

    // Commit any in-flight pending delete first so state stays predictable.
    if (pendingColumnDeletion.value) {
        flushPendingColumnDeletion();
    }
    if (pendingTaskDeletion.value) {
        flushPendingTaskDeletion();
    }

    errorMessage.value = '';
    const taskCount = (tasksByStatus.value[status] ?? []).length;

    if (taskCount > 0) {
        deleteColumnContext.value = {
            status,
            label: formatStatus(status),
            taskCount,
        };
        deleteColumnError.value = '';
        deleteColumnProcessing.value = false;
        showingDeleteColumnModal.value = true;
        return;
    }

    pendingColumnDeletion.value = {
        label: formatStatus(status),
        snapshot: snapshotColumnState(status),
    };
    removeStatusLocally(status);

    pendingColumnDeletionTimer = window.setTimeout(() => {
        flushPendingColumnDeletion();
    }, 5100);
};

const cancelDeleteColumnModal = () => {
    showingDeleteColumnModal.value = false;
    deleteColumnContext.value = null;
    deleteColumnError.value = '';
};

const confirmDeleteColumnWithMove = async (moveTasksTo) => {
    if (!deleteColumnContext.value || !moveTasksTo) {
        return;
    }

    const ctx = deleteColumnContext.value;
    const snapshot = snapshotColumnState(ctx.status);

    deleteColumnProcessing.value = true;
    deleteColumnError.value = '';

    try {
        const response = await axios.delete(
            route('tasks.columns.destroy', {
                board: currentBoardId.value,
                status: ctx.status,
            }),
            { data: { move_tasks_to: moveTasksTo } },
        );

        if (Array.isArray(response?.data?.statuses)) {
            boardStatuses.value = response.data.statuses;
        }

        if (response?.data?.status_labels) {
            boardStatusLabels.value = buildStatusLabels(
                response.data.status_labels,
            );
        }

        // Tasks have new statuses on the server; refresh from Inertia.
        router.reload({
            only: ['tasks'],
            preserveScroll: true,
            preserveState: true,
        });

        showingDeleteColumnModal.value = false;
        deleteColumnContext.value = null;
    } catch (error) {
        boardStatuses.value = snapshot.statuses;
        boardStatusLabels.value = snapshot.statusLabels;
        deleteColumnError.value =
            error?.response?.data?.message ||
            'Unable to delete column. Please try again.';
    } finally {
        deleteColumnProcessing.value = false;
    }
};

// ---------------------------------------------------------------------------
// Task deletion (mirrors the column flow: optimistic remove + 5s undo toast).
// ---------------------------------------------------------------------------

const clearPendingTaskTimer = () => {
    if (pendingTaskDeletionTimer !== null) {
        window.clearTimeout(pendingTaskDeletionTimer);
        pendingTaskDeletionTimer = null;
    }
};

const undoPendingTaskDeletion = () => {
    if (!pendingTaskDeletion.value) {
        return;
    }

    const pending = pendingTaskDeletion.value;
    pendingTaskDeletion.value = null;
    clearPendingTaskTimer();

    tasks.value = pending.activeSnapshot;
    archivedTasks.value = pending.archivedSnapshot;
};

const flushPendingTaskDeletion = async () => {
    if (!pendingTaskDeletion.value) {
        return;
    }

    const pending = pendingTaskDeletion.value;
    pendingTaskDeletion.value = null;
    clearPendingTaskTimer();

    try {
        await axios.delete(
            route('tasks.destroy', {
                board: currentBoardId.value,
                task: pending.taskId,
            }),
        );
    } catch (error) {
        tasks.value = pending.activeSnapshot;
        archivedTasks.value = pending.archivedSnapshot;
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to delete task. Please try again.';
    }
};

const requestDeleteTask = (task) => {
    if (!task) {
        return;
    }

    if (pendingColumnDeletion.value) {
        flushPendingColumnDeletion();
    }
    if (pendingTaskDeletion.value) {
        flushPendingTaskDeletion();
    }

    errorMessage.value = '';

    if (showingDetailsModal.value && selectedTaskId.value === task.id) {
        closeTaskDetails();
    }

    pendingTaskDeletion.value = {
        taskId: task.id,
        title: task.title,
        activeSnapshot: cloneTasks(tasks.value),
        archivedSnapshot: cloneTasks(archivedTasks.value),
    };

    tasks.value = tasks.value.filter((t) => t.id !== task.id);
    archivedTasks.value = archivedTasks.value.filter((t) => t.id !== task.id);

    pendingTaskDeletionTimer = window.setTimeout(() => {
        flushPendingTaskDeletion();
    }, 5100);
};

const archiveTaskLocally = (task, archivedAt) => {
    tasks.value = tasks.value.filter((candidate) => candidate.id !== task.id);
    archivedTasks.value = [
        normalizeTask({
            ...task,
            archived_at: archivedAt,
        }),
        ...archivedTasks.value.filter((candidate) => candidate.id !== task.id),
    ];
};

const restoreTaskLocally = (task) => {
    archivedTasks.value = archivedTasks.value.filter(
        (candidate) => candidate.id !== task.id,
    );
    tasks.value = [
        ...tasks.value.filter((candidate) => candidate.id !== task.id),
        normalizeTask({
            ...task,
            archived_at: null,
        }),
    ];
};

const requestArchiveTask = async (task) => {
    if (!task || updatingId.value) {
        return;
    }

    errorMessage.value = '';
    updatingId.value = task.id;
    const previousTasks = cloneTasks(tasks.value);
    const previousArchivedTasks = cloneTasks(archivedTasks.value);

    try {
        const response = await axios.patch(
            route('tasks.archive', {
                board: currentBoardId.value,
                task: task.id,
            }),
        );

        archiveTaskLocally(
            task,
            response?.data?.archived_at ?? new Date().toISOString(),
        );
    } catch (error) {
        tasks.value = previousTasks;
        archivedTasks.value = previousArchivedTasks;
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to archive task. Please try again.';
    } finally {
        updatingId.value = null;
    }
};

const requestRestoreTask = async (task) => {
    if (!task || updatingId.value) {
        return;
    }

    errorMessage.value = '';
    updatingId.value = task.id;
    const previousTasks = cloneTasks(tasks.value);
    const previousArchivedTasks = cloneTasks(archivedTasks.value);

    try {
        await axios.patch(
            route('tasks.restore', {
                board: currentBoardId.value,
                task: task.id,
            }),
        );

        restoreTaskLocally(task);
    } catch (error) {
        tasks.value = previousTasks;
        archivedTasks.value = previousArchivedTasks;
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to restore task. Please try again.';
    } finally {
        updatingId.value = null;
    }
};

const requestDuplicateTask = (task) => {
    if (!task || updatingId.value) {
        return;
    }

    errorMessage.value = '';
    updatingId.value = task.id;
    closeTaskDetails();

    router.post(
        route('tasks.duplicate', {
            board: currentBoardId.value,
            task: task.id,
        }),
        {},
        {
            preserveScroll: true,
            onError: () => {
                errorMessage.value = 'Unable to duplicate task. Please try again.';
            },
            onFinish: () => {
                updatingId.value = null;
            },
        },
    );
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
    clearTaskQueryFromUrl();
};

watch(
    () => page.url,
    (url) => openTaskFromQuery(url),
    { immediate: true },
);

watch([tasks, archivedTasks], () => openTaskFromQuery(currentBrowserUrl()));

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
        assignee_ids: Array.isArray(task.assignees)
            ? task.assignees.map((assignee) => assignee.id)
            : [],
    });
    editForm.clearErrors();
    resetChecklistForm();
    showingEditModal.value = true;
};

const closeEditModal = () => {
    showingEditModal.value = false;
    editingTaskId.value = null;
    setTaskFormValues(editForm, blankTaskData());
    editForm.clearErrors();
    resetChecklistForm();
};

const openEditFromDetails = () => {
    if (!activeTask.value || activeTask.value.archived_at) {
        return;
    }

    const task = activeTask.value;
    closeTaskDetails();
    openEditModal(task);
};

const {
    searchQuery,
    priorityFilter,
    assigneeFilter,
    deadlineFilter,
    currentFilters,
    filteredTasks,
    hasActiveFilters,
    togglePriority,
    clearFilters,
    setFilters,
} = useBoardFilter(currentTaskList, initialFilterPreferences);

const currentFilterPreferences = computed(() =>
    normalizeBoardFilterPreferences({
        ...currentFilters.value,
        view: showingArchived.value ? 'archived' : 'active',
    }),
);

const hasSavedFilterChanges = computed(
    () =>
        JSON.stringify(currentFilterPreferences.value) !==
        JSON.stringify(savedFilterPreferences.value),
);

watch(
    () => props.filterPreferences,
    (nextPreferences) => {
        const normalized = normalizeBoardFilterPreferences(nextPreferences);
        savedFilterPreferences.value = normalized;
        setFilters(normalized);
        showingArchived.value = normalized.view === 'archived';
    },
    { deep: true },
);

const saveFilterPreferences = async (
    preferences = currentFilterPreferences.value,
) => {
    if (!currentBoardId.value || savingFilterPreferences.value) {
        return;
    }

    const payload = normalizeBoardFilterPreferences(preferences);
    savingFilterPreferences.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.patch(
            route('boards.filters.update', { board: currentBoardId.value }),
            payload,
        );

        savedFilterPreferences.value = normalizeBoardFilterPreferences(
            response?.data?.filters ?? payload,
        );
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to save board filters. Please try again.';
    } finally {
        savingFilterPreferences.value = false;
    }
};

const resetSavedFilterPreferences = async () => {
    const defaults = defaultBoardFilterPreferences();
    setFilters(defaults);
    showingArchived.value = false;

    await saveFilterPreferences(defaults);
};

const tasksByStatus = computed(() => {
    const grouped = {};
    boardStatuses.value.forEach((status) => {
        grouped[status] = [];
    });

    if (showingArchived.value) {
        return grouped;
    }

    filteredTasks.value.forEach((task) => {
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

    const appendToList = (taskList) => taskList.map((task) => {
        if (task.id !== taskId) {
            return task;
        }

        return normalizeTask({
            ...task,
            comments: [...(task.comments ?? []), normalizedComment],
        });
    });

    tasks.value = appendToList(tasks.value);
    archivedTasks.value = appendToList(archivedTasks.value);
};

const appendReplyToTask = (taskId, parentId, comment) => {
    const normalizedReply = normalizeComment(comment);

    const appendToList = (taskList) => taskList.map((task) => {
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

    tasks.value = appendToList(tasks.value);
    archivedTasks.value = appendToList(archivedTasks.value);
};

const updateTaskInLists = (taskId, callback) => {
    const updateList = (taskList) => taskList.map((task) => {
        if (task.id !== taskId) {
            return task;
        }

        return normalizeTask(callback(task));
    });

    tasks.value = updateList(tasks.value);
    archivedTasks.value = updateList(archivedTasks.value);
};

const appendChecklistItemToTask = (taskId, item) => {
    updateTaskInLists(taskId, (task) => ({
        ...task,
        checklist_items: [...(task.checklist_items ?? []), item],
    }));
};

const replaceChecklistItemOnTask = (taskId, item) => {
    updateTaskInLists(taskId, (task) => ({
        ...task,
        checklist_items: (task.checklist_items ?? []).map((existing) =>
            existing.id === item.id ? item : existing,
        ),
    }));
};

const removeChecklistItemFromTask = (taskId, itemId) => {
    updateTaskInLists(taskId, (task) => ({
        ...task,
        checklist_items: (task.checklist_items ?? []).filter(
            (item) => item.id !== itemId,
        ),
    }));
};

const submitChecklistItem = async () => {
    const targetTask = checklistTask.value;

    if (!targetTask || addingChecklistItem.value) {
        return;
    }

    const title = checklistDraft.value.trim();

    if (!title) {
        return;
    }

    addingChecklistItem.value = true;
    checklistErrors.value = {};
    errorMessage.value = '';

    try {
        const response = await axios.post(
            route('tasks.checklist-items.store', {
                board: currentBoardId.value,
                task: targetTask.id,
            }),
            { title },
        );

        if (response?.data?.checklist_item) {
            appendChecklistItemToTask(
                targetTask.id,
                response.data.checklist_item,
            );
        }

        checklistDraft.value = '';
    } catch (error) {
        if (error?.response?.status === 422) {
            checklistErrors.value = error.response.data.errors ?? {};
            return;
        }

        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to add checklist item right now. Please try again.';
    } finally {
        addingChecklistItem.value = false;
    }
};

const updateChecklistItem = async (item, payload) => {
    const targetTask = checklistTask.value;

    if (!targetTask || updatingChecklistItemId.value === item.id) {
        return;
    }

    updatingChecklistItemId.value = item.id;
    checklistErrors.value = {};
    errorMessage.value = '';

    try {
        const response = await axios.patch(
            route('tasks.checklist-items.update', {
                board: currentBoardId.value,
                task: targetTask.id,
                checklistItem: item.id,
            }),
            payload,
        );

        if (response?.data?.checklist_item) {
            replaceChecklistItemOnTask(
                targetTask.id,
                response.data.checklist_item,
            );
        }
    } catch (error) {
        if (error?.response?.status === 422) {
            checklistErrors.value = error.response.data.errors ?? {};
            return;
        }

        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to update checklist item right now. Please try again.';
    } finally {
        updatingChecklistItemId.value = null;
    }
};

const toggleChecklistItem = ({ item, completed }) => {
    updateChecklistItem(item, { completed });
};

const renameChecklistItem = ({ item, title }) => {
    updateChecklistItem(item, { title });
};

const deleteChecklistItem = async (item) => {
    const targetTask = checklistTask.value;

    if (!targetTask || deletingChecklistItemId.value === item.id) {
        return;
    }

    deletingChecklistItemId.value = item.id;
    errorMessage.value = '';

    try {
        const response = await axios.delete(
            route('tasks.checklist-items.destroy', {
                board: currentBoardId.value,
                task: targetTask.id,
                checklistItem: item.id,
            }),
        );

        removeChecklistItemFromTask(
            targetTask.id,
            Number(response?.data?.id ?? item.id),
        );
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to delete checklist item right now. Please try again.';
    } finally {
        deletingChecklistItemId.value = null;
    }
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
            <BoardHeader
                :board-id="currentBoardId"
                :name="currentBoardName"
                :description="currentBoardDescription"
                @update:name="currentBoardName = $event"
                @update:description="currentBoardDescription = $event"
                @error="errorMessage = $event"
            >
                <template #actions>
                    <span
                        v-if="tasks.length || archivedTasks.length"
                        class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500 shadow-sm"
                    >
                        {{ tasks.length }} active / {{ archivedTasks.length }} archived
                    </span>
                    <SecondaryButton @click="openMembersModal">
                        Members
                    </SecondaryButton>
                    <SecondaryButton @click="showingColumnModal = true">
                        Add Column
                    </SecondaryButton>
                    <PrimaryButton @click="openCreateModal()">
                        New Task
                    </PrimaryButton>
                </template>
            </BoardHeader>
        </template>

        <div class="min-h-[calc(100vh-9rem)] pt-5">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <template v-if="boardStatuses.length">
                    <BoardFilters
                        v-if="tasks.length || archivedTasks.length"
                        v-model:search-query="searchQuery"
                        v-model:assignee-filter="assigneeFilter"
                        v-model:deadline-filter="deadlineFilter"
                        :priorities="priorityOptions"
                        :active-priorities="priorityFilter"
                        :members="taskMembers"
                        :current-user-id="$page.props.auth.user?.id ?? null"
                        :has-active-filters="hasActiveFilters"
                        :matched-count="filteredTasks.length"
                        :total-count="currentTaskList.length"
                        :can-save-filters="currentBoardId !== null"
                        :has-saved-filter-changes="hasSavedFilterChanges"
                        :saving-filters="savingFilterPreferences"
                        class="mb-3"
                        @toggle-priority="togglePriority"
                        @clear="clearFilters"
                        @save-filters="saveFilterPreferences"
                        @reset-saved-filters="resetSavedFilterPreferences"
                    >
                        <template #leading>
                            <div class="inline-flex shrink-0 rounded-md border border-gray-200 bg-gray-50 p-0.5">
                                <button
                                    type="button"
                                    class="rounded px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wide transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    :class="
                                        !showingArchived
                                            ? 'bg-gray-800 text-white shadow-sm'
                                            : 'text-gray-500 hover:bg-white hover:text-gray-700'
                                    "
                                    :aria-pressed="!showingArchived"
                                    @click="showingArchived = false"
                                >
                                    Active {{ tasks.length }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wide transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    :class="
                                        showingArchived
                                            ? 'bg-gray-800 text-white shadow-sm'
                                            : 'text-gray-500 hover:bg-white hover:text-gray-700'
                                    "
                                    :aria-pressed="showingArchived"
                                    @click="showingArchived = true"
                                >
                                    Archived {{ archivedTasks.length }}
                                </button>
                            </div>
                        </template>
                    </BoardFilters>

                    <template v-if="showingArchived">
                        <div
                            v-if="filteredTasks.length"
                            class="grid gap-4 pb-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                        >
                            <TaskCard
                                v-for="task in filteredTasks"
                                :key="`archived-${task.id}`"
                                :task="task"
                                :can-drag="false"
                                :can-edit="false"
                                :is-updating="updatingId === task.id"
                                @open-details="openTaskDetails"
                            />
                        </div>
                        <div
                            v-else
                            class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500"
                        >
                            No archived tasks.
                        </div>
                    </template>

                    <template v-else>
                        <div class="board-scroll h-full snap-x snap-mandatory overflow-x-auto overflow-y-hidden pb-3 sm:snap-none">
                            <div
                                class="flex min-h-[calc(100vh-13rem)] w-max min-w-full items-stretch justify-start gap-4 sm:justify-center sm:gap-6"
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
                                    :can-delete="canDeleteColumn"
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
                                    @request-delete="requestDeleteColumn(status)"
                                    @add-task="openCreateModal(status)"
                                    @task-drag-start="onTaskDragStart"
                                    @task-drag-over="onTaskDragOver"
                                    @task-drag-end="onTaskDragEnd"
                                    @task-drop="onTaskDrop"
                                    @task-open-details="openTaskDetails"
                                    @task-open-edit="openEditModal"
                                />
                            </div>
                        </div>
                    </template>
                </template>
                <BoardEmptyState
                    v-else
                    :board-name="currentBoardName"
                    @create-task="openCreateModal()"
                    @add-column="showingColumnModal = true"
                />

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

                <DeleteColumnModal
                    :show="showingDeleteColumnModal"
                    :column-label="deleteColumnContext?.label ?? ''"
                    :task-count="deleteColumnContext?.taskCount ?? 0"
                    :destinations="destinationOptionsForDelete"
                    :processing="deleteColumnProcessing"
                    :error="deleteColumnError"
                    @close="cancelDeleteColumnModal"
                    @confirm="confirmDeleteColumnWithMove"
                />

                <BoardMembersModal
                    :show="showingMembersModal"
                    :members="boardMembers"
                    :available-agents="availableBoardAgents"
                    :is-owner="isBoardOwner"
                    :loading="loadingMembers"
                    :inviting="invitingMember"
                    :adding-agent-id="addingAgentId"
                    :removing-user-id="removingMemberId"
                    :invite-error="memberInviteError"
                    @close="closeMembersModal"
                    @invite="inviteMember"
                    @add-agent="addAgentMember"
                    @remove="removeMember"
                />

                <UndoToast
                    :show="pendingColumnDeletion !== null"
                    :message="`Column “${pendingColumnDeletion?.label ?? ''}” deleted`"
                    :duration-ms="5000"
                    @undo="undoPendingColumnDeletion"
                    @expire="flushPendingColumnDeletion"
                />

                <UndoToast
                    :show="pendingTaskDeletion !== null"
                    :message="`Task “${pendingTaskDeletion?.title ?? ''}” deleted`"
                    :duration-ms="5000"
                    @undo="undoPendingTaskDeletion"
                    @expire="flushPendingTaskDeletion"
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
                    :updating-checklist-item-id="updatingChecklistItemId"
                    @close="closeTaskDetails"
                    @open-edit="openEditFromDetails"
                    @request-archive="requestArchiveTask(activeTask)"
                    @request-restore="requestRestoreTask(activeTask)"
                    @request-delete="requestDeleteTask(activeTask)"
                    @request-duplicate="requestDuplicateTask(activeTask)"
                    @submit-comment="submitTaskComment"
                    @start-reply="startReply"
                    @cancel-reply="cancelReply"
                    @submit-reply="submitReply"
                    @toggle-checklist-item="toggleChecklistItem"
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
                    :members="taskMembers"
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
                    :members="taskMembers"
                    v-model:checklist-draft="checklistDraft"
                    :checklist-items="editingTask?.checklist_items ?? []"
                    :checklist-errors="checklistErrors"
                    :adding-checklist-item="addingChecklistItem"
                    :updating-checklist-item-id="updatingChecklistItemId"
                    :deleting-checklist-item-id="deletingChecklistItemId"
                    @close="closeEditModal"
                    @submit="submitTaskUpdate"
                    @add-checklist-item="submitChecklistItem"
                    @rename-checklist-item="renameChecklistItem"
                    @delete-checklist-item="deleteChecklistItem"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.board-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
    scrollbar-gutter: stable;
}

.board-scroll::-webkit-scrollbar {
    height: 10px;
}

.board-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.board-scroll::-webkit-scrollbar-thumb {
    background-color: rgb(203 213 225);
    border-radius: 9999px;
    border: 2px solid transparent;
    background-clip: padding-box;
    min-width: 40px;
}

.board-scroll::-webkit-scrollbar-thumb:hover {
    background-color: rgb(148 163 184);
}

.board-scroll::-webkit-scrollbar-button {
    display: none;
}
</style>
