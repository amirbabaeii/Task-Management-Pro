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
import { computed } from 'vue';

const props = defineProps({
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
    updatingChecklistItemId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits([
    'close',
    'open-edit',
    'request-archive',
    'request-restore',
    'request-delete',
    'submit-comment',
    'start-reply',
    'cancel-reply',
    'submit-reply',
    'toggle-checklist-item',
]);

const commentDraft = defineModel('commentDraft', {
    type: String,
    default: '',
});

const replyDraft = defineModel('replyDraft', {
    type: String,
    default: '',
});

const checklistItems = computed(() => props.task?.checklist_items ?? []);
const completedChecklistCount = computed(
    () => checklistItems.value.filter((item) => item.completed).length,
);

const activityDotClass = (kind) => {
    switch (kind) {
        case 'created':
            return 'bg-emerald-400';
        case 'status_changed':
            return 'bg-indigo-400';
        case 'assignees_changed':
            return 'bg-amber-400';
        case 'comment_added':
            return 'bg-sky-400';
        case 'archived':
            return 'bg-gray-400';
        case 'restored':
            return 'bg-emerald-400';
        case 'checklist_item_added':
        case 'checklist_item_completed':
        case 'checklist_item_reopened':
        case 'checklist_item_renamed':
        case 'checklist_item_deleted':
            return 'bg-teal-400';
        default:
            return 'bg-gray-300';
    }
};
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
                <div class="flex shrink-0 items-center gap-2">
                    <SecondaryButton
                        v-if="!task.archived_at"
                        @click="emit('open-edit')"
                    >
                        Edit Task
                    </SecondaryButton>
                    <SecondaryButton
                        v-if="task.archived_at"
                        @click="emit('request-restore')"
                    >
                        Restore
                    </SecondaryButton>
                    <button
                        v-else
                        type="button"
                        class="rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-600 transition hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                        @click="emit('request-archive')"
                    >
                        Archive
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-widest text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
                        @click="emit('request-delete')"
                    >
                        Delete
                    </button>
                </div>
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

                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-900">
                            Assignees
                        </h4>
                        <span class="text-xs text-gray-500">
                            {{ task.assignees?.length ?? 0 }}
                            {{ (task.assignees?.length ?? 0) === 1 ? 'person' : 'people' }}
                        </span>
                    </div>
                    <ul
                        v-if="task.assignees && task.assignees.length"
                        class="flex flex-wrap gap-2"
                    >
                        <li
                            v-for="assignee in task.assignees"
                            :key="assignee.id"
                            class="flex items-center gap-2 rounded-full border border-gray-200 bg-white px-2 py-1"
                        >
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700"
                            >
                                {{
                                    assignee.name
                                        .split(/\s+/)
                                        .filter(Boolean)
                                        .slice(0, 2)
                                        .map((p) => p[0]?.toUpperCase() ?? '')
                                        .join('') || '?'
                                }}
                            </span>
                            <span class="text-sm text-gray-700">
                                {{ assignee.name }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500">No one assigned.</p>
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

                <section class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-900">
                            Checklist
                        </h4>
                        <span class="text-xs text-gray-500">
                            {{ completedChecklistCount }}/{{ checklistItems.length }}
                            done
                        </span>
                    </div>

                    <ul v-if="checklistItems.length" class="space-y-2">
                        <li
                            v-for="item in checklistItems"
                            :key="item.id"
                            class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-gray-800 shadow-sm focus:ring-gray-500"
                                :checked="item.completed"
                                :disabled="
                                    task.archived_at ||
                                    updatingChecklistItemId === item.id
                                "
                                @change="
                                    emit('toggle-checklist-item', {
                                        item,
                                        completed: $event.target.checked,
                                    })
                                "
                            />
                            <span
                                class="min-w-0 flex-1 text-sm text-gray-700"
                                :class="{ 'line-through text-gray-400': item.completed }"
                            >
                                {{ item.title }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500">
                        No checklist items yet.
                    </p>
                </section>

                <section
                    v-if="task.activities && task.activities.length"
                    class="space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-900">
                            Activity
                        </h4>
                        <span class="text-xs text-gray-500">
                            {{ task.activities.length }} entries
                        </span>
                    </div>
                    <ol class="space-y-3 border-l-2 border-gray-100 pl-4">
                        <li
                            v-for="activity in task.activities"
                            :key="activity.id"
                            class="relative text-sm text-gray-700"
                        >
                            <span
                                class="absolute -left-[0.4rem] top-1.5 h-2 w-2 rounded-full"
                                :class="activityDotClass(activity.kind)"
                                aria-hidden="true"
                            />
                            <p class="leading-snug">{{ activity.text }}</p>
                            <p class="text-xs text-gray-400">
                                {{ formatDateTime(activity.created_at) }}
                            </p>
                        </li>
                    </ol>
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
