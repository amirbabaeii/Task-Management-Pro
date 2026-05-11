import { computed, ref } from 'vue';
import { deadlineState } from '@/lib/format';

/**
 * Reactive client-side filter for the board's task list.
 *
 * Search matches title / description / tags. Priority is multi-select
 * (any-of); empty array means "all". Assignee is single-select; null
 * means "anyone". Deadline is a single-select date window.
 *
 * @param {import('vue').Ref<Array>} tasks  Raw task list (unfiltered).
 */
export function useBoardFilter(tasks, initialFilters = {}) {
    const initial = normalizeBoardFilterPreferences(initialFilters);
    const searchQuery = ref(initial.search);
    const priorityFilter = ref([...initial.priorities]);
    const assigneeFilter = ref(initial.assignee_id);
    const deadlineFilter = ref(initial.deadline);

    const hasActiveFilters = computed(
        () =>
            searchQuery.value.trim() !== '' ||
            priorityFilter.value.length > 0 ||
            assigneeFilter.value !== null ||
            deadlineFilter.value !== 'all',
    );

    const filteredTasks = computed(() => {
        const query = searchQuery.value.trim().toLowerCase();
        const priorities = priorityFilter.value;
        const assigneeId = assigneeFilter.value;
        const deadline = deadlineFilter.value;

        if (
            query === '' &&
            priorities.length === 0 &&
            assigneeId === null &&
            deadline === 'all'
        ) {
            return tasks.value;
        }

        return tasks.value.filter((task) => {
            if (priorities.length > 0 && ! priorities.includes(task.priority)) {
                return false;
            }

            if (assigneeId !== null) {
                const assignees = Array.isArray(task.assignees)
                    ? task.assignees
                    : [];
                if (! assignees.some((assignee) => assignee.id === assigneeId)) {
                    return false;
                }
            }

            if (! matchesDeadline(task.deadline_at, deadline)) {
                return false;
            }

            if (query !== '') {
                const haystack = [
                    task.title ?? '',
                    task.description ?? '',
                    ...(Array.isArray(task.tags) ? task.tags : []),
                ]
                    .join(' ')
                    .toLowerCase();

                if (! haystack.includes(query)) {
                    return false;
                }
            }

            return true;
        });
    });

    const togglePriority = (priority) => {
        const next = [...priorityFilter.value];
        const index = next.indexOf(priority);

        if (index === -1) {
            next.push(priority);
        } else {
            next.splice(index, 1);
        }

        priorityFilter.value = next;
    };

    const clearFilters = () => {
        setFilters(defaultBoardFilterPreferences());
    };

    const setFilters = (filters = {}) => {
        const normalized = normalizeBoardFilterPreferences(filters);

        searchQuery.value = normalized.search;
        priorityFilter.value = [...normalized.priorities];
        assigneeFilter.value = normalized.assignee_id;
        deadlineFilter.value = normalized.deadline;
    };

    const currentFilters = computed(() => ({
        search: searchQuery.value.trim(),
        priorities: [...priorityFilter.value],
        assignee_id: assigneeFilter.value,
        deadline: deadlineFilter.value,
    }));

    return {
        searchQuery,
        priorityFilter,
        assigneeFilter,
        deadlineFilter,
        currentFilters,
        filteredTasks,
        hasActiveFilters,
        togglePriority,
        clearFilters,
        setFilters,
    };
}

export const defaultBoardFilterPreferences = () => ({
    search: '',
    priorities: [],
    assignee_id: null,
    deadline: 'all',
    view: 'active',
});

export const normalizeBoardFilterPreferences = (filters = {}) => {
    const defaults = defaultBoardFilterPreferences();
    const deadline = ['all', 'overdue', 'today', 'upcoming', 'none'].includes(
        filters?.deadline,
    )
        ? filters.deadline
        : defaults.deadline;
    const view = ['active', 'archived'].includes(filters?.view)
        ? filters.view
        : defaults.view;
    const assigneeId =
        filters?.assignee_id === null || filters?.assignee_id === undefined
            ? null
            : Number(filters.assignee_id);

    return {
        search:
            typeof filters?.search === 'string'
                ? filters.search.trim().slice(0, 150)
                : defaults.search,
        priorities: Array.isArray(filters?.priorities)
            ? [...new Set(filters.priorities.map(String))]
            : [],
        assignee_id: Number.isFinite(assigneeId) ? assigneeId : null,
        deadline,
        view,
    };
};

const matchesDeadline = (value, filter) => {
    if (filter === 'all') {
        return true;
    }

    const state = deadlineState(value);

    if (filter === 'upcoming') {
        return state === 'soon';
    }

    if (filter === 'none') {
        return state === 'none';
    }

    return state === filter;
};
