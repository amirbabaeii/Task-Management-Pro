<script setup>
import { computed, ref } from 'vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    placeholder: {
        type: String,
        default: 'Add a tag',
    },
    maxTags: {
        type: Number,
        default: 10,
    },
    maxTagLength: {
        type: Number,
        default: 30,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const model = defineModel({
    type: Array,
    default: () => [],
});

const draft = ref('');
const localError = ref('');

const tags = computed(() => (Array.isArray(model.value) ? model.value : []));
const remainingSlots = computed(() => Math.max(props.maxTags - tags.value.length, 0));
const canAddMore = computed(
    () => !props.disabled && tags.value.length < props.maxTags,
);
const combinedError = computed(() => localError.value || props.error);
const helperText = computed(() =>
    canAddMore.value
        ? `Press Enter or click Add. ${remainingSlots.value} ${
              remainingSlots.value === 1 ? 'slot' : 'slots'
          } left.`
        : `Tag limit reached (${props.maxTags}). Remove one to add another.`,
);

const normalizeTag = (value) =>
    `${value ?? ''}`.replace(/\s+/g, ' ').trim();

const splitDraftTags = (value) =>
    `${value ?? ''}`
        .split(/[\r\n,]+/)
        .map(normalizeTag)
        .filter(Boolean);

const clearLocalError = () => {
    localError.value = '';
};

const addTags = () => {
    if (props.disabled) {
        return;
    }

    const nextDraftTags = splitDraftTags(draft.value);

    if (!nextDraftTags.length) {
        clearLocalError();
        draft.value = '';
        return;
    }

    const tooLongTag = nextDraftTags.find(
        (tag) => tag.length > props.maxTagLength,
    );

    if (tooLongTag) {
        localError.value = `Each tag must be ${props.maxTagLength} characters or fewer.`;
        return;
    }

    const existingTags = new Set(tags.value.map((tag) => tag.toLowerCase()));
    const uniqueIncomingTags = [];

    nextDraftTags.forEach((tag) => {
        const normalizedTag = tag.toLowerCase();

        if (existingTags.has(normalizedTag)) {
            return;
        }

        existingTags.add(normalizedTag);
        uniqueIncomingTags.push(tag);
    });

    if (!uniqueIncomingTags.length) {
        localError.value = 'That tag is already added.';
        return;
    }

    if (tags.value.length + uniqueIncomingTags.length > props.maxTags) {
        localError.value = `You can add up to ${props.maxTags} tags.`;
        return;
    }

    model.value = [...tags.value, ...uniqueIncomingTags];
    draft.value = '';
    clearLocalError();
};

const removeTag = (tagToRemove) => {
    model.value = tags.value.filter((tag) => tag !== tagToRemove);
    clearLocalError();
};

const handleKeydown = (event) => {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addTags();
    }
};
</script>

<template>
    <div class="mt-1 space-y-3">
        <div
            class="flex min-h-24 flex-wrap gap-2 rounded-lg border border-gray-300 bg-white p-3"
        >
            <span
                v-for="tag in tags"
                :key="tag"
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700"
            >
                {{ tag }}
                <button
                    type="button"
                    class="inline-flex h-5 w-5 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-200 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-gray-400"
                    :aria-label="`Remove ${tag} tag`"
                    @click="removeTag(tag)"
                >
                    <svg
                        class="h-3 w-3"
                        viewBox="0 0 12 12"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    >
                        <path d="M2 2l8 8" />
                        <path d="M10 2L2 10" />
                    </svg>
                </button>
            </span>

            <span
                v-if="!tags.length"
                class="self-center text-sm text-gray-400"
            >
                No tags yet.
            </span>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            <input
                :id="id"
                v-model="draft"
                type="text"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500 disabled:bg-gray-100 disabled:text-gray-500"
                :placeholder="placeholder"
                :disabled="!canAddMore"
                autocomplete="off"
                @input="clearLocalError"
                @keydown="handleKeydown"
            />
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canAddMore"
                @click="addTags"
            >
                Add
            </button>
        </div>

        <p class="text-xs text-gray-500">
            {{ helperText }}
        </p>

        <InputError
            :message="combinedError"
        />
    </div>
</template>
