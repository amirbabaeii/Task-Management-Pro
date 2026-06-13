<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import { formatDateTime } from '@/lib/format';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const page = usePage();
const notifications = ref([]);
const unreadCount = ref(page.props.unreadNotifications ?? 0);
const loading = ref(false);
const loaded = ref(false);
const notificationFilter = ref('all');
const notificationType = ref('all');
const dismissingNotificationId = ref(null);

const sharedUnread = computed(() => page.props.unreadNotifications ?? 0);
const unreadLabel = computed(() =>
    unreadCount.value === 1 ? '1 unread' : `${unreadCount.value} unread`,
);
const loadedUnreadCount = computed(
    () => notifications.value.filter((notification) => !notification.read_at).length,
);
const notificationTypeOptions = computed(() => {
    const kinds = [
        ...new Set(
            notifications.value
                .map((notification) => notification.data?.kind)
                .filter(Boolean),
        ),
    ];

    return kinds.map((kind) => ({
        value: kind,
        label: kindLabel({ data: { kind } }),
    }));
});
const visibleNotifications = computed(() =>
    notifications.value.filter((notification) => {
        if (
            notificationFilter.value === 'unread' &&
            notification.read_at
        ) {
            return false;
        }

        return (
            notificationType.value === 'all' ||
            notification.data?.kind === notificationType.value
        );
    }),
);
const emptyFilterTitle = computed(() =>
    notificationFilter.value === 'unread' &&
    notificationType.value === 'all'
        ? 'No unread notifications'
        : 'No matching notifications',
);
const emptyFilterDetail = computed(() =>
    notificationFilter.value === 'unread' &&
    notificationType.value === 'all'
        ? 'Everything in this list has been read.'
        : 'Try another notification type or show the full list.',
);

watch(sharedUnread, (count) => {
    unreadCount.value = count;
});

const fetchNotifications = async () => {
    if (loading.value) {
        return;
    }

    loading.value = true;
    try {
        const response = await axios.get(route('notifications.index'));
        notifications.value = response.data.notifications ?? [];
        unreadCount.value = response.data.unread_count ?? 0;
        loaded.value = true;
    } catch {
        // Silently swallow — bell is non-critical UI.
    } finally {
        loading.value = false;
    }
};

const onOpen = () => {
    if (! loaded.value) {
        fetchNotifications();
    }
};

const markAsRead = async (notification) => {
    if (notification.read_at) {
        return;
    }

    try {
        const response = await axios.patch(
            route('notifications.read', { id: notification.id }),
        );
        notification.read_at = new Date().toISOString();
        unreadCount.value = response.data.unread_count ?? 0;
    } catch {
        // ignore
    }
};

const markAllAsRead = async () => {
    if (unreadCount.value === 0) {
        return;
    }

    try {
        await axios.patch(route('notifications.read-all'));
        notifications.value = notifications.value.map((n) => ({
            ...n,
            read_at: n.read_at ?? new Date().toISOString(),
        }));
        unreadCount.value = 0;
    } catch {
        // ignore
    }
};

const dismissNotification = async (notification) => {
    if (dismissingNotificationId.value !== null) {
        return;
    }

    dismissingNotificationId.value = notification.id;
    try {
        const response = await axios.delete(
            route('notifications.destroy', { id: notification.id }),
        );
        notifications.value = notifications.value.filter(
            (item) => item.id !== notification.id,
        );
        unreadCount.value = response.data.unread_count ?? unreadCount.value;
    } catch {
        // ignore
    } finally {
        dismissingNotificationId.value = null;
    }
};

const summarize = (notification) => {
    const data = notification.data ?? {};

    if (data.kind === 'task_assigned') {
        return `${data.assigned_by?.name ?? 'Someone'} assigned “${data.task?.title ?? 'a task'}” to you on ${data.board?.name ?? 'a board'}`;
    }
    if (data.kind === 'board_member_added') {
        return `${data.invited_by?.name ?? 'Someone'} added you to ${data.board?.name ?? 'a board'}`;
    }
    if (data.kind === 'comment_reply') {
        return `${data.author?.name ?? 'Someone'} replied to your comment on “${data.task?.title ?? 'a task'}”`;
    }
    if (data.kind === 'task_deadline_reminder') {
        const title = data.task?.title ?? 'a task';
        const board = data.board?.name ?? 'a board';

        return data.deadline_state === 'overdue'
            ? `“${title}” is overdue on ${board}`
            : `“${title}” is due today on ${board}`;
    }
    return 'You have a new notification';
};

const kindLabel = (notification) => {
    const data = notification.data ?? {};

    if (data.kind === 'task_assigned') {
        return 'Assignment';
    }

    if (data.kind === 'board_member_added') {
        return 'Board';
    }

    if (data.kind === 'comment_reply') {
        return 'Reply';
    }

    if (data.kind === 'task_deadline_reminder') {
        return data.deadline_state === 'overdue' ? 'Overdue' : 'Due today';
    }

    return 'Update';
};

const kindBadgeClass = (notification) => {
    const data = notification.data ?? {};

    if (data.kind === 'task_deadline_reminder') {
        return data.deadline_state === 'overdue'
            ? 'border-rose-200 bg-rose-50 text-rose-700'
            : 'border-amber-200 bg-amber-50 text-amber-700';
    }

    if (data.kind === 'task_assigned') {
        return 'border-indigo-200 bg-indigo-50 text-indigo-700';
    }

    if (data.kind === 'comment_reply') {
        return 'border-sky-200 bg-sky-50 text-sky-700';
    }

    return 'border-gray-200 bg-gray-50 text-gray-600';
};

const linkFor = (notification) => {
    const data = notification.data ?? {};
    if (data.board?.id) {
        const boardHref = route('tasks.board', { board: data.board.id });

        if (data.task?.id) {
            const query = new URLSearchParams({ task: data.task.id }).toString();

            return `${boardHref}?${query}`;
        }

        return boardHref;
    }
    return route('dashboard');
};
</script>

<template>
    <Dropdown
        align="right"
        width="80"
        content-classes="overflow-hidden bg-white"
        :full-height="false"
        @click="onOpen"
    >
        <template #trigger>
            <button
                type="button"
                class="relative flex h-9 w-9 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                aria-label="Notifications"
                @click="onOpen"
            >
                <svg
                    class="h-5 w-5"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6z"
                    />
                    <path
                        d="M8.5 16a1.5 1.5 0 003 0h-3z"
                    />
                </svg>
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1.1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold leading-4 text-white"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
            </button>
        </template>
        <template #content>
            <div class="w-full">
                <div
                    class="flex items-center justify-between border-b border-gray-100 px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <h4 class="text-sm font-semibold text-gray-900">
                            Notifications
                        </h4>
                        <span
                            v-if="unreadCount > 0"
                            class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-700"
                        >
                            {{ unreadLabel }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="text-xs font-semibold text-gray-500 hover:text-gray-700 disabled:text-gray-300"
                            :disabled="loading"
                            @click.stop="fetchNotifications"
                        >
                            {{ loading ? 'Refreshing...' : 'Refresh' }}
                        </button>
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-700"
                            @click.stop="markAllAsRead"
                        >
                            Mark all read
                        </button>
                    </div>
                </div>
                <div
                    v-if="notifications.length"
                    class="flex items-center gap-1 border-b border-gray-100 px-3 py-2"
                >
                    <button
                        type="button"
                        class="rounded-md px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="
                            notificationFilter === 'all'
                                ? 'bg-gray-800 text-white'
                                : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'
                        "
                        @click.stop="notificationFilter = 'all'"
                    >
                        All {{ notifications.length }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="
                            notificationFilter === 'unread'
                                ? 'bg-gray-800 text-white'
                                : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'
                        "
                        @click.stop="notificationFilter = 'unread'"
                    >
                        Unread {{ loadedUnreadCount }}
                    </button>
                    <select
                        v-if="notificationTypeOptions.length > 1"
                        v-model="notificationType"
                        class="ml-auto rounded-md border-gray-300 py-1 pl-2 pr-7 text-[11px] font-semibold uppercase tracking-wide text-gray-600 shadow-sm focus:border-gray-500 focus:ring-gray-500"
                    >
                        <option value="all">All types</option>
                        <option
                            v-for="option in notificationTypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div
                    v-if="loading && notifications.length === 0"
                    class="px-4 py-8 text-center text-xs text-gray-500"
                >
                    Loading...
                </div>
                <div
                    v-else-if="notifications.length === 0"
                    class="px-4 py-8 text-center"
                >
                    <div class="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6z"
                            />
                            <path d="M8.5 16a1.5 1.5 0 003 0h-3z" />
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-medium text-gray-700">
                        No notifications
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        You're all caught up.
                    </p>
                    <button
                        type="button"
                        class="mt-3 rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-gray-600 transition hover:bg-gray-50 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:text-gray-300"
                        :disabled="loading"
                        @click.stop="fetchNotifications"
                    >
                        {{ loading ? 'Checking...' : 'Check again' }}
                    </button>
                </div>
                <div
                    v-else-if="visibleNotifications.length === 0"
                    class="px-4 py-8 text-center"
                >
                    <p class="text-sm font-medium text-gray-700">
                        {{ emptyFilterTitle }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ emptyFilterDetail }}
                    </p>
                    <button
                        type="button"
                        class="mt-3 rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-gray-600 transition hover:bg-gray-50 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click.stop="
                            notificationFilter = 'all';
                            notificationType = 'all';
                        "
                    >
                        Show all
                    </button>
                </div>
                <ul
                    v-else
                    class="max-h-80 divide-y divide-gray-100 overflow-y-auto"
                >
                    <li
                        v-for="notification in visibleNotifications"
                        :key="notification.id"
                        class="transition"
                        :class="
                            notification.read_at ? 'bg-white' : 'bg-indigo-50/40'
                        "
                    >
                        <div class="flex items-start hover:bg-gray-50">
                            <Link
                                :href="linkFor(notification)"
                                class="flex min-w-0 flex-1 gap-3 px-4 py-3 pr-2 text-sm text-gray-700"
                                @click="markAsRead(notification)"
                            >
                                <span
                                    class="mt-2 h-2 w-2 shrink-0 rounded-full"
                                    :class="
                                        notification.read_at
                                            ? 'bg-transparent'
                                            : 'bg-indigo-500'
                                    "
                                    aria-hidden="true"
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="mb-1.5 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                        :class="kindBadgeClass(notification)"
                                    >
                                        {{ kindLabel(notification) }}
                                    </span>
                                    <span class="block leading-snug">
                                        {{ summarize(notification) }}
                                    </span>
                                    <span class="mt-1 block text-[10px] text-gray-400">
                                        {{ formatDateTime(notification.created_at) }}
                                    </span>
                                </span>
                            </Link>
                            <button
                                type="button"
                                class="mr-2 mt-2 flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-white hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500 disabled:opacity-40"
                                :disabled="
                                    dismissingNotificationId === notification.id
                                "
                                title="Dismiss notification"
                                aria-label="Dismiss notification"
                                @click.stop="dismissNotification(notification)"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </template>
    </Dropdown>
</template>
