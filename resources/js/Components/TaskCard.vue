<script setup>
import AvatarGroup from '@/Components/AvatarGroup.vue';
import {
    deadlineBadgeClass,
    formatDeadlineLabel,
    formatPriority,
    priorityBadgeClass,
} from '@/lib/format';
import { commentCount, hiddenTagCount, visibleTags } from '@/lib/task';

defineProps({
    task: {
        type: Object,
        required: true,
    },
    isDragging: {
        type: Boolean,
        default: false,
    },
    isDropBeforeTarget: {
        type: Boolean,
        default: false,
    },
    isDropAfterTarget: {
        type: Boolean,
        default: false,
    },
    canDrag: {
        type: Boolean,
        default: true,
    },
    isUpdating: {
        type: Boolean,
        default: false,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    'open-details',
    'open-edit',
    'drag-start',
    'drag-over',
    'drag-end',
    'drop',
]);

const completedChecklistCount = (task) =>
    Array.isArray(task.checklist_items)
        ? task.checklist_items.filter((item) => item.completed).length
        : 0;

const checklistPercent = (task) => {
    const total = Array.isArray(task.checklist_items)
        ? task.checklist_items.length
        : 0;

    if (total === 0) {
        return 0;
    }

    return Math.round((completedChecklistCount(task) / total) * 100);
};

const taskCommentCount = (task) =>
    Array.isArray(task.comments) ? commentCount(task.comments) : 0;
</script>

<template>
    <article
        class="task-card rounded-md border border-gray-200 bg-gray-50 p-4 shadow-sm transition"
        :class="{
            'task-card--dragging': isDragging,
            'task-card--drop-before': isDropBeforeTarget,
            'task-card--drop-after': isDropAfterTarget,
            'task-card--static': !canDrag,
        }"
        role="button"
        tabindex="0"
        :draggable="canDrag"
        @click="emit('open-details', task)"
        @keydown.enter.prevent="emit('open-details', task)"
        @keydown.space.prevent="emit('open-details', task)"
        @dragstart="emit('drag-start', $event, task)"
        @dragover.stop="emit('drag-over', $event, task)"
        @drop.stop.prevent="emit('drop', task)"
        @dragend="emit('drag-end')"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <h4 class="text-sm font-semibold text-gray-800">
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
                v-if="task.tags.length"
                class="mb-3 flex flex-wrap gap-1.5"
            >
                <span
                    v-for="tag in visibleTags(task.tags)"
                    :key="`${task.id}-${tag}`"
                    class="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700"
                >
                    {{ tag }}
                </span>
                <span
                    v-if="hiddenTagCount(task.tags)"
                    class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500"
                >
                    +{{ hiddenTagCount(task.tags) }}
                </span>
            </div>
            <div class="h-2 w-full rounded-full bg-gray-200">
                <div
                    class="h-2 rounded-full bg-gray-800"
                    :style="{ width: `${task.progress}%` }"
                />
            </div>
            <div class="mt-1 text-[10px] text-gray-500">
                {{ task.progress }}% complete
            </div>
        </div>

        <div
            v-if="task.checklist_items?.length"
            class="mt-3 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-[11px] text-gray-600"
        >
            <div class="flex items-center justify-between gap-3">
                <span class="font-semibold uppercase tracking-wide">
                    Checklist
                </span>
                <span>
                    {{ completedChecklistCount(task) }}/{{ task.checklist_items.length }}
                    done · {{ checklistPercent(task) }}%
                </span>
            </div>
            <div class="mt-1.5 h-1.5 rounded-full bg-gray-100">
                <div
                    class="h-1.5 rounded-full bg-teal-500"
                    :style="{ width: `${checklistPercent(task)}%` }"
                />
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <AvatarGroup
                v-if="task.assignees && task.assignees.length"
                :users="task.assignees"
                :max="3"
            />
            <span
                v-if="formatDeadlineLabel(task.deadline_at)"
                class="rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                :class="deadlineBadgeClass(task.deadline_at)"
            >
                {{ formatDeadlineLabel(task.deadline_at) }}
            </span>
            <span
                v-if="taskCommentCount(task)"
                class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500"
            >
                {{ taskCommentCount(task) }}
                {{ taskCommentCount(task) === 1 ? 'Comment' : 'Comments' }}
            </span>
            <div class="ml-auto flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-gray-600 transition hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    @click.stop="emit('open-details', task)"
                >
                    Open
                </button>
                <button
                    v-if="canEdit"
                    type="button"
                    class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                    :disabled="isUpdating"
                    @click.stop="emit('open-edit', task)"
                >
                    Edit
                </button>
                <span
                    v-if="isUpdating"
                    class="text-[11px] text-gray-400"
                >
                    Updating...
                </span>
            </div>
        </div>
    </article>
</template>

<style scoped>
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

.task-card--static {
    cursor: pointer;
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
</style>
