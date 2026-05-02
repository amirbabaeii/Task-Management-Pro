<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import TaskCard from '@/Components/TaskCard.vue';

defineProps({
    status: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    tasks: {
        type: Array,
        default: () => [],
    },
    isEditingLabel: {
        type: Boolean,
        default: false,
    },
    isDraggingColumn: {
        type: Boolean,
        default: false,
    },
    isMoving: {
        type: Boolean,
        default: false,
    },
    isTaskDropTarget: {
        type: Boolean,
        default: false,
    },
    isReorderDropBefore: {
        type: Boolean,
        default: false,
    },
    isReorderDropAfter: {
        type: Boolean,
        default: false,
    },
    columnsBusy: {
        type: Boolean,
        default: false,
    },
    canDelete: {
        type: Boolean,
        default: true,
    },
    updatingTaskId: {
        type: Number,
        default: null,
    },
    isTaskDragging: {
        type: Function,
        required: true,
    },
    isTaskDropBefore: {
        type: Function,
        required: true,
    },
    isTaskDropAfter: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    'section-drag-over',
    'section-drop',
    'column-drag-start',
    'column-drag-end',
    'start-edit-label',
    'save-label',
    'cancel-edit-label',
    'request-delete',
    'task-drag-start',
    'task-drag-over',
    'task-drag-end',
    'task-drop',
    'task-open-details',
    'task-open-edit',
]);

const labelDraft = defineModel('labelDraft', {
    type: String,
    default: '',
});

const setInputRef = (element) => {
    if (element) {
        element.focus();
        element.select();
    }
};
</script>

<template>
    <section
        class="task-column flex h-full w-80 min-w-80 shrink-0 flex-col rounded-lg border border-gray-200 bg-white shadow-sm transition"
        :class="{
            'task-column--drop-target': isTaskDropTarget,
            'task-column--dragging': isDraggingColumn,
            'task-column--drop-before': isReorderDropBefore,
            'task-column--drop-after': isReorderDropAfter,
            'task-column--moving': isMoving,
        }"
        @dragover.stop="emit('section-drag-over', $event)"
        @drop.stop.prevent="emit('section-drop')"
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
                    @dragstart.stop="emit('column-drag-start', $event)"
                    @dragend.stop="emit('column-drag-end')"
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
                        v-if="isEditingLabel"
                        :ref="setInputRef"
                        v-model="labelDraft"
                        type="text"
                        maxlength="40"
                        class="block w-full rounded-md border-gray-300 px-2 py-1 text-sm font-semibold text-gray-700 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                        @click.stop
                        @keydown.enter.prevent="emit('save-label')"
                        @keydown.esc.prevent="emit('cancel-edit-label')"
                        @blur="emit('save-label')"
                    />
                    <button
                        v-else
                        type="button"
                        class="block max-w-full truncate rounded-md px-2 py-1 text-left text-sm font-semibold text-gray-700 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click.stop="emit('start-edit-label')"
                    >
                        {{ label }}
                    </button>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <span
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                >
                    {{ tasks.length }}
                </span>
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            aria-label="Column actions"
                            @click.stop
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 16 16"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <circle cx="8" cy="3" r="1.4" />
                                <circle cx="8" cy="8" r="1.4" />
                                <circle cx="8" cy="13" r="1.4" />
                            </svg>
                        </button>
                    </template>
                    <template #content>
                        <button
                            type="button"
                            class="block w-full px-4 py-2 text-left text-sm transition focus:outline-none focus:bg-gray-100"
                            :class="
                                canDelete
                                    ? 'text-rose-600 hover:bg-gray-100 hover:text-rose-700'
                                    : 'cursor-not-allowed text-gray-400'
                            "
                            :disabled="!canDelete"
                            :title="
                                canDelete
                                    ? null
                                    : 'You need at least one column on a board.'
                            "
                            @click.stop="canDelete && emit('request-delete')"
                        >
                            Delete column
                        </button>
                    </template>
                </Dropdown>
            </div>
        </div>
        <div class="flex-1 space-y-4 p-4">
            <div
                v-if="!tasks.length"
                class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-6 text-center text-xs text-gray-500"
            >
                No tasks in this status.
            </div>
            <TaskCard
                v-for="task in tasks"
                :key="task.id"
                :task="task"
                :is-dragging="isTaskDragging(task.id)"
                :is-drop-before-target="isTaskDropBefore(task.id)"
                :is-drop-after-target="isTaskDropAfter(task.id)"
                :can-drag="updatingTaskId !== task.id && !columnsBusy"
                :is-updating="updatingTaskId === task.id"
                @open-details="(t) => emit('task-open-details', t)"
                @open-edit="(t) => emit('task-open-edit', t)"
                @drag-start="(event, t) => emit('task-drag-start', event, t)"
                @drag-over="(event, t) => emit('task-drag-over', event, t)"
                @drop="(t) => emit('task-drop', t)"
                @drag-end="emit('task-drag-end')"
            />
        </div>
    </section>
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
</style>
