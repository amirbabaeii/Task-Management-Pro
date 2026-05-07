<script setup>
import AvatarGroup from '@/Components/AvatarGroup.vue';
import {
    formatDate,
    formatPriority,
    priorityBadgeClass,
} from '@/lib/format';
import { hiddenTagCount, visibleTags } from '@/lib/task';

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
});

const emit = defineEmits([
    'open-details',
    'open-edit',
    'drag-start',
    'drag-over',
    'drag-end',
    'drop',
]);
</script>

<template>
    <article
        class="task-card rounded-md border border-gray-200 bg-gray-50 p-4 shadow-sm transition"
        :class="{
            'task-card--dragging': isDragging,
            'task-card--drop-before': isDropBeforeTarget,
            'task-card--drop-after': isDropAfterTarget,
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

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <AvatarGroup
                v-if="task.assignees && task.assignees.length"
                :users="task.assignees"
                :max="3"
            />
            <span
                v-if="formatDate(task.deadline_at)"
                class="text-[11px] text-gray-500"
            >
                Due {{ formatDate(task.deadline_at) }}
            </span>
            <div class="ml-auto flex items-center gap-2">
                <button
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
