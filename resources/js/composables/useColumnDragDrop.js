import { ref, unref } from 'vue';

/**
 * Drag-and-drop state machine for board columns (statuses).
 *
 * The column drag interleaves with the task drag (a single board section
 * receives both kinds of drag events). This composable exposes the column
 * state and atomic handlers; the parent component decides which drag is
 * active and routes events accordingly.
 *
 * @param {Object} options
 * @param {import('vue').Ref<boolean>} options.isBusy
 *   Reactive guard — drag is rejected while truthy.
 * @param {(status: string, beforeStatus: string|null) => string|null} options.computeBeforeStatus
 *   Resolve the destination "before" anchor when dropping near a target.
 * @param {(status: string, beforeStatus: string|null) => Promise<void>} options.onReorder
 *   Persistence callback. Receives the moved status and its destination anchor.
 */
export function useColumnDragDrop({ isBusy, computeBeforeStatus, onReorder }) {
    const draggedColumnStatus = ref(null);
    const columnDragOverStatus = ref(null);
    const columnDragInsertPosition = ref('before');

    const reset = () => {
        draggedColumnStatus.value = null;
        columnDragOverStatus.value = null;
        columnDragInsertPosition.value = 'before';
    };

    const isDraggingColumn = (status) => draggedColumnStatus.value === status;

    const isColumnReorderDropTarget = (status, position) =>
        draggedColumnStatus.value !== null &&
        columnDragOverStatus.value === status &&
        columnDragInsertPosition.value === position;

    const onColumnDragStart = (event, status) => {
        if (unref(isBusy)) {
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

    const onColumnDragEnd = () => {
        reset();
    };

    /**
     * Track hover position when dragging a column over another column.
     */
    const onColumnDragOver = (event, status) => {
        if (draggedColumnStatus.value === null) {
            return false;
        }

        event.preventDefault();

        if (draggedColumnStatus.value === status) {
            columnDragOverStatus.value = status;
            columnDragInsertPosition.value = 'before';
            return true;
        }

        const bounds = event.currentTarget.getBoundingClientRect();
        const midpoint = bounds.left + bounds.width / 2;

        columnDragOverStatus.value = status;
        columnDragInsertPosition.value =
            event.clientX < midpoint ? 'before' : 'after';

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }

        return true;
    };

    /**
     * @returns {Promise<boolean>} true if the drop was handled as a column
     *   reorder, false if the caller should fall through to task drop.
     */
    const onColumnDrop = async (status) => {
        if (draggedColumnStatus.value === null) {
            return false;
        }

        if (draggedColumnStatus.value === status) {
            reset();
            return true;
        }

        const beforeStatus = computeBeforeStatus(
            status,
            columnDragInsertPosition.value,
        );

        await onReorder(draggedColumnStatus.value, beforeStatus);
        return true;
    };

    /**
     * The lane is the empty area to the right of the last column — dropping
     * here parks the column at the end.
     */
    const onLaneDragOver = (event) => {
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

    const onLaneDrop = async () => {
        if (draggedColumnStatus.value === null) {
            return;
        }

        await onReorder(draggedColumnStatus.value, null);
    };

    return {
        draggedColumnStatus,
        columnDragOverStatus,
        columnDragInsertPosition,
        reset,
        isDraggingColumn,
        isColumnReorderDropTarget,
        onColumnDragStart,
        onColumnDragEnd,
        onColumnDragOver,
        onColumnDrop,
        onLaneDragOver,
        onLaneDrop,
    };
}
