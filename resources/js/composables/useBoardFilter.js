import { computed, ref } from 'vue';

/**
 * Reactive client-side filter for the board's task list.
 *
 * Search matches title / description / tags. Priority is multi-select
 * (any-of); empty array means "all".
 *
 * @param {import('vue').Ref<Array>} tasks  Raw task list (unfiltered).
 */
export function useBoardFilter(tasks) {
    const searchQuery = ref('');
    const priorityFilter = ref([]);

    const hasActiveFilters = computed(
        () =>
            searchQuery.value.trim() !== '' ||
            priorityFilter.value.length > 0,
    );

    const filteredTasks = computed(() => {
        const query = searchQuery.value.trim().toLowerCase();
        const priorities = priorityFilter.value;

        if (query === '' && priorities.length === 0) {
            return tasks.value;
        }

        return tasks.value.filter((task) => {
            if (priorities.length > 0 && ! priorities.includes(task.priority)) {
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
    };

    return {
        searchQuery,
        priorityFilter,
        filteredTasks,
        hasActiveFilters,
        togglePriority,
        clearFilters,
    };
}
