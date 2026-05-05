<script setup>
import { formatPriority, priorityBadgeClass } from '@/lib/format';

defineProps({
    priorities: {
        type: Array,
        default: () => [],
    },
    activePriorities: {
        type: Array,
        default: () => [],
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
});

const emit = defineEmits(['toggle-priority', 'clear']);

const searchQuery = defineModel('searchQuery', {
    type: String,
    default: '',
});
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm"
    >
        <div class="relative min-w-[14rem] flex-1">
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
                class="block w-full rounded-md border-gray-300 pl-8 pr-3 py-1.5 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                autocomplete="off"
            />
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

        <div class="ml-auto flex items-center gap-3">
            <span
                v-if="hasActiveFilters"
                class="text-xs text-gray-500"
            >
                {{ matchedCount }} of {{ totalCount }}
            </span>
            <button
                v-if="hasActiveFilters"
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="emit('clear')"
            >
                Clear filters
            </button>
        </div>
    </div>
</template>
