<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    boardId: {
        type: Number,
        default: null,
    },
    task: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['task-refresh-needed']);

const runs = ref([]);
const loading = ref(false);
const starting = ref(false);
const errorMessage = ref('');
const selectedAgentId = ref(null);
const selectedAutonomy = ref('');
const busyActionKey = ref(null);
let pollTimer = null;

const assignedAgents = computed(() =>
    (props.task?.assignees ?? []).filter(
        (assignee) => assignee.is_agent && !assignee.is_archived_agent,
    ),
);

const activeRun = computed(() =>
    runs.value.find((run) =>
        ['queued', 'running', 'awaiting_approval'].includes(run.status),
    ),
);

const hasPollingRun = computed(() =>
    runs.value.some((run) => ['queued', 'running'].includes(run.status)),
);

const canStartRun = computed(
    () =>
        props.boardId &&
        props.task?.id &&
        assignedAgents.value.length > 0 &&
        !activeRun.value &&
        !starting.value,
);

const statusLabels = {
    queued: 'Queued',
    running: 'Running',
    awaiting_approval: 'Awaiting approval',
    completed: 'Completed',
    failed: 'Failed',
};

const actionTypeLabels = {
    add_comment: 'Add comment',
    add_checklist_item: 'Add checklist item',
    toggle_checklist_item: 'Toggle checklist item',
    update_progress: 'Update progress',
    change_status: 'Change status',
    update_task_fields: 'Update task fields',
};

const actionStatusLabels = {
    suggested: 'Suggested',
    proposed: 'Needs approval',
    applied: 'Applied',
    rejected: 'Rejected',
    failed: 'Failed',
};

const routeTo = (name, params = {}) => route(name, params);

const resetStartSelection = () => {
    selectedAgentId.value = assignedAgents.value[0]?.id ?? null;
    selectedAutonomy.value = '';
};

const replaceRun = (run) => {
    const index = runs.value.findIndex((existing) => existing.id === run.id);

    if (index === -1) {
        runs.value = [run, ...runs.value];
    } else {
        runs.value = runs.value.map((existing) =>
            existing.id === run.id ? run : existing,
        );
    }

    if (['completed', 'awaiting_approval'].includes(run.status)) {
        emit('task-refresh-needed');
    }
};

const loadRuns = async ({ silent = false } = {}) => {
    if (!props.boardId || !props.task?.id) {
        runs.value = [];
        return;
    }

    if (!silent) {
        loading.value = true;
    }
    errorMessage.value = '';

    try {
        const response = await axios.get(
            routeTo('tasks.agent-runs.index', {
                board: props.boardId,
                task: props.task.id,
            }),
        );

        runs.value = response?.data?.agent_runs ?? [];
        resetStartSelection();
        updatePolling();
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to load agent runs right now.';
    } finally {
        loading.value = false;
    }
};

const clearPolling = () => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
};

const updatePolling = () => {
    clearPolling();

    if (!hasPollingRun.value) {
        return;
    }

    pollTimer = window.setInterval(() => {
        loadRuns({ silent: true });
    }, 3000);
};

const startRun = async () => {
    if (!canStartRun.value || !selectedAgentId.value) {
        return;
    }

    starting.value = true;
    errorMessage.value = '';

    const payload = {
        agent_id: selectedAgentId.value,
    };

    if (selectedAutonomy.value) {
        payload.autonomy = selectedAutonomy.value;
    }

    try {
        const response = await axios.post(
            routeTo('tasks.agent-runs.store', {
                board: props.boardId,
                task: props.task.id,
            }),
            payload,
        );

        if (response?.data?.agent_run) {
            replaceRun(response.data.agent_run);
            updatePolling();
        }
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors ?? {})?.[0]?.[0] ||
            'Unable to start the agent run.';
    } finally {
        starting.value = false;
    }
};

const runAction = async (key, request, refreshTask = false) => {
    busyActionKey.value = key;
    errorMessage.value = '';

    try {
        const response = await request();

        if (response?.data?.agent_run) {
            replaceRun(response.data.agent_run);
        }

        if (refreshTask) {
            emit('task-refresh-needed');
        }
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors ?? {})?.[0]?.[0] ||
            'Unable to update this agent run.';
    } finally {
        busyActionKey.value = null;
    }
};

const approveAction = (run, action) =>
    runAction(
        `approve-${action.id}`,
        () =>
            axios.post(
                routeTo('agent-runs.actions.approve', {
                    agentRun: run.id,
                    action: action.id,
                }),
            ),
        true,
    );

const rejectAction = (run, action) =>
    runAction(
        `reject-${action.id}`,
        () =>
            axios.post(
                routeTo('agent-runs.actions.reject', {
                    agentRun: run.id,
                    action: action.id,
                }),
            ),
    );

const approveAll = (run) =>
    runAction(
        `approve-all-${run.id}`,
        () =>
            axios.post(
                routeTo('agent-runs.approve-all', {
                    agentRun: run.id,
                }),
            ),
        true,
    );

const retryRun = (run) =>
    runAction(
        `retry-${run.id}`,
        () =>
            axios.post(
                routeTo('agent-runs.retry', {
                    agentRun: run.id,
                }),
            ),
    );

const actionSummary = (action) => {
    const payload = action.payload ?? {};

    switch (action.type) {
        case 'add_comment':
            return payload.comment;
        case 'add_checklist_item':
            return payload.title;
        case 'toggle_checklist_item':
            return `Checklist #${payload.checklist_item_id}: ${payload.completed ? 'complete' : 'reopen'}`;
        case 'update_progress':
            return `${payload.progress}%`;
        case 'change_status':
            return payload.status;
        case 'update_task_fields':
            return Object.entries(payload.fields ?? {})
                .filter(([, value]) => value !== null && value !== undefined)
                .map(([field, value]) => `${field}: ${Array.isArray(value) ? value.join(', ') : value}`)
                .join(' - ');
        default:
            return '';
    }
};

watch(
    () => props.task?.id,
    () => {
        clearPolling();
        resetStartSelection();
        loadRuns();
    },
    { immediate: true },
);

watch(assignedAgents, resetStartSelection);

onBeforeUnmount(clearPolling);
</script>

<template>
    <section class="space-y-4 rounded-lg border border-indigo-100 bg-indigo-50/40 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h4 class="text-sm font-semibold text-gray-900">
                    Agent Runs
                </h4>
                <p class="mt-1 text-xs text-gray-600">
                    Ask an assigned AI teammate to review this task and propose next actions.
                </p>
            </div>
            <span
                v-if="activeRun"
                class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700"
            >
                {{ statusLabels[activeRun.status] ?? activeRun.status }}
            </span>
        </div>

        <div class="grid gap-3 md:grid-cols-[1fr_auto]">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-gray-600">
                    Agent
                    <select
                        v-model.number="selectedAgentId"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        :disabled="assignedAgents.length === 0 || Boolean(activeRun)"
                    >
                        <option
                            v-for="agent in assignedAgents"
                            :key="agent.id"
                            :value="agent.id"
                        >
                            {{ agent.name }}
                        </option>
                    </select>
                </label>
                <label class="text-xs font-semibold uppercase tracking-wide text-gray-600">
                    Run mode
                    <select
                        v-model="selectedAutonomy"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        :disabled="Boolean(activeRun)"
                    >
                        <option value="">Agent default</option>
                        <option value="advisory">Advisory</option>
                        <option value="approval">Approval</option>
                        <option value="automatic">Automatic</option>
                    </select>
                </label>
            </div>
            <div class="flex items-end">
                <PrimaryButton
                    :disabled="!canStartRun"
                    :class="{ 'opacity-25': !canStartRun }"
                    @click="startRun"
                >
                    {{ starting ? 'Starting...' : 'Start Run' }}
                </PrimaryButton>
            </div>
        </div>

        <p v-if="assignedAgents.length === 0" class="text-sm text-gray-500">
            Assign an active AI teammate to this task before starting a run.
        </p>

        <p v-if="errorMessage" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ errorMessage }}
        </p>

        <p v-if="loading" class="text-sm text-gray-500">
            Loading agent runs...
        </p>

        <div v-else-if="runs.length === 0" class="rounded-md border border-dashed border-indigo-200 bg-white/70 px-3 py-4 text-sm text-gray-500">
            No agent runs yet.
        </div>

        <div v-else class="space-y-3">
            <article
                v-for="run in runs"
                :key="run.id"
                class="space-y-3 rounded-lg border border-gray-200 bg-white p-3"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-gray-900">
                                {{ run.agent?.name ?? 'AI teammate' }}
                            </span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-600">
                                {{ run.autonomy }}
                            </span>
                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-700">
                                {{ statusLabels[run.status] ?? run.status }}
                            </span>
                        </div>
                        <p v-if="run.summary" class="mt-2 text-sm text-gray-700">
                            {{ run.summary }}
                        </p>
                        <p v-if="run.error?.message" class="mt-2 text-sm text-rose-700">
                            {{ run.error.message }}
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <SecondaryButton
                            v-if="run.status === 'awaiting_approval'"
                            :disabled="busyActionKey === `approve-all-${run.id}`"
                            @click="approveAll(run)"
                        >
                            Approve All
                        </SecondaryButton>
                        <SecondaryButton
                            v-if="run.status === 'failed'"
                            :disabled="busyActionKey === `retry-${run.id}`"
                            @click="retryRun(run)"
                        >
                            Retry
                        </SecondaryButton>
                    </div>
                </div>

                <div
                    v-if="run.actions?.length"
                    class="space-y-2"
                >
                    <div
                        v-for="action in run.actions"
                        :key="action.id"
                        class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-800">
                                        {{ actionTypeLabels[action.type] ?? action.type }}
                                    </span>
                                    <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-600">
                                        {{ actionStatusLabels[action.status] ?? action.status }}
                                    </span>
                                </div>
                                <p
                                    v-if="actionSummary(action)"
                                    class="mt-1 text-sm text-gray-600"
                                >
                                    {{ actionSummary(action) }}
                                </p>
                                <p
                                    v-if="action.error_message"
                                    class="mt-1 text-sm text-rose-700"
                                >
                                    {{ action.error_message }}
                                </p>
                            </div>
                            <div
                                v-if="action.status === 'proposed'"
                                class="flex shrink-0 gap-2"
                            >
                                <SecondaryButton
                                    :disabled="busyActionKey === `reject-${action.id}`"
                                    @click="rejectAction(run, action)"
                                >
                                    Reject
                                </SecondaryButton>
                                <PrimaryButton
                                    :disabled="busyActionKey === `approve-${action.id}`"
                                    @click="approveAction(run, action)"
                                >
                                    Approve
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
