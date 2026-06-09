<script setup>
import { formatPriority, priorityBadgeClass } from '@/lib/format';
import { computed } from 'vue';

const props = defineProps({
    priorities: {
        type: Array,
        default: () => [],
    },
    activePriorities: {
        type: Array,
        default: () => [],
    },
    members: {
        type: Array,
        default: () => [],
    },
    currentUserId: {
        type: Number,
        default: null,
    },
    hasActiveFilters: {
        type: Boolean,
        default: false,
    },
    matchedCount: {
        type: Number,
        default: 0,
    },
    totalCount: {
        type: Number,
        default: 0,
    },
    canSaveFilters: {
        type: Boolean,
        default: false,
    },
    hasSavedFilterChanges: {
        type: Boolean,
        default: false,
    },
    savingFilters: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'toggle-priority',
    'clear',
    'save-filters',
    'reset-saved-filters',
]);

const searchQuery = defineModel('searchQuery', {
    type: String,
    default: '',
});

const assigneeFilter = defineModel('assigneeFilter', {
    type: Number,
    default: null,
});

const deadlineFilter = defineModel('deadlineFilter', {
    type: String,
    default: 'all',
});

const deadlineOptions = [
    { value: 'all', label: 'All' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'today', label: 'Today' },
    { value: 'upcoming', label: 'Next 7' },
    { value: 'none', label: 'No date' },
];
const deadlineLabel = computed(
    () =>
        deadlineOptions.find((option) => option.value === deadlineFilter.value)
            ?.label ?? null,
);

// "Me" appears first when the current user is on the board.
const assigneeOptions = computed(() => {
    const sorted = [...props.members].sort((a, b) => {
        if (a.id === props.currentUserId) return -1;
        if (b.id === props.currentUserId) return 1;
        if (a.role === 'owner' && b.role !== 'owner') return -1;
        if (b.role === 'owner' && a.role !== 'owner') return 1;
        return a.name.localeCompare(b.name);
    });
    return sorted;
});

const assigneeLabel = computed(() => {
    if (assigneeFilter.value === null) {
        return null;
    }

    const assignee = assigneeOptions.value.find(
        (member) => Number(member.id) === Number(assigneeFilter.value),
    );

    if (!assignee) {
        return null;
    }

    return assignee.id === props.currentUserId ? 'Me' : assignee.name;
});

const resultSummary = computed(() => {
    const totalLabel = props.totalCount === 1 ? 'task' : 'tasks';

    return `Showing ${props.matchedCount} of ${props.totalCount} ${totalLabel}`;
});
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm"
    >
        <slot name="leading" />

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
                placeholder="Search tasks by title, description, or tag..."
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

        <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                Priority
            </span>
            <button
                v-for="priority in priorities"
                :key="priority"
                type="button"
                class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                :class="
                    activePriorities.includes(priority)
                        ? priorityBadgeClass(priority)
                        : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-50'
                "
                :aria-pressed="activePriorities.includes(priority)"
                @click="emit('toggle-priority', priority)"
            >
                {{ formatPriority(priority) }}
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                Due
            </span>
            <button
                v-for="option in deadlineOptions"
                :key="option.value"
                type="button"
                class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                :class="
                    deadlineFilter === option.value
                        ? 'border-gray-700 bg-gray-800 text-white'
                        : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-50'
                "
                :aria-pressed="deadlineFilter === option.value"
                @click="deadlineFilter = option.value"
            >
                {{ option.label }}
            </button>
        </div>

        <label class="flex items-center gap-2 text-xs">
            <span class="font-semibold uppercase tracking-wide text-gray-500">
                Assignee
            </span>
            <button
                v-if="currentUserId"
                type="button"
                class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                :class="
                    assigneeFilter === currentUserId
                        ? 'border-gray-700 bg-gray-800 text-white'
                        : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-50'
                "
                :aria-pressed="assigneeFilter === currentUserId"
                @click="
                    assigneeFilter =
                        assigneeFilter === currentUserId ? null : currentUserId
                "
            >
                Mine
            </button>
            <select
                v-model="assigneeFilter"
                class="rounded-md border-gray-300 py-1 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500"
            >
                <option :value="null">Anyone</option>
                <option
                    v-for="member in assigneeOptions"
                    :key="member.id"
                    :value="member.id"
                >
                    {{
                        member.id === currentUserId
                            ? `Me (${member.name})`
                            : member.name
                    }}
                </option>
            </select>
        </label>

        <div class="flex w-full items-center gap-3 sm:ml-auto sm:w-auto">
            <span
                v-if="hasActiveFilters"
                class="text-xs text-gray-500"
            >
                {{ resultSummary }}
            </span>
            <button
                v-if="hasActiveFilters"
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="emit('clear')"
            >
                Clear
            </button>
            <button
                v-if="canSaveFilters"
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-semibold uppercase tracking-wide transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                :class="
                    hasSavedFilterChanges
                        ? 'bg-gray-800 text-white hover:bg-gray-700'
                        : 'text-gray-400'
                "
                :disabled="savingFilters || !hasSavedFilterChanges"
                @click="emit('save-filters')"
            >
                {{ savingFilters ? 'Saving...' : 'Save filters' }}
            </button>
            <button
                v-if="canSaveFilters"
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:text-gray-300"
                :disabled="savingFilters"
                @click="emit('reset-saved-filters')"
            >
                Reset
            </button>
        </div>

        <div
            v-if="hasActiveFilters"
            class="flex basis-full flex-wrap items-center gap-1.5 border-t border-gray-100 pt-2 text-[11px]"
        >
            <span class="font-semibold uppercase tracking-wide text-gray-400">
                Filters
            </span>
            <button
                v-if="searchQuery"
                type="button"
                class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 font-medium text-gray-600 transition hover:bg-white hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                @click="searchQuery = ''"
            >
                Search: {{ searchQuery }}
            </button>
            <button
                v-for="priority in activePriorities"
                :key="`chip-priority-${priority}`"
                type="button"
                class="rounded-full border px-2 py-0.5 font-medium transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                :class="priorityBadgeClass(priority)"
                @click="emit('toggle-priority', priority)"
            >
                Priority: {{ formatPriority(priority) }}
            </button>
            <button
                v-if="deadlineFilter !== 'all' && deadlineLabel"
                type="button"
                class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 font-medium text-gray-600 transition hover:bg-white hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                @click="deadlineFilter = 'all'"
            >
                Due: {{ deadlineLabel }}
            </button>
            <button
                v-if="assigneeLabel"
                type="button"
                class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 font-medium text-gray-600 transition hover:bg-white hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                @click="assigneeFilter = null"
            >
                Assignee: {{ assigneeLabel }}
            </button>
        </div>
    </div>
</template>
