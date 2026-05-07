<script setup>
import { computed } from 'vue';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    max: {
        type: Number,
        default: 3,
    },
});

const initials = (name = '') =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || '?';

const visible = computed(() => props.users.slice(0, props.max));
const overflow = computed(() => Math.max(props.users.length - props.max, 0));
</script>

<template>
    <div class="flex -space-x-1.5">
        <span
            v-for="user in visible"
            :key="user.id"
            class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-indigo-100 text-[10px] font-semibold text-indigo-700"
            :title="user.name"
        >
            {{ initials(user.name) }}
        </span>
        <span
            v-if="overflow"
            class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-gray-200 text-[10px] font-semibold text-gray-600"
            :title="`+${overflow} more`"
        >
            +{{ overflow }}
        </span>
    </div>
</template>
