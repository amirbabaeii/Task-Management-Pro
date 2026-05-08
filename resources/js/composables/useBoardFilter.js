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
export function useBoardFilter(tasks) {
    const searchQuery = ref('');
    const priorityFilter = ref([]);
    const assigneeFilter = ref(null);
    const deadlineFilter = ref('all');

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
        searchQuery.value = '';
        priorityFilter.value = [];
        assigneeFilter.value = null;
        deadlineFilter.value = 'all';
    };

    return {
        searchQuery,
        priorityFilter,
        assigneeFilter,
        deadlineFilter,
        filteredTasks,
        hasActiveFilters,
        togglePriority,
        clearFilters,
    };
}

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
