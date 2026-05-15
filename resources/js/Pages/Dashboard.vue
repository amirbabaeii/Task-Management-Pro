<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    deadlineBadgeClass,
    formatDeadlineLabel,
    formatDateTime,
    formatPriority,
    formatStatus,
    priorityBadgeClass,
} from '@/lib/format';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    dashboard: {
        type: Object,
        default: () => ({
            summary: {},
            boards: [],
            upcoming_tasks: [],
            recent_activity: [],
        }),
    },
});

const summary = computed(() => props.dashboard.summary ?? {});
const boards = computed(() => props.dashboard.boards ?? []);
const upcomingTasks = computed(() => props.dashboard.upcoming_tasks ?? []);
const recentActivity = computed(() => props.dashboard.recent_activity ?? []);
const primaryBoardHref = computed(() =>
    boards.value.length
        ? route('tasks.board', { board: boards.value[0].id })
        : route('tasks.board'),
);

const metrics = computed(() => [
    {
        label: 'Active',
        value: summary.value.active_tasks ?? 0,
        detail: `${summary.value.total_tasks ?? 0} assigned total`,
        class: 'border-gray-200 bg-white text-gray-900',
    },
    {
        label: 'Overdue',
        value: summary.value.overdue_tasks ?? 0,
        detail: 'Need attention',
        class:
            (summary.value.overdue_tasks ?? 0) > 0
                ? 'border-rose-200 bg-rose-50 text-rose-900'
                : 'border-gray-200 bg-white text-gray-900',
    },
    {
        label: 'Due today',
        value: summary.value.due_today_tasks ?? 0,
        detail: 'Calendar day',
        class:
            (summary.value.due_today_tasks ?? 0) > 0
                ? 'border-amber-200 bg-amber-50 text-amber-900'
                : 'border-gray-200 bg-white text-gray-900',
    },
    {
        label: 'Completed',
        value: summary.value.completed_tasks ?? 0,
        detail: 'Across all boards',
        class: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    },
]);

const completionPercent = (counts = {}) => {
    const total = counts.total_tasks ?? 0;

    if (total === 0) {
        return 0;
    }

    return Math.round(((counts.completed_tasks ?? 0) / total) * 100);
};

const activityDotClass = (kind) => {
    switch (kind) {
        case 'created':
            return 'bg-emerald-400';
        case 'status_changed':
            return 'bg-indigo-400';
        case 'assignees_changed':
            return 'bg-amber-400';
        case 'comment_added':
            return 'bg-sky-400';
        case 'archived':
            return 'bg-gray-400';
        case 'restored':
            return 'bg-emerald-400';
        default:
            return 'bg-gray-300';
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Dashboard
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ summary.active_tasks ?? 0 }} active tasks across {{ boards.length }} boards
                    </p>
                </div>
                <Link
                    :href="primaryBoardHref"
                    class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Open Board
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="metric in metrics"
                        :key="metric.label"
                        class="rounded-lg border p-4 shadow-sm"
                        :class="metric.class"
                    >
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ metric.label }}
                        </div>
                        <div class="mt-2 text-3xl font-semibold">
                            {{ metric.value }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ metric.detail }}
                        </div>
                    </div>
                </section>

                <section class="grid gap-8 lg:grid-cols-[minmax(0,1.55fr)_minmax(20rem,0.9fr)]">
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                                Boards
                            </h3>
                            <span class="text-xs text-gray-500">
                                {{ summary.due_soon_tasks ?? 0 }} due in the next week
                            </span>
                        </div>

                        <div
                            v-if="boards.length"
                            class="grid gap-3 md:grid-cols-2"
                        >
                            <Link
                                v-for="board in boards"
                                :key="board.id"
                                :href="route('tasks.board', { board: board.id })"
                                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-gray-900">
                                            {{ board.name }}
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ board.is_owner ? 'Owner' : 'Collaborator' }}
                                        </div>
                                    </div>
                                    <span
                                        v-if="board.task_counts.overdue_tasks > 0"
                                        class="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-700"
                                    >
                                        {{ board.task_counts.overdue_tasks }} overdue
                                    </span>
                                </div>

                                <div class="mt-4 grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <div class="font-semibold text-gray-900">
                                            {{ board.task_counts.active_tasks }}
                                        </div>
                                        <div class="text-gray-500">Active</div>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900">
                                            {{ board.task_counts.due_today_tasks }}
                                        </div>
                                        <div class="text-gray-500">Today</div>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900">
                                            {{ board.task_counts.completed_tasks }}
                                        </div>
                                        <div class="text-gray-500">Done</div>
                                    </div>
                                </div>

                                <div class="mt-4 h-2 rounded-full bg-gray-100">
                                    <div
                                        class="h-2 rounded-full bg-gray-800"
                                        :style="{ width: `${completionPercent(board.task_counts)}%` }"
                                    />
                                </div>
                                <div class="mt-1 text-[11px] text-gray-500">
                                    {{ completionPercent(board.task_counts) }}% complete
                                </div>
                            </Link>
                        </div>

                        <div
                            v-else
                            class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500"
                        >
                            No boards yet.
                        </div>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                                Due Next
                            </h3>
                            <span class="text-xs text-gray-500">
                                {{ upcomingTasks.length }} tasks
                            </span>
                        </div>

                        <div
                            v-if="upcomingTasks.length"
                            class="space-y-3"
                        >
                            <Link
                                v-for="task in upcomingTasks"
                                :key="task.id"
                                :href="route('tasks.board', { board: task.board.id })"
                                class="block rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-gray-900">
                                            {{ task.title }}
                                        </div>
                                        <div class="mt-1 truncate text-xs text-gray-500">
                                            {{ task.board?.name }} · {{ formatStatus(task.status) }}
                                        </div>
                                    </div>
                                    <span
                                        class="rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                        :class="priorityBadgeClass(task.priority)"
                                    >
                                        {{ formatPriority(task.priority) }}
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                    <span
                                        class="rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                        :class="deadlineBadgeClass(task.deadline_at)"
                                    >
                                        {{ formatDeadlineLabel(task.deadline_at) }}
                                    </span>
                                    <span class="text-gray-500">
                                        {{ task.progress }}% complete
                                    </span>
                                </div>
                            </Link>
                        </div>

                        <div
                            v-else
                            class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500"
                        >
                            No dated active tasks.
                        </div>
                    </div>
                </section>

                <section>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                            Recent Activity
                        </h3>
                        <span class="text-xs text-gray-500">
                            {{ recentActivity.length }} updates
                        </span>
                    </div>

                    <div
                        v-if="recentActivity.length"
                        class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                    >
                        <Link
                            v-for="activity in recentActivity"
                            :key="activity.id"
                            :href="route('tasks.board', { board: activity.board.id })"
                            class="flex flex-col gap-2 border-b border-gray-100 px-4 py-3 transition last:border-b-0 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:flex-row sm:items-start sm:gap-3"
                        >
                            <span class="flex min-w-0 flex-1 gap-3">
                                <span
                                    class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                                    :class="activityDotClass(activity.kind)"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm text-gray-700">
                                        {{ activity.text }}
                                    </span>
                                    <span class="mt-1 block truncate text-xs text-gray-500">
                                        {{ activity.task.title }} · {{ activity.board.name }}
                                    </span>
                                </span>
                            </span>
                            <span class="shrink-0 pl-5 text-xs text-gray-400 sm:pl-0">
                                {{ formatDateTime(activity.created_at) }}
                            </span>
                        </Link>
                    </div>

                    <div
                        v-else
                        class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500"
                    >
                        No recent activity yet.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
