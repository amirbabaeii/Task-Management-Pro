<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    message: {
        type: String,
        default: '',
    },
    durationMs: {
        type: Number,
        default: 5000,
    },
});

const emit = defineEmits(['undo', 'expire']);

const elapsed = ref(0);
let intervalId = null;
let startedAt = 0;

const tick = () => {
    elapsed.value = Math.min(props.durationMs, Date.now() - startedAt);

    if (elapsed.value >= props.durationMs) {
        stop();
        emit('expire');
    }
};

const start = () => {
    stop();
    startedAt = Date.now();
    elapsed.value = 0;
    intervalId = window.setInterval(tick, 50);
};

const stop = () => {
    if (intervalId !== null) {
        window.clearInterval(intervalId);
        intervalId = null;
    }
};

const remainingSeconds = computed(() =>
    Math.max(0, Math.ceil((props.durationMs - elapsed.value) / 1000)),
);

const progress = computed(() =>
    Math.min(100, (elapsed.value / props.durationMs) * 100),
);

watch(
    () => props.show,
    (next) => {
        if (next) {
            start();
        } else {
            stop();
        }
    },
);

onMounted(() => {
    if (props.show) {
        start();
    }
});

onBeforeUnmount(() => {
    stop();
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="show"
            class="pointer-events-auto fixed bottom-6 left-1/2 z-50 w-full max-w-sm -translate-x-1/2 px-4"
            role="status"
            aria-live="polite"
        >
            <div
                class="overflow-hidden rounded-lg bg-gray-900 text-white shadow-lg ring-1 ring-black/5"
            >
                <div class="flex items-center gap-4 px-4 py-3">
                    <span class="text-sm">{{ message }}</span>
                    <span class="text-xs text-gray-400">
                        {{ remainingSeconds }}s
                    </span>
                    <button
                        type="button"
                        class="ml-auto rounded-md px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-300 transition hover:bg-white/10 hover:text-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-gray-900"
                        @click="$emit('undo')"
                    >
                        Undo
                    </button>
                </div>
                <div class="h-0.5 bg-gray-800">
                    <div
                        class="h-full bg-indigo-400 transition-[width] duration-75 ease-linear"
                        :style="{ width: `${100 - progress}%` }"
                    />
                </div>
            </div>
        </div>
    </Transition>
</template>
