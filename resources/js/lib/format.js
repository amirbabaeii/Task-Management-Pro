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

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
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
