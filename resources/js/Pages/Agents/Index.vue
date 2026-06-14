<script setup>
import AgentCard from '@/Components/AgentCard.vue';
import AgentFormModal from '@/Components/AgentFormModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import UndoToast from '@/Components/UndoToast.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    agents: {
        type: Array,
        default: () => [],
    },
    archivedAgents: {
        type: Array,
        default: () => [],
    },
    providerConnections: {
        type: Array,
        default: () => [],
    },
});

const agents = ref([...props.agents]);
const archivedAgents = ref([...props.archivedAgents]);
const showingArchived = ref(false);
const searchQuery = ref('');
const boardFilter = ref(null);
const skillFilter = ref('');
const workloadFilter = ref('all');
const showingFormModal = ref(false);
const editingAgentId = ref(null);
const saving = ref(false);
const archivingAgentId = ref(null);
const restoringAgentId = ref(null);
const errors = ref({});
const errorMessage = ref('');
const pendingAgentDeletion = ref(null);
let pendingAgentDeletionTimer = null;

const blankForm = () => ({
    name: '',
    email: '',
    agent_provider_connection_id: props.providerConnections[0]?.id ?? null,
    agent_model: '',
    agent_autonomy: 'approval',
    agent_title: '',
    agent_profile: '',
    agent_personality: '',
    agent_skills: [],
});

const form = reactive(blankForm());

const activeAgents = computed(() => agents.value);
const currentAgents = computed(() =>
    showingArchived.value ? archivedAgents.value : agents.value,
);
const currentAgentsLabel = computed(() =>
    showingArchived.value ? 'archived' : 'active',
);
const workloadFilterOptions = [
    { value: 'all', label: 'All' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'working', label: 'Working' },
    { value: 'idle', label: 'Idle' },
];
const sortOptions = [
    { value: 'name', label: 'Name' },
    { value: 'active', label: 'Active tasks' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'boards', label: 'Boards' },
];
const sortOptionValues = sortOptions.map((option) => option.value);
const sortStorageKey = 'task-management-pro.agent-sort';
const readSavedSortMode = () => {
    if (typeof window === 'undefined') {
        return 'name';
    }

    try {
        const saved = window.localStorage.getItem(sortStorageKey);

        return sortOptionValues.includes(saved) ? saved : 'name';
    } catch {
        return 'name';
    }
};
const sortMode = ref(readSavedSortMode());

watch(sortMode, (mode) => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(sortStorageKey, mode);
    } catch {
        // Local storage is a convenience only.
    }
});
const editingAgent = computed(
    () =>
        [...agents.value, ...archivedAgents.value].find(
            (agent) => agent.id === editingAgentId.value,
        ) ?? null,
);
const isEditing = computed(() => editingAgent.value !== null);
const hasAgentFilters = computed(
    () =>
        searchQuery.value.trim() !== '' ||
        boardFilter.value !== null ||
        skillFilter.value !== '' ||
        workloadFilter.value !== 'all',
);
const boardFilterOptions = computed(() => {
    const boardsById = new Map();

    currentAgents.value.forEach((agent) => {
        (agent.boards ?? []).forEach((board) => {
            if (board.id && ! boardsById.has(Number(board.id))) {
                boardsById.set(Number(board.id), {
                    id: Number(board.id),
                    name: board.name,
                });
            }
        });
    });

    return [...boardsById.values()].sort((a, b) =>
        a.name.localeCompare(b.name),
    );
});
const skillFilterOptions = computed(() =>
    [
        ...new Set(
            currentAgents.value
                .flatMap((agent) => agent.skills ?? [])
                .map((skill) => `${skill}`.trim())
                .filter(Boolean),
        ),
    ].sort((a, b) => a.localeCompare(b)),
);

const searchableText = (agent) =>
    [
        agent.name,
        agent.email,
        agent.title,
        agent.profile,
        agent.personality,
        ...(agent.skills ?? []),
        ...(agent.boards ?? []).map((board) => board.name),
        ...(agent.next_tasks ?? []).flatMap((task) => [
            task.title,
            task.board_name,
        ]),
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

const matchesWorkloadFilter = (agent) => {
    const activeTasks = Number(agent.workload?.active_tasks ?? 0);
    const overdueTasks = Number(agent.workload?.overdue_tasks ?? 0);

    if (workloadFilter.value === 'overdue') {
        return overdueTasks > 0;
    }

    if (workloadFilter.value === 'working') {
        return activeTasks > 0;
    }

    if (workloadFilter.value === 'idle') {
        return activeTasks === 0;
    }

    return true;
};

const matchesBoardFilter = (agent) =>
    boardFilter.value === null ||
    (agent.boards ?? []).some(
        (board) => Number(board.id) === Number(boardFilter.value),
    );

const matchesSkillFilter = (agent) =>
    skillFilter.value === '' ||
    (agent.skills ?? []).some(
        (skill) =>
            `${skill}`.toLowerCase() === skillFilter.value.toLowerCase(),
    );

const visibleAgents = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return currentAgents.value.filter((agent) => {
        const matchesSearch = query === '' || searchableText(agent).includes(query);

        return (
            matchesSearch &&
            matchesBoardFilter(agent) &&
            matchesSkillFilter(agent) &&
            matchesWorkloadFilter(agent)
        );
    });
});

const sortedVisibleAgents = computed(() =>
    [...visibleAgents.value].sort((a, b) => {
        const nameSort = (a.name ?? '').localeCompare(b.name ?? '');

        if (sortMode.value === 'active') {
            return (
                Number(b.workload?.active_tasks ?? 0) -
                    Number(a.workload?.active_tasks ?? 0) || nameSort
            );
        }

        if (sortMode.value === 'overdue') {
            return (
                Number(b.workload?.overdue_tasks ?? 0) -
                    Number(a.workload?.overdue_tasks ?? 0) || nameSort
            );
        }

        if (sortMode.value === 'boards') {
            return (
                Number(b.workload?.boards ?? 0) -
                    Number(a.workload?.boards ?? 0) || nameSort
            );
        }

        return nameSort;
    }),
);

const sortAgents = (items) =>
    [...items].sort((a, b) => a.name.localeCompare(b.name));

const clearAgentFilters = () => {
    searchQuery.value = '';
    boardFilter.value = null;
    skillFilter.value = '';
    workloadFilter.value = 'all';
};

const selectedBoardName = computed(
    () =>
        boardFilterOptions.value.find(
            (board) => Number(board.id) === Number(boardFilter.value),
        )?.name ?? null,
);

const emptyStateTitle = computed(() => {
    if (hasAgentFilters.value) {
        return 'No matching agents.';
    }

    return `No ${currentAgentsLabel.value} agents.`;
});

const emptyStateDetail = computed(() => {
    if (! hasAgentFilters.value) {
        return showingArchived.value
            ? 'Archived agents will appear here after you remove them from active rotation.'
            : 'Create an agent to start assigning AI teammates to board work.';
    }

    const parts = [];

    if (searchQuery.value.trim()) {
        parts.push(`search "${searchQuery.value.trim()}"`);
    }

    if (selectedBoardName.value) {
        parts.push(`board ${selectedBoardName.value}`);
    }

    if (skillFilter.value) {
        parts.push(`skill ${skillFilter.value}`);
    }

    if (workloadFilter.value !== 'all') {
        parts.push(`${workloadFilter.value} workload`);
    }

    return parts.length
        ? `No agents match ${parts.join(', ')}.`
        : 'No agents match the current filters.';
});

const removeAgentFromLists = (id) => {
    agents.value = agents.value.filter((agent) => agent.id !== id);
    archivedAgents.value = archivedAgents.value.filter((agent) => agent.id !== id);
};

const upsertAgent = (agent) => {
    removeAgentFromLists(agent.id);

    if (agent.archived_at) {
        archivedAgents.value = sortAgents([...archivedAgents.value, agent]);
        return;
    }

    agents.value = sortAgents([...agents.value, agent]);
};

const resetForm = () => {
    Object.assign(form, blankForm());
    editingAgentId.value = null;
    errors.value = {};
    errorMessage.value = '';
};

const openCreateModal = () => {
    resetForm();
    showingFormModal.value = true;
};

const dismissFormModal = () => {
    showingFormModal.value = false;
    resetForm();
};

const editAgent = (agent) => {
    editingAgentId.value = agent.id;
    Object.assign(form, {
        name: agent.name ?? '',
        email: agent.email ?? '',
        agent_provider_connection_id:
            agent.execution?.provider_connection_id ?? null,
        agent_model: agent.execution?.model ?? '',
        agent_autonomy: agent.execution?.autonomy ?? 'approval',
        agent_title: agent.title ?? '',
        agent_profile: agent.profile ?? '',
        agent_personality: agent.personality ?? '',
        agent_skills: Array.isArray(agent.skills) ? [...agent.skills] : [],
    });
    errors.value = {};
    errorMessage.value = '';
    showingFormModal.value = true;
};

const closeFormModal = () => {
    if (saving.value) {
        return;
    }

    dismissFormModal();
};

const submit = async () => {
    if (saving.value) {
        return;
    }

    saving.value = true;
    errors.value = {};
    errorMessage.value = '';

    try {
        const payload = {
            name: form.name,
            email: form.email,
            agent_provider_connection_id:
                form.agent_provider_connection_id,
            agent_model: form.agent_model,
            agent_autonomy: form.agent_autonomy,
            agent_title: form.agent_title,
            agent_profile: form.agent_profile,
            agent_personality: form.agent_personality,
            agent_skills: form.agent_skills,
        };
        const response = isEditing.value
            ? await axios.patch(
                route('agents.update', { agent: editingAgent.value.id }),
                payload,
            )
            : await axios.post(route('agents.store'), payload);

        if (response?.data?.agent) {
            upsertAgent(response.data.agent);
            showingArchived.value = Boolean(response.data.agent.archived_at);
        }

        dismissFormModal();
    } catch (error) {
        if (error?.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            return;
        }

        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to save agent right now. Please try again.';
    } finally {
        saving.value = false;
    }
};

const clearPendingAgentTimer = () => {
    if (pendingAgentDeletionTimer !== null) {
        window.clearTimeout(pendingAgentDeletionTimer);
        pendingAgentDeletionTimer = null;
    }
};

const flushPendingAgentDeletion = async () => {
    if (!pendingAgentDeletion.value) {
        return;
    }

    const pending = pendingAgentDeletion.value;
    pendingAgentDeletion.value = null;
    clearPendingAgentTimer();

    try {
        await axios.delete(route('agents.destroy', { agent: pending.agentId }));
    } catch (error) {
        agents.value = pending.activeSnapshot;
        archivedAgents.value = pending.archivedSnapshot;
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to delete agent right now. Please try again.';
    }
};

const undoPendingAgentDeletion = () => {
    if (!pendingAgentDeletion.value) {
        return;
    }

    const pending = pendingAgentDeletion.value;
    pendingAgentDeletion.value = null;
    clearPendingAgentTimer();

    agents.value = pending.activeSnapshot;
    archivedAgents.value = pending.archivedSnapshot;
};

const requestDeleteAgent = async (agent) => {
    if (pendingAgentDeletion.value) {
        await flushPendingAgentDeletion();
    }

    errorMessage.value = '';

    pendingAgentDeletion.value = {
        agentId: agent.id,
        name: agent.name,
        activeSnapshot: [...agents.value],
        archivedSnapshot: [...archivedAgents.value],
    };

    removeAgentFromLists(agent.id);

    if (editingAgentId.value === agent.id) {
        closeFormModal();
    }

    pendingAgentDeletionTimer = window.setTimeout(() => {
        flushPendingAgentDeletion();
    }, 5100);
};

const archiveAgent = async (agent) => {
    if (archivingAgentId.value !== null) {
        return;
    }

    if (pendingAgentDeletion.value) {
        await flushPendingAgentDeletion();
    }

    archivingAgentId.value = agent.id;
    errorMessage.value = '';

    try {
        const response = await axios.patch(
            route('agents.archive', { agent: agent.id }),
        );

        if (response?.data?.agent) {
            upsertAgent(response.data.agent);
            showingArchived.value = true;
        }
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to archive agent right now. Please try again.';
    } finally {
        archivingAgentId.value = null;
    }
};

const restoreAgent = async (agent) => {
    if (restoringAgentId.value !== null) {
        return;
    }

    if (pendingAgentDeletion.value) {
        await flushPendingAgentDeletion();
    }

    restoringAgentId.value = agent.id;
    errorMessage.value = '';

    try {
        const response = await axios.patch(
            route('agents.restore', { agent: agent.id }),
        );

        if (response?.data?.agent) {
            upsertAgent(response.data.agent);
            showingArchived.value = false;
        }
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            'Unable to restore agent right now. Please try again.';
    } finally {
        restoringAgentId.value = null;
    }
};

const workingActionFor = (agent) => {
    if (pendingAgentDeletion.value?.agentId === agent.id) {
        return 'delete';
    }

    if (archivingAgentId.value === agent.id) {
        return 'archive';
    }

    if (restoringAgentId.value === agent.id) {
        return 'restore';
    }

    return null;
};
</script>

<template>
    <Head title="Agents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Agents
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Manage AI agents for boards and task assignments.
                    </p>
                </div>
                <PrimaryButton @click="openCreateModal">
                    New Agent
                </PrimaryButton>
            </div>
        </template>

        <div class="min-h-[calc(100vh-9rem)] py-5">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm"
                >
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
                            Active {{ activeAgents.length }}
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
                            Archived {{ archivedAgents.length }}
                        </button>
                    </div>

                    <div class="relative min-w-0 flex-1 basis-full sm:min-w-[14rem] sm:basis-auto xl:flex-[1_1_18rem]">
                        <svg
                            class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M9 3a6 6 0 104.472 10.027l3.25 3.25a1 1 0 001.414-1.414l-3.25-3.25A6 6 0 009 3zm-4 6a4 4 0 118 0 4 4 0 01-8 0z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search agents, skills, or assignments..."
                            class="block w-full rounded-md border-gray-300 py-1.5 pl-8 pr-16 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                            autocomplete="off"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            @click="searchQuery = ''"
                        >
                            Clear
                        </button>
                    </div>

                    <label class="flex items-center gap-2 text-xs">
                        <span class="font-semibold uppercase tracking-wide text-gray-500">
                            Board
                        </span>
                        <select
                            v-model="boardFilter"
                            class="rounded-md border-gray-300 py-1 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500"
                        >
                            <option :value="null">Any board</option>
                            <option
                                v-for="board in boardFilterOptions"
                                :key="board.id"
                                :value="board.id"
                            >
                                {{ board.name }}
                            </option>
                        </select>
                    </label>

                    <label
                        v-if="skillFilterOptions.length"
                        class="flex items-center gap-2 text-xs"
                    >
                        <span class="font-semibold uppercase tracking-wide text-gray-500">
                            Skill
                        </span>
                        <select
                            v-model="skillFilter"
                            class="rounded-md border-gray-300 py-1 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500"
                        >
                            <option value="">Any skill</option>
                            <option
                                v-for="skill in skillFilterOptions"
                                :key="skill"
                                :value="skill"
                            >
                                {{ skill }}
                            </option>
                        </select>
                    </label>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Workload
                        </span>
                        <button
                            v-for="option in workloadFilterOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            :class="
                                workloadFilter === option.value
                                    ? 'border-gray-700 bg-gray-800 text-white'
                                    : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-50'
                            "
                            :aria-pressed="workloadFilter === option.value"
                            @click="workloadFilter = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <label class="flex items-center gap-2 text-xs">
                        <span class="font-semibold uppercase tracking-wide text-gray-500">
                            Sort
                        </span>
                        <select
                            v-model="sortMode"
                            class="rounded-md border-gray-300 py-1 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500"
                        >
                            <option
                                v-for="option in sortOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <span class="text-xs text-gray-500">
                        {{ visibleAgents.length }} of {{ currentAgents.length }} {{ currentAgentsLabel }}
                    </span>

                    <button
                        v-if="hasAgentFilters"
                        type="button"
                        class="rounded-md px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="clearAgentFilters"
                    >
                        Clear
                    </button>

                    <p
                        v-if="errorMessage"
                        class="text-sm font-medium text-rose-600 sm:ml-auto"
                    >
                        {{ errorMessage }}
                    </p>
                </div>

                <div
                    v-if="sortedVisibleAgents.length"
                    class="grid gap-4 pb-6 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <AgentCard
                        v-for="agent in sortedVisibleAgents"
                        :key="agent.id"
                        :agent="agent"
                        :archived="showingArchived"
                        :working-action="workingActionFor(agent)"
                        @edit="editAgent"
                        @archive="archiveAgent"
                        @restore="restoreAgent"
                        @delete="requestDeleteAgent"
                    />
                </div>

                <div
                    v-else
                    class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500"
                >
                    <div class="font-semibold text-gray-700">
                        {{ emptyStateTitle }}
                    </div>
                    <p class="mx-auto mt-2 max-w-md text-xs text-gray-500">
                        {{ emptyStateDetail }}
                    </p>
                    <button
                        v-if="hasAgentFilters"
                        type="button"
                        class="mt-4 rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="clearAgentFilters"
                    >
                        Clear Filters
                    </button>
                    <button
                        v-else-if="!showingArchived"
                        type="button"
                        class="mt-4 rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="openCreateModal"
                    >
                        Create Agent
                    </button>
                </div>
            </div>
        </div>

        <AgentFormModal
            v-model:name="form.name"
            v-model:email="form.email"
            v-model:provider-connection-id="form.agent_provider_connection_id"
            v-model:model="form.agent_model"
            v-model:autonomy="form.agent_autonomy"
            v-model:title="form.agent_title"
            v-model:profile="form.agent_profile"
            v-model:personality="form.agent_personality"
            v-model:skills="form.agent_skills"
            :show="showingFormModal"
            :provider-connections="providerConnections"
            :is-editing="isEditing"
            :saving="saving"
            :errors="errors"
            :error-message="errorMessage"
            @close="closeFormModal"
            @submit="submit"
        />

        <UndoToast
            :show="pendingAgentDeletion !== null"
            :message="`Agent ${pendingAgentDeletion?.name ?? ''} deleted`"
            @undo="undoPendingAgentDeletion"
            @expire="flushPendingAgentDeletion"
        />
    </AuthenticatedLayout>
</template>
