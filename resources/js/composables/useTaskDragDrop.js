import { ref, unref } from 'vue';

/**
 * Drag-and-drop state machine for task cards.
 *
 * @param {Object} options
 * @param {import('vue').Ref<Array>} options.tasks
 *   Reactive task list (read for finding the dragged task).
 * @param {import('vue').Ref<Object>} options.tasksByStatus
 *   Reactive grouped tasks (read for resolving drop targets).
 * @param {import('vue').Ref<boolean>} options.isBusy
 *   Reactive "currently saving" flag — drag is disabled while truthy.
 * @param {(task: Object, destinationStatus: string, beforeTaskId: number|null) => Promise<void>} options.onReorder
 *   Persistence callback. Receives the dragged task and its new position.
 */
export function useTaskDragDrop({ tasks, tasksByStatus, isBusy, onReorder }) {
    const draggedTaskId = ref(null);
    const dragOverStatus = ref(null);
    const dragOverTaskId = ref(null);
    const dragInsertPosition = ref('before');

    const reset = () => {
        draggedTaskId.value = null;
        dragOverStatus.value = null;
        dragOverTaskId.value = null;
        dragInsertPosition.value = 'before';
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

    const onTaskDragStart = (event, task) => {
        if (unref(isBusy)) {
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
        reset();
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

        await onReorder(draggedTask, task.status, beforeTaskId);
    };

    const onColumnDrop = async (status) => {
        if (draggedTaskId.value === null) {
            return;
        }

        const task = tasks.value.find(
            (candidate) => candidate.id === draggedTaskId.value,
        );

        await onReorder(task, status, null);
    };

    return {
        draggedTaskId,
        dragOverStatus,
        dragOverTaskId,
        dragInsertPosition,
        reset,
        isDraggingTask,
        isColumnDropTarget,
        isTaskDropTarget,
        onTaskDragStart,
        onTaskDragEnd,
        onColumnDragOver,
        onTaskDragOver,
        onTaskDrop,
        onColumnDrop,
    };
}
