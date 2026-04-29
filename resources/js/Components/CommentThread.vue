<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatDateTime } from '@/lib/format';

defineProps({
    comment: {
        type: Object,
        required: true,
    },
    activeReplyCommentId: {
        type: Number,
        default: null,
    },
    replyErrors: {
        type: Object,
        default: () => ({}),
    },
    replyingCommentId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['start-reply', 'cancel-reply', 'submit-reply']);

const replyDraft = defineModel('replyDraft', {
    type: String,
    default: '',
});
</script>

<template>
    <article
        class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm font-semibold text-gray-800">
                {{ comment.user.name }}
            </div>
            <div class="text-xs text-gray-500">
                {{ formatDateTime(comment.created_at) || 'Just now' }}
            </div>
        </div>
        <p
            class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-gray-600"
        >
            {{ comment.content }}
        </p>
        <div class="mt-3 flex items-center justify-end">
            <button
                type="button"
                class="rounded-md px-2 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="emit('start-reply', comment)"
            >
                {{
                    activeReplyCommentId === comment.id
                        ? 'Cancel Reply'
                        : 'Reply'
                }}
            </button>
        </div>

        <form
            v-if="activeReplyCommentId === comment.id"
            class="mt-3 space-y-3 border-t border-gray-200 pt-3"
            @submit.prevent="emit('submit-reply', comment)"
        >
            <textarea
                v-model="replyDraft"
                rows="3"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                :placeholder="`Reply to ${comment.user.name}...`"
            />
            <InputError :message="replyErrors.content?.[0]" />
            <div class="flex justify-end gap-3">
                <SecondaryButton @click="emit('cancel-reply')">
                    Cancel
                </SecondaryButton>
                <PrimaryButton
                    :class="{
                        'opacity-25':
                            replyingCommentId === comment.id ||
                            !replyDraft.trim(),
                    }"
                    :disabled="
                        replyingCommentId === comment.id ||
                        !replyDraft.trim()
                    "
                >
                    {{
                        replyingCommentId === comment.id
                            ? 'Posting...'
                            : 'Post Reply'
                    }}
                </PrimaryButton>
            </div>
        </form>

        <div
            v-if="comment.replies.length"
            class="mt-4 space-y-3 border-l-2 border-gray-200 pl-4"
        >
            <article
                v-for="reply in comment.replies"
                :key="reply.id"
                class="rounded-lg border border-gray-200 bg-white px-4 py-3"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <div class="text-sm font-semibold text-gray-800">
                        {{ reply.user.name }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ formatDateTime(reply.created_at) || 'Just now' }}
                    </div>
                </div>
                <p
                    class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-gray-600"
                >
                    {{ reply.content }}
                </p>
            </article>
        </div>
    </article>
</template>
