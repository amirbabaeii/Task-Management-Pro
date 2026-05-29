<script setup>
import { computed } from 'vue';

const props = defineProps({
    members: {
        type: Array,
        default: () => [],
    },
});

const selectedIds = defineModel('selectedIds', {
    type: Array,
    default: () => [],
});

const isSelected = (id) => selectedIds.value.includes(id);

const isArchivedAgent = (member) =>
    Boolean(
        member.is_archived_agent || (member.is_agent && member.agent_archived_at),
    );

const canToggle = (member) => ! isArchivedAgent(member) || isSelected(member.id);

const toggle = (member) => {
    if (! canToggle(member)) {
        return;
    }

    const id = member.id;
    const next = [...selectedIds.value];
    const index = next.indexOf(id);
    if (index === -1) {
        next.push(id);
    } else {
        next.splice(index, 1);
    }
    selectedIds.value = next;
};

const initials = (name = '') =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || '?';

const sortedMembers = computed(() =>
    [...props.members].sort((a, b) => {
        if (a.role === 'owner' && b.role !== 'owner') return -1;
        if (b.role === 'owner' && a.role !== 'owner') return 1;
        return a.name.localeCompare(b.name);
    }),
);

const selectedCount = computed(() => selectedIds.value.length);
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between">
            <span class="text-xs text-gray-500">
                {{ selectedCount }} of {{ members.length }} selected
            </span>
        </div>
        <div
            v-if="members.length === 0"
            class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-4 text-center text-xs text-gray-500"
        >
            No members on this board yet.
        </div>
        <ul
            v-else
            class="max-h-48 overflow-y-auto rounded-md border border-gray-200 bg-white"
        >
            <li
                v-for="member in sortedMembers"
                :key="member.id"
                class="flex items-center gap-3 border-b border-gray-100 px-3 py-2 transition last:border-b-0"
                :class="[
                    isSelected(member.id) ? 'bg-indigo-50' : '',
                    canToggle(member)
                        ? 'cursor-pointer hover:bg-gray-50'
                        : 'cursor-not-allowed opacity-60',
                ]"
                role="checkbox"
                :aria-checked="isSelected(member.id)"
                :aria-disabled="! canToggle(member)"
                :tabindex="canToggle(member) ? 0 : -1"
                @click="toggle(member)"
                @keydown.enter.prevent="toggle(member)"
                @keydown.space.prevent="toggle(member)"
            >
                <div
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                    :class="
                        isArchivedAgent(member)
                            ? 'bg-gray-100 text-gray-500'
                            : 'bg-indigo-100 text-indigo-700'
                    "
                >
                    {{ initials(member.name) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium text-gray-900">
                        {{ member.name }}
                    </div>
                    <div class="truncate text-xs text-gray-500">
                        {{ member.email }}
                    </div>
                </div>
                <span
                    v-if="isArchivedAgent(member)"
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500"
                >
                    Archived
                </span>
                <span
                    v-else-if="member.role === 'owner'"
                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700"
                >
                    Owner
                </span>
                <span
                    v-else-if="member.is_agent"
                    class="rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal-700"
                >
                    Agent
                </span>
                <span
                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition"
                    :class="
                        isSelected(member.id)
                            ? 'border-indigo-600 bg-indigo-600 text-white'
                            : 'border-gray-300 bg-white'
                    "
                    aria-hidden="true"
                >
                    <svg
                        v-if="isSelected(member.id)"
                        class="h-3 w-3"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 011.42-1.42l2.79 2.79 6.79-6.79a1 1 0 011.42 0z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </span>
            </li>
        </ul>
    </div>
</template>
