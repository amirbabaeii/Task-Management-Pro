<script setup>
import { computed } from 'vue';

const props = defineProps({
    agent: {
        type: Object,
        required: true,
    },
    archived: {
        type: Boolean,
        default: false,
    },
    workingAction: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['edit', 'archive', 'restore', 'delete']);

const visibleSkills = computed(() => (props.agent.skills ?? []).slice(0, 5));
const hiddenSkillCount = computed(() =>
    Math.max(0, (props.agent.skills?.length ?? 0) - visibleSkills.value.length),
);
const workloadCount = (key) => Number(props.agent.workload?.[key] ?? 0);
</script>

<template>
    <article
        class="rounded-md border border-gray-200 bg-gray-50 p-4 shadow-sm transition"
        :class="{ 'border-gray-300 bg-white': archived }"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="truncate text-sm font-semibold text-gray-900">
                    {{ agent.name }}
                </h3>
                <p class="mt-1 truncate text-xs text-gray-500">
                    {{ agent.title || 'AI agent' }} - {{ agent.email }}
                </p>
            </div>
            <span
                class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                :class="
                    archived
                        ? 'border-gray-200 bg-gray-100 text-gray-600'
                        : 'border-teal-200 bg-teal-50 text-teal-700'
                "
            >
                {{ archived ? 'Archived' : 'Agent' }}
            </span>
        </div>

        <p class="mt-3 text-sm leading-6 text-gray-600">
            {{ agent.profile || 'No profile yet.' }}
        </p>

        <div class="mt-3 grid grid-cols-3 divide-x divide-gray-200 border-y border-gray-200 py-2 text-center">
            <div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ workloadCount('boards') }}
                </div>
                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                    Boards
                </div>
            </div>
            <div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ workloadCount('active_tasks') }}
                </div>
                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                    Active
                </div>
            </div>
            <div>
                <div
                    class="text-sm font-semibold"
                    :class="
                        workloadCount('overdue_tasks') > 0
                            ? 'text-rose-600'
                            : 'text-gray-900'
                    "
                >
                    {{ workloadCount('overdue_tasks') }}
                </div>
                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                    Overdue
                </div>
            </div>
        </div>

        <div
            v-if="agent.personality"
            class="mt-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs leading-5 text-gray-600"
        >
            <span class="font-semibold uppercase tracking-wide text-gray-500">
                Personality
            </span>
            <span class="ml-1">{{ agent.personality }}</span>
        </div>

        <div
            v-if="visibleSkills.length"
            class="mt-3 flex flex-wrap gap-1.5"
        >
            <span
                v-for="skill in visibleSkills"
                :key="`${agent.id}-${skill}`"
                class="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700"
            >
                {{ skill }}
            </span>
            <span
                v-if="hiddenSkillCount"
                class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500"
            >
                +{{ hiddenSkillCount }}
            </span>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            <button
                type="button"
                class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                :disabled="workingAction !== null"
                @click="emit('edit', agent)"
            >
                Edit
            </button>
            <button
                v-if="archived"
                type="button"
                class="rounded-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-gray-600 transition hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-25"
                :disabled="workingAction !== null"
                @click="emit('restore', agent)"
            >
                {{ workingAction === 'restore' ? 'Restoring...' : 'Restore' }}
            </button>
            <button
                v-else
                type="button"
                class="rounded-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-gray-600 transition hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-25"
                :disabled="workingAction !== null"
                @click="emit('archive', agent)"
            >
                {{ workingAction === 'archive' ? 'Archiving...' : 'Archive' }}
            </button>
            <button
                type="button"
                class="rounded-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:opacity-25"
                :disabled="workingAction !== null"
                @click="emit('delete', agent)"
            >
                {{ workingAction === 'delete' ? 'Deleting...' : 'Delete' }}
            </button>
        </div>
    </article>
</template>
