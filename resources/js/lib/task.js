// Pure helpers for normalizing and grouping board tasks. No Vue, no state.

export const normalizeComment = (comment) => ({
    ...comment,
    id: Number(comment.id ?? 0),
    parent_id:
        comment.parent_id === null || comment.parent_id === undefined
            ? null
            : Number(comment.parent_id),
    user: {
        id: Number(comment.user?.id ?? 0),
        name: comment.user?.name ?? 'Unknown user',
    },
    replies: Array.isArray(comment.replies)
        ? comment.replies.map(normalizeComment)
        : [],
});

export const normalizeTask = (task) => ({
    ...task,
    progress: Number(task.progress ?? 0),
    sort_order: Number(task.sort_order ?? 0),
    archived_at: task.archived_at ?? null,
    tags: Array.isArray(task.tags)
        ? task.tags.map((tag) => `${tag ?? ''}`.trim()).filter(Boolean)
        : [],
    comments: Array.isArray(task.comments)
        ? task.comments.map(normalizeComment)
        : [],
    assignees: Array.isArray(task.assignees)
        ? task.assignees.map((assignee) => ({
              id: Number(assignee.id ?? 0),
              name: assignee.name ?? 'Unknown user',
          }))
        : [],
    activities: Array.isArray(task.activities) ? task.activities : [],
});

export const cloneTasks = (taskList) => taskList.map((task) => ({ ...task }));

export const sortTaskList = (taskList) =>
    [...taskList].sort(
        (left, right) =>
            (left.sort_order ?? 0) - (right.sort_order ?? 0) ||
            left.id - right.id,
    );

export const visibleTags = (tags = [], limit = 3) => tags.slice(0, limit);

export const hiddenTagCount = (tags = [], limit = 3) =>
    Math.max(tags.length - limit, 0);

export const commentCount = (comments = []) =>
    comments.reduce(
        (count, comment) => count + 1 + commentCount(comment.replies ?? []),
        0,
    );

export const buildGroupedTasks = (taskList, statuses = []) => {
    const grouped = {};

    statuses.forEach((status) => {
        grouped[status] = [];
    });

    taskList.forEach((task) => {
        if (!grouped[task.status]) {
            grouped[task.status] = [];
        }

        grouped[task.status].push({ ...normalizeTask(task) });
    });

    Object.keys(grouped).forEach((status) => {
        grouped[status] = sortTaskList(grouped[status]);
    });

    return grouped;
};

export const flattenGroupedTasks = (grouped, statuses = []) => {
    const orderedStatuses = [
        ...statuses,
        ...Object.keys(grouped).filter((status) => !statuses.includes(status)),
    ];

    return orderedStatuses.flatMap((status) => grouped[status] ?? []);
};

export const appendReplyToComments = (comments, parentId, reply) =>
    comments.map((comment) => {
        if (comment.id !== parentId) {
            return comment;
        }

        return normalizeComment({
            ...comment,
            replies: [...(comment.replies ?? []), reply],
        });
    });
