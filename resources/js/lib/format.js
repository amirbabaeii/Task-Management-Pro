// Pure formatters for display values. No Vue, no state.

export const defaultStatusLabels = {
    pending: 'Pending',
    'in-progress': 'In Progress',
    completed: 'Completed',
};

export const formatStatus = (status, labels = {}) =>
    labels[status] ?? defaultStatusLabels[status] ?? status;

export const formatPriority = (priority) => {
    if (!priority) {
        return 'Unspecified';
    }

    return `${priority.charAt(0).toUpperCase()}${priority.slice(1)}`;
};

export const priorityBadgeClass = (priority) => {
    if (priority === 'low') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (priority === 'high') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    return 'border-amber-200 bg-amber-50 text-amber-700';
};

export const formatDate = (value) => {
    if (!value) {
        return null;
    }

    const date = dateFromDateLike(value);

    if (!date) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(date);
};

export const formatDateTime = (value) => {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
};

export const formatDateInput = (value) => {
    if (!value) {
        return '';
    }

    if (typeof value === 'string') {
        return value.slice(0, 10);
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toISOString().slice(0, 10);
};

const dateFromDateLike = (value) => {
    if (!value) {
        return null;
    }

    if (typeof value === 'string') {
        const dateParts = value.slice(0, 10).split('-').map(Number);

        if (
            dateParts.length === 3 &&
            dateParts.every((part) => Number.isInteger(part))
        ) {
            return new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
        }
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
};

const today = () => {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
};

export const daysUntilDate = (value) => {
    const date = dateFromDateLike(value);

    if (!date) {
        return null;
    }

    const millisecondsPerDay = 24 * 60 * 60 * 1000;

    return Math.round((date.getTime() - today().getTime()) / millisecondsPerDay);
};

export const deadlineState = (value) => {
    const daysUntil = daysUntilDate(value);

    if (daysUntil === null) {
        return 'none';
    }

    if (daysUntil < 0) {
        return 'overdue';
    }

    if (daysUntil === 0) {
        return 'today';
    }

    if (daysUntil <= 7) {
        return 'soon';
    }

    return 'scheduled';
};

export const deadlineBadgeClass = (value) => {
    const state = deadlineState(value);

    if (state === 'overdue') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    if (state === 'today') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    if (state === 'soon') {
        return 'border-sky-200 bg-sky-50 text-sky-700';
    }

    return 'border-gray-200 bg-white text-gray-500';
};

export const formatDeadlineLabel = (value) => {
    const formattedDate = formatDate(value);

    if (!formattedDate) {
        return null;
    }

    const state = deadlineState(value);

    if (state === 'overdue') {
        return `Overdue ${formattedDate}`;
    }

    if (state === 'today') {
        return 'Due today';
    }

    return `Due ${formattedDate}`;
};
