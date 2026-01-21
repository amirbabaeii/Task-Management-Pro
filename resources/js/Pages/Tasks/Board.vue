<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
});

const tasks = ref(props.tasks.map((task) => ({ ...task })));
const movingId = ref(null);
const errorMessage = ref('');

const statusLabels = {
    pending: 'Pending',
    'in-progress': 'In Progress',
    completed: 'Completed',
};

const boardStatuses = computed(() =>
    props.statuses.length
        ? props.statuses
        : ['pending', 'in-progress', 'completed'],
);

const formatStatus = (status) => statusLabels[status] ?? status;

const formatPriority = (priority) => {
    if (!priority) {
        return 'Unspecified';
    }

    return `${priority.charAt(0).toUpperCase()}${priority.slice(1)}`;
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

    return grouped;
});

const updateStatus = async (task, nextStatus) => {
    if (!nextStatus || task.status === nextStatus || movingId.value) {
        return;
    }

    errorMessage.value = '';
    movingId.value = task.id;
    const previousStatus = task.status;
    task.status = nextStatus;

    try {
        await axios.patch(route('tasks.status', task.id), {
            status: nextStatus,
        });
    } catch (error) {
        task.status = previousStatus;
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to update task status. Please try again.';
    } finally {
        movingId.value = null;
    }
};

const onStatusChange = (event, task) => {
    updateStatus(task, event.target.value);
};
</script>

<template>
    <Head title="Task Board" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Task Board
                </h2>
                <p class="text-sm text-gray-500">
                    Move tasks between statuses to keep work flowing.
                </p>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-3">
                    <section
                        v-for="status in boardStatuses"
                        :key="status"
                        class="flex flex-col rounded-lg border border-gray-200 bg-white shadow-sm"
                    >
                        <div
                            class="flex items-center justify-between border-b border-gray-100 px-4 py-3"
                        >
                            <h3 class="text-sm font-semibold text-gray-700">
                                {{ formatStatus(status) }}
                            </h3>
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
                                class="rounded-md border border-gray-200 bg-gray-50 p-4 shadow-sm"
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
                                        class="rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-700"
                                    >
                                        {{ formatPriority(task.priority) }}
                                    </span>
                                </div>

                                <div
                                    v-if="
                                        task.progress !== null &&
                                        task.progress !== undefined
                                    "
                                    class="mt-3"
                                >
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

                                <div
                                    class="mt-3 flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span
                                        v-if="formatDate(task.deadline_at)"
                                        class="text-[11px] text-gray-500"
                                    >
                                        Due {{ formatDate(task.deadline_at) }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="movingId === task.id"
                                            class="text-[11px] text-gray-400"
                                        >
                                            Updating...
                                        </span>
                                        <select
                                            class="block w-32 rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500"
                                            :value="task.status"
                                            :disabled="movingId === task.id"
                                            @change="onStatusChange($event, task)"
                                        >
                                            <option
                                                v-for="option in boardStatuses"
                                                :key="option"
                                                :value="option"
                                            >
                                                {{ formatStatus(option) }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>

                <p
                    v-if="errorMessage"
                    class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ errorMessage }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
