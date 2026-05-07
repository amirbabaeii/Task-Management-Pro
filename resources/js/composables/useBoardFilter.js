import { computed, ref } from 'vue';

/**
 * Reactive client-side filter for the board's task list.
 *
 * Search matches title / description / tags. Priority is multi-select
 * (any-of); empty array means "all". Assignee is single-select; null
 * means "anyone".
 *
 * @param {import('vue').Ref<Array>} tasks  Raw task list (unfiltered).
 */
export function useBoardFilter(tasks) {
    const searchQuery = ref('');
    const priorityFilter = ref([]);
    const assigneeFilter = ref(null);

    const hasActiveFilters = computed(
        () =>
            searchQuery.value.trim() !== '' ||
            priorityFilter.value.length > 0 ||
            assigneeFilter.value !== null,
    );

    const filteredTasks = computed(() => {
        const query = searchQuery.value.trim().toLowerCase();
        const priorities = priorityFilter.value;
        const assigneeId = assigneeFilter.value;

        if (query === '' && priorities.length === 0 && assigneeId === null) {
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
    };

    return {
        searchQuery,
        priorityFilter,
        assigneeFilter,
        filteredTasks,
        hasActiveFilters,
        togglePriority,
        clearFilters,
    };
}
