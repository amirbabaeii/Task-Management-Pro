<script setup>
import CommentThread from '@/Components/CommentThread.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    formatDate,
    formatDateTime,
    formatPriority,
    priorityBadgeClass,
} from '@/lib/format';
import { commentCount } from '@/lib/task';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    task: {
        type: Object,
        default: null,
    },
    formatStatus: {
        type: Function,
        required: true,
    },
    commentErrors: {
        type: Object,
        default: () => ({}),
    },
    submittingComment: {
        type: Boolean,
        default: false,
    },
    replyErrors: {
        type: Object,
        default: () => ({}),
    },
    activeReplyCommentId: {
        type: Number,
        default: null,
    },
    replyingCommentId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits([
    'close',
    'open-edit',
    'submit-comment',
    'start-reply',
    'cancel-reply',
    'submit-reply',
]);

const commentDraft = defineModel('commentDraft', {
    type: String,
    default: '',
});

const replyDraft = defineModel('replyDraft', {
    type: String,
    default: '',
});
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div v-if="task" class="p-6">
            <div
                class="flex flex-col gap-4 border-b border-gray-100 pb-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ task.title }}
                    </h3>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span
                            class="rounded-full bg-gray-100 px-2.5 py-1 font-semibold uppercase tracking-wide text-gray-700"
                        >
                            {{ formatStatus(task.status) }}
                        </span>
                        <span
                            class="rounded-full border px-2.5 py-1 font-semibold uppercase tracking-wide"
                            :class="priorityBadgeClass(task.priority)"
                        >
                            {{ formatPriority(task.priority) }}
                        </span>
                    </div>
                </div>
                <SecondaryButton @click="emit('open-edit')">
                    Edit Task
                </SecondaryButton>
            </div>

            <div class="mt-6 space-y-6">
                <section class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 px-4 py-3">
                        <div
                            class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            Due Date
                        </div>
                        <div class="mt-1 text-sm text-gray-700">
                            {{ formatDate(task.deadline_at) || 'No deadline set' }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3">
                        <div
                            class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            Created At
                        </div>
                        <div class="mt-1 text-sm text-gray-700">
                            {{ formatDateTime(task.created_at) || 'Unavailable' }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-3">
                        <div
                            class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            Task ID
                        </div>
                        <div class="mt-1 text-sm text-gray-700">
                            #{{ task.id }}
                        </div>
                    </div>
                </section>

                <section class="space-y-2">
                    <h4 class="text-sm font-semibold text-gray-900">
                        Description
                    </h4>
                    <p class="text-sm leading-6 text-gray-600">
                        {{ task.description || 'No description provided.' }}
                    </p>
                </section>

                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-900">
                            Tags
                        </h4>
                        <span class="text-xs text-gray-500">
                            {{ task.tags.length }}
                            {{ task.tags.length === 1 ? 'tag' : 'tags' }}
                        </span>
                    </div>
                    <div
                        v-if="task.tags.length"
                        class="flex flex-wrap gap-2"
                    >
                        <span
                            v-for="tag in task.tags"
                            :key="`${task.id}-tag-${tag}`"
                            class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-sky-700"
                        >
                            {{ tag }}
                        </span>
                    </div>
                    <p v-else class="text-sm text-gray-500">
                        No tags added.
                    </p>
                </section>

                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-900">
                            Progress
                        </h4>
                        <span class="text-sm text-gray-500">
                            {{ task.progress }}%
                        </span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-gray-200">
                        <div
                            class="h-2 rounded-full bg-gray-800"
                            :style="{ width: `${task.progress}%` }"
                        />
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-900">
                            Comments
                        </h4>
                        <span class="text-xs text-gray-500">
                            {{ commentCount(task.comments) }}
                            {{
                                commentCount(task.comments) === 1
                                    ? 'comment'
                                    : 'comments'
                            }}
                        </span>
                    </div>

                    <form
                        class="space-y-3"
                        @submit.prevent="emit('submit-comment')"
                    >
                        <textarea
                            v-model="commentDraft"
                            rows="3"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                            placeholder="Add a comment..."
                        />
                        <InputError :message="commentErrors.content?.[0]" />
                        <div class="flex justify-end">
                            <PrimaryButton
                                :class="{
                                    'opacity-25':
                                        submittingComment ||
                                        !commentDraft.trim(),
                                }"
                                :disabled="
                                    submittingComment || !commentDraft.trim()
                                "
                            >
                                {{
                                    submittingComment
                                        ? 'Posting...'
                                        : 'Post Comment'
                                }}
                            </PrimaryButton>
                        </div>
                    </form>

                    <div
                        v-if="task.comments.length"
                        class="space-y-3"
                    >
                        <CommentThread
                            v-for="comment in task.comments"
                            :key="comment.id"
                            v-model:reply-draft="replyDraft"
                            :comment="comment"
                            :active-reply-comment-id="activeReplyCommentId"
                            :reply-errors="replyErrors"
                            :replying-comment-id="replyingCommentId"
                            @start-reply="(c) => emit('start-reply', c)"
                            @cancel-reply="emit('cancel-reply')"
                            @submit-reply="(c) => emit('submit-reply', c)"
                        />
                    </div>
                    <div
                        v-else
                        class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-500"
                    >
                        No comments yet.
                    </div>
                </section>
            </div>
        </div>
    </Modal>
</template>
