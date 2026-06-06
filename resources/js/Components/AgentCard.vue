<script setup>
import { Link } from '@inertiajs/vue3';
import {
    formatDate,
    formatPriority,
    formatStatus,
    priorityBadgeClass,
} from '@/lib/format';
import { computed } from 'vue';

const props = defineProps({
    agent: {
        type: Object,
        required: true,
    },
    archived: {
        type: Boolean,
        default: false,
    },
    workingAction: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['edit', 'archive', 'restore', 'delete']);

const visibleSkills = computed(() => (props.agent.skills ?? []).slice(0, 5));
const hiddenSkillCount = computed(() =>
    Math.max(0, (props.agent.skills?.length ?? 0) - visibleSkills.value.length),
);
const visibleBoards = computed(() => (props.agent.boards ?? []).slice(0, 3));
const hiddenBoardCount = computed(() =>
    Math.max(0, (props.agent.boards?.length ?? 0) - visibleBoards.value.length),
);
const nextTasks = computed(() => (props.agent.next_tasks ?? []).slice(0, 3));
const archivedLabel = computed(() =>
    props.agent.archived_at ? formatDate(props.agent.archived_at) : null,
);
const workloadCount = (key) => Number(props.agent.workload?.[key] ?? 0);
const statusBadgeClass = (status) => {
    if (status === 'completed') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (status === 'in-progress') {
        return 'border-indigo-200 bg-indigo-50 text-indigo-700';
    }

    return 'border-gray-200 bg-gray-50 text-gray-600';
};
const boardHref = (board) => {
    if (!board.id) {
        return null;
    }

    return route('tasks.board', { board: board.id });
};
const assignmentHref = (task) => {
    if (!task.board_id || !task.id) {
        return null;
    }

    const query = new URLSearchParams({ task: task.id }).toString();

    return `${route('tasks.board', { board: task.board_id })}?${query}`;
};
const formatDeadline = (value) => {
    if (!value) {
        return 'No date';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'No date';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(date);
};
const isOverdue = (value) => {
    if (!value) {
        return false;
    }

    const date = new Date(value);

    return !Number.isNaN(date.getTime()) && date.getTime() < Date.now();
};
</script>

<template>
    <article
        class="rounded-md border border-gray-200 bg-gray-50 p-4 shadow-sm transition"
        :class="{ 'border-gray-300 bg-white': archived }"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="truncate text-sm font-semibold text-gray-900">
                    {{ agent.name }}
                </h3>
                <p class="mt-1 truncate text-xs text-gray-500">
                    {{ agent.title || 'AI agent' }} - {{ agent.email }}
                </p>
                <p
                    v-if="archived && archivedLabel"
                    class="mt-1 text-[11px] font-medium uppercase tracking-wide text-gray-400"
                >
                    Archived {{ archivedLabel }}
                </p>
            </div>
            <span
                class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                :class="
                    archived
                        ? 'border-gray-200 bg-gray-100 text-gray-600'
                        : 'border-teal-200 bg-teal-50 text-teal-700'
                "
            >
                {{ archived ? 'Archived' : 'Agent' }}
            </span>
        </div>

        <p class="mt-3 text-sm leading-6 text-gray-600">
            {{ agent.profile || 'No profile yet.' }}
        </p>

        <div class="mt-3 grid grid-cols-3 divide-x divide-gray-200 border-y border-gray-200 py-2 text-center">
            <div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ workloadCount('boards') }}
                </div>
                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                    Boards
                </div>
            </div>
            <div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ workloadCount('active_tasks') }}
                </div>
                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                    Active
                </div>
            </div>
            <div>
                <div
                    class="text-sm font-semibold"
                    :class="
                        workloadCount('overdue_tasks') > 0
                            ? 'text-rose-600'
                            : 'text-gray-900'
                    "
                >
                    {{ workloadCount('overdue_tasks') }}
                </div>
                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                    Overdue
                </div>
            </div>
        </div>

        <div
            v-if="visibleBoards.length"
            class="mt-3 space-y-2"
        >
            <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                Board Access
            </div>
            <div class="flex flex-wrap gap-1.5">
                <Link
                    v-for="board in visibleBoards"
                    :key="`${agent.id}-board-${board.id}`"
                    :href="boardHref(board)"
                    class="max-w-full truncate rounded-full border border-indigo-100 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 transition hover:border-indigo-200 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    {{ board.name }}
                </Link>
                <span
                    v-if="hiddenBoardCount"
                    class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500"
                >
                    +{{ hiddenBoardCount }}
                </span>
            </div>
        </div>

        <div
            v-if="agent.personality"
            class="mt-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs leading-5 text-gray-600"
        >
            <span class="font-semibold uppercase tracking-wide text-gray-500">
                Personality
            </span>
            <span class="ml-1">{{ agent.personality }}</span>
        </div>

        <div
            v-if="nextTasks.length"
            class="mt-3 rounded-md border border-gray-200 bg-white"
        >
            <div class="border-b border-gray-100 px-3 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                Next Assignments
            </div>
            <ul class="divide-y divide-gray-100">
                <li
                    v-for="task in nextTasks"
                    :key="`${agent.id}-task-${task.id}`"
                    class="px-2 py-1.5"
                >
                    <Link
                        v-if="assignmentHref(task)"
                        :href="assignmentHref(task)"
                        class="block rounded px-1 py-0.5 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        <div class="flex items-center gap-2">
                            <div class="min-w-0 flex-1 truncate text-xs font-semibold text-gray-800">
                                {{ task.title }}
                            </div>
                            <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-indigo-600">
                                Open
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px]">
                            <span
                                class="rounded-full border px-1.5 py-0.5 font-semibold uppercase tracking-wide"
                                :class="statusBadgeClass(task.status)"
                            >
                                {{ formatStatus(task.status) }}
                            </span>
                            <span
                                class="rounded-full border px-1.5 py-0.5 font-semibold uppercase tracking-wide"
                                :class="priorityBadgeClass(task.priority)"
                            >
                                {{ formatPriority(task.priority) }}
                            </span>
                            <span class="truncate text-[11px] text-gray-500">
                                {{ task.board_name || 'Board' }}
                            </span>
                            <span
                                class="shrink-0 text-[11px] font-medium"
                                :class="isOverdue(task.deadline_at) ? 'text-rose-600' : 'text-gray-500'"
                            >
                                {{ formatDeadline(task.deadline_at) }}
                            </span>
                        </div>
                    </Link>
                    <div v-else class="px-1 py-0.5">
                        <div class="truncate text-xs font-semibold text-gray-800">
                            {{ task.title }}
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px]">
                            <span
                                class="rounded-full border px-1.5 py-0.5 font-semibold uppercase tracking-wide"
                                :class="statusBadgeClass(task.status)"
                            >
                                {{ formatStatus(task.status) }}
                            </span>
                            <span
                                class="rounded-full border px-1.5 py-0.5 font-semibold uppercase tracking-wide"
                                :class="priorityBadgeClass(task.priority)"
                            >
                                {{ formatPriority(task.priority) }}
                            </span>
                            <span class="truncate text-[11px] text-gray-500">
                                {{ task.board_name || 'Board' }}
                            </span>
                            <span
                                class="shrink-0 text-[11px] font-medium"
                                :class="isOverdue(task.deadline_at) ? 'text-rose-600' : 'text-gray-500'"
                            >
                                {{ formatDeadline(task.deadline_at) }}
                            </span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div
            v-if="visibleSkills.length"
            class="mt-3 flex flex-wrap gap-1.5"
        >
            <span
                v-for="skill in visibleSkills"
                :key="`${agent.id}-${skill}`"
                class="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700"
            >
                {{ skill }}
            </span>
            <span
                v-if="hiddenSkillCount"
                class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500"
            >
                +{{ hiddenSkillCount }}
            </span>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            <button
                type="button"
                class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                :disabled="workingAction !== null"
                @click="emit('edit', agent)"
            >
                Edit
            </button>
            <button
                v-if="archived"
                type="button"
                class="rounded-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-gray-600 transition hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-25"
                :disabled="workingAction !== null"
                @click="emit('restore', agent)"
            >
                {{ workingAction === 'restore' ? 'Restoring...' : 'Restore' }}
            </button>
            <button
                v-else
                type="button"
                class="rounded-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-gray-600 transition hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-25"
                :disabled="workingAction !== null"
                @click="emit('archive', agent)"
            >
                {{ workingAction === 'archive' ? 'Archiving...' : 'Archive' }}
            </button>
            <button
                type="button"
                class="rounded-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:opacity-25"
                :disabled="workingAction !== null"
                @click="emit('delete', agent)"
            >
                {{ workingAction === 'delete' ? 'Deleting...' : 'Delete' }}
            </button>
        </div>
    </article>
</template>
