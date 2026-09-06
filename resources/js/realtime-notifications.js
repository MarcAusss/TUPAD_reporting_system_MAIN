const DEFAULT_POLL_MS = 10000;
const MIN_POLL_MS = 5000;
const MAX_TOASTS_PER_POLL = 3;

const severityRank = {
    normal: 0,
    attention: 1,
    critical: 2,
};

function safeNumber(value) {
    const number = Number(value ?? 0);
    return Number.isFinite(number) ? number : 0;
}

function normalizeItems(items) {
    if (!Array.isArray(items)) {
        return [];
    }

    return items.map((item) => ({
        key: String(item?.key ?? ''),
        title: String(item?.title ?? 'Workflow update'),
        message: String(item?.message ?? ''),
        count: safeNumber(item?.count),
        severity: ['critical', 'attention', 'normal'].includes(item?.severity)
            ? item.severity
            : 'normal',
        url: String(item?.url ?? ''),
        category: String(item?.category ?? 'Workflow Queue'),
        stateToken: String(item?.state_token ?? ''),
    })).filter((item) => item.key !== '');
}

function snapshot(items) {
    return Object.fromEntries(items.map((item) => [item.key, {
        count: item.count,
        severity: item.severity,
        message: item.message,
        stateToken: item.stateToken,
    }]));
}

function changedItems(previous, currentItems) {
    if (!previous) {
        return [];
    }

    return currentItems.filter((item) => {
        const old = previous[item.key];

        if (!old) {
            return true;
        }

        if (item.count > safeNumber(old.count)) {
            return true;
        }

        if ((severityRank[item.severity] ?? 0) > (severityRank[old.severity] ?? 0)) {
            return true;
        }

        // Detect queue membership changes even when the aggregate count stays the same.
        if (item.stateToken !== String(old.stateToken ?? '')) {
            return true;
        }

        // Aging/severity text can change while the user keeps the page open.
        return item.message !== String(old.message ?? '');
    });
}

function updateBell(data) {
    const bell = document.querySelector('[data-notification-bell]');
    const badge = bell?.querySelector('[data-notification-badge]');
    const liveLabel = bell?.querySelector('[data-notification-live-label]');
    const count = safeNumber(data.total_count);

    if (!bell || !badge) {
        return;
    }

    badge.textContent = count > 99 ? '99+' : String(count);
    badge.classList.toggle('hidden', count < 1);
    bell.setAttribute('aria-label', count > 0
        ? `Notifications: ${count} active item${count === 1 ? '' : 's'}`
        : 'Notifications');

    if (liveLabel) {
        liveLabel.textContent = count > 0
            ? `${count} active notification item${count === 1 ? '' : 's'}`
            : 'No active notifications';
    }
}

function severityClasses(severity) {
    if (severity === 'critical') {
        return 'border-rose-200 bg-rose-50 text-rose-800';
    }

    if (severity === 'attention') {
        return 'border-amber-200 bg-amber-50 text-amber-800';
    }

    return 'border-blue-100 bg-blue-50 text-blue-800';
}

function createNotificationRow(item) {
    const row = document.createElement('div');
    row.className = 'flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between';

    const content = document.createElement('div');
    content.className = 'min-w-0';

    const meta = document.createElement('div');
    meta.className = 'flex flex-wrap items-center gap-2';

    const category = document.createElement('span');
    category.className = `inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${severityClasses(item.severity)}`;
    category.textContent = item.category;

    const count = document.createElement('span');
    count.className = 'text-xs font-semibold text-slate-500';
    count.textContent = `${item.count.toLocaleString()} item(s)`;

    const title = document.createElement('div');
    title.className = 'mt-2 text-sm font-semibold text-slate-900';
    title.textContent = item.title;

    const message = document.createElement('p');
    message.className = 'mt-1 text-xs leading-5 text-slate-500';
    message.textContent = item.message;

    const link = document.createElement('a');
    link.href = item.url || '#';
    link.className = 'inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800';
    link.textContent = 'Open Queue';

    meta.append(category, count);
    content.append(meta, title, message);
    row.append(content, link);

    return row;
}

function updateNotificationCenter(data, items) {
    const total = document.querySelector('[data-notification-total]');
    const attention = document.querySelector('[data-notification-attention]');
    const critical = document.querySelector('[data-notification-critical]');
    const list = document.querySelector('[data-live-notification-list]');

    if (total) total.textContent = safeNumber(data.total_count).toLocaleString();
    if (attention) attention.textContent = safeNumber(data.attention_count).toLocaleString();
    if (critical) critical.textContent = safeNumber(data.critical_count).toLocaleString();

    if (!list) {
        return;
    }

    list.replaceChildren();

    if (items.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'px-5 py-12 text-center';
        empty.dataset.notificationEmpty = '';

        const title = document.createElement('div');
        title.className = 'text-sm font-semibold text-slate-800';
        title.textContent = 'No pending notifications';

        const message = document.createElement('p');
        message.className = 'mt-1 text-xs text-slate-500';
        message.textContent = 'There are no current workflow actions requiring your role.';

        empty.append(title, message);
        list.append(empty);
        return;
    }

    items.forEach((item) => list.append(createNotificationRow(item)));
}

function toastRegion() {
    return document.querySelector('[data-notification-toast-region]');
}

function showToast(item) {
    const region = toastRegion();
    if (!region) return;

    const toast = document.createElement('div');
    toast.className = 'pointer-events-auto translate-x-0 rounded-xl border border-slate-200 bg-white p-4 shadow-xl ring-1 ring-slate-900/5 transition duration-200';
    toast.setAttribute('role', 'status');

    const header = document.createElement('div');
    header.className = 'flex items-start gap-3';

    const dot = document.createElement('span');
    dot.className = `mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${item.severity === 'critical' ? 'bg-rose-600' : item.severity === 'attention' ? 'bg-amber-500' : 'bg-blue-600'}`;

    const body = document.createElement('div');
    body.className = 'min-w-0 flex-1';

    const eyebrow = document.createElement('div');
    eyebrow.className = 'text-[10px] font-bold uppercase tracking-[0.1em] text-slate-400';
    eyebrow.textContent = 'New workflow update';

    const title = document.createElement('div');
    title.className = 'mt-1 text-sm font-semibold text-slate-900';
    title.textContent = item.title;

    const message = document.createElement('p');
    message.className = 'mt-1 text-xs leading-5 text-slate-500';
    message.textContent = item.message;

    body.append(eyebrow, title, message);

    if (item.url) {
        const link = document.createElement('a');
        link.href = item.url;
        link.className = 'mt-3 inline-flex text-xs font-semibold text-[#063b86] hover:underline';
        link.textContent = 'Open related queue';
        body.append(link);
    }

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'shrink-0 rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.textContent = '×';
    close.addEventListener('click', () => toast.remove());

    header.append(dot, body, close);
    toast.append(header);
    region.prepend(toast);

    window.setTimeout(() => toast.remove(), 8000);
}

function showChangedToasts(items) {
    const visible = items.slice(0, MAX_TOASTS_PER_POLL);
    visible.forEach(showToast);

    if (items.length > MAX_TOASTS_PER_POLL) {
        showToast({
            title: `${items.length - MAX_TOASTS_PER_POLL} more workflow update(s)`,
            message: 'Open Notifications to review all current actions.',
            severity: 'normal',
            url: document.querySelector('[data-notification-bell]')?.href ?? '',
        });
    }
}

export function initializeRealtimeNotifications() {
    const root = document.body;
    const feedUrl = root?.dataset.notificationFeedUrl;
    const userId = root?.dataset.notificationUserId;

    if (!feedUrl || !userId) {
        return;
    }

    const storageKey = `tupad.notification.snapshot.${userId}`;
    const configuredPoll = safeNumber(root.dataset.notificationPollMs) || DEFAULT_POLL_MS;
    let pollMs = Math.max(MIN_POLL_MS, configuredPoll);
    let previous = null;
    let stopped = false;
    let inFlight = false;
    let timer = null;
    let lastPollAt = 0;

    try {
        previous = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    } catch {
        previous = null;
    }

    const saveSnapshot = (value) => {
        previous = value;
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(value));
        } catch {
            // Storage can be unavailable in hardened/private browser contexts.
        }
    };

    const schedule = (delay = pollMs) => {
        if (stopped) return;
        window.clearTimeout(timer);
        timer = window.setTimeout(poll, delay);
    };

    const poll = async () => {
        if (stopped || inFlight) return;

        if (document.visibilityState === 'hidden') {
            schedule(pollMs);
            return;
        }

        inFlight = true;
        lastPollAt = Date.now();

        try {
            const response = await fetch(feedUrl, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (response.status === 401 || response.status === 403) {
                stopped = true;
                return;
            }

            if (!response.ok) {
                schedule(pollMs);
                return;
            }

            const data = await response.json();
            const items = normalizeItems(data.items);
            const current = snapshot(items);
            const changed = changedItems(previous, items);

            updateBell(data);
            updateNotificationCenter(data, items);

            if (changed.length > 0) {
                showChangedToasts(changed);
            }

            saveSnapshot(current);

            window.dispatchEvent(new CustomEvent('tupad:notifications-updated', {
                detail: {
                    ...data,
                    items,
                },
            }));

            const serverPoll = safeNumber(data.poll_after_ms);
            if (serverPoll >= MIN_POLL_MS) {
                pollMs = serverPoll;
            }
        } catch {
            // Temporary network errors are retried silently on the next cycle.
        } finally {
            inFlight = false;
            schedule(pollMs);
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && Date.now() - lastPollAt > 1500) {
            window.clearTimeout(timer);
            poll();
        }
    });

    window.addEventListener('focus', () => {
        if (Date.now() - lastPollAt > 1500) {
            window.clearTimeout(timer);
            poll();
        }
    });

    // Initial request syncs the current badge/page state. Existing alerts are not
    // replayed as toasts unless the stored session snapshot shows they are new.
    poll();
}
