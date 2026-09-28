const relativeTime = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
const pesoFormat = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0,
});

const severityStyles = {
    critical: { icon: 'bg-rose-100 text-rose-700', bar: 'bg-rose-500' },
    attention: { icon: 'bg-amber-100 text-amber-700', bar: 'bg-amber-500' },
    normal: { icon: 'bg-[#eaf2ff] text-[#063b86]', bar: 'bg-[#063b86]' },
};

function timestamp(item) {
    const value = Date.parse(item.occurred_at ?? '');
    return Number.isFinite(value) ? value : 0;
}

function timeAgo(item) {
    const time = timestamp(item);
    if (!time) return String(item.occurred_human ?? '');

    const seconds = Math.round((time - Date.now()) / 1000);
    const units = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeTime.format(Math.round(seconds / size), unit);
        }
    }

    return 'Just now';
}

function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function createItem(item, unread) {
    const styles = severityStyles[item.severity] ?? severityStyles.normal;

    const link = element(
        'a',
        `group flex gap-3 rounded-lg px-3 py-3 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none ${unread ? 'bg-[#f2f7ff]' : ''}`,
    );
    link.href = item.url || '#';
    link.dataset.notificationItem = item.key;

    const icon = element(
        'span',
        `flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-extrabold ${styles.icon}`,
        `${Number(item.progress_percent ?? 0)}%`,
    );
    icon.setAttribute('aria-hidden', 'true');

    const body = element('div', 'min-w-0 flex-1');

    const headline = element('p', 'text-sm leading-5 text-slate-700');

    if (item.message) {
        // Edit request / decision: the message is the headline, the project follows.
        headline.append(
            element('strong', 'font-semibold text-slate-900', item.message),
            document.createTextNode(' '),
            element('span', 'text-slate-600', item.project_title),
        );
    } else {
        headline.append(
            element('strong', 'font-semibold text-slate-900', item.project_title),
            document.createTextNode(item.action_label ? ' needs ' : ' is now '),
            element('strong', 'font-semibold text-slate-900', item.action_label || item.status_label),
        );
    }

    const meta = element(
        'p',
        'mt-0.5 truncate text-xs text-slate-500',
        [item.location, item.implementation_mode].filter(Boolean).join(' · '),
    );

    const figures = element(
        'p',
        'mt-0.5 text-xs text-slate-500',
        `${Number(item.beneficiaries_total ?? 0).toLocaleString()} beneficiaries · ${pesoFormat.format(Number(item.total_project_cost ?? 0))}`,
    );

    const progress = element('div', 'mt-2');
    const track = element('div', 'h-1.5 overflow-hidden rounded-full bg-slate-200');
    const bar = element('div', `h-full rounded-full ${styles.bar}`);
    bar.style.width = `${Math.min(100, Math.max(0, Number(item.progress_percent ?? 0)))}%`;
    track.append(bar);
    progress.append(
        track,
        element(
            'div',
            'mt-1 text-[11px] text-slate-500',
            `Stage ${item.stage_number} of ${item.stage_count} · ${item.stage_label}`,
        ),
    );

    const time = element(
        'div',
        `mt-1 text-xs ${unread ? 'font-semibold text-[#1765d8]' : 'text-slate-400'}`,
        timeAgo(item),
    );

    body.append(headline);

    if (item.reason) {
        body.append(element('p', 'mt-1 rounded-md bg-slate-100 px-2 py-1 text-xs italic text-slate-600', `“${item.reason}”`));
    }

    body.append(meta, figures, progress, time);
    link.append(icon, body);

    if (unread) {
        const dot = element('span', 'mt-4 h-2.5 w-2.5 shrink-0 rounded-full bg-[#1765d8]');
        dot.setAttribute('aria-label', 'Unread');
        link.append(dot);
    }

    if (!Array.isArray(item.actions) || item.actions.length === 0) {
        return link;
    }

    // Buttons cannot live inside the link, so wrap both in a container.
    const container = element('div', `rounded-lg ${unread ? 'bg-[#f2f7ff]' : ''}`);
    link.classList.remove('bg-[#f2f7ff]');
    container.append(link, createActions(item));

    return container;
}

function createActions(item) {
    const row = element('div', 'flex flex-wrap items-center gap-2 px-3 pb-3 pl-17');

    item.actions.forEach((action) => {
        const button = element(
            'button',
            action.style === 'primary'
                ? 'inline-flex h-8 items-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b] disabled:opacity-60'
                : 'inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60',
            action.label,
        );
        button.type = 'button';

        button.addEventListener('click', async (event) => {
            event.stopPropagation();
            row.querySelectorAll('button').forEach((other) => { other.disabled = true; });

            try {
                const response = await fetch(action.url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    credentials: 'same-origin',
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.message || 'The request could not be completed.');
                }

                row.replaceChildren(element('span', 'text-xs font-semibold text-emerald-700', data.message || 'Done.'));
                window.dispatchEvent(new CustomEvent('tupad:notifications-refresh'));
            } catch (error) {
                row.querySelectorAll('button').forEach((other) => { other.disabled = false; });
                row.querySelector('[data-action-error]')?.remove();
                const message = element('span', 'text-xs font-semibold text-rose-600', error.message);
                message.dataset.actionError = '';
                row.append(message);
            }
        });

        row.append(button);
    });

    return row;
}

function createEmptyState() {
    const empty = element('div', 'px-4 py-10 text-center');
    empty.append(
        element('div', 'text-sm font-semibold text-slate-800', 'No notifications yet'),
        element('p', 'mt-1 text-xs text-slate-500', 'Projects that need your action will appear here.'),
    );
    return empty;
}

export function initializeNotificationDropdown() {
    const menu = document.querySelector('[data-notification-menu]');
    const bell = menu?.querySelector('[data-notification-bell]');
    const panel = menu?.querySelector('[data-notification-panel]');
    const list = menu?.querySelector('[data-notification-dropdown-list]');

    if (!menu || !bell || !panel || !list) {
        return;
    }

    const userId = document.body.dataset.notificationUserId ?? 'guest';
    const seenKey = `tupad.notification.seen.${userId}`;

    // Filled by the realtime poller, which fetches the feed immediately on page load.
    let items = null;

    const readSeen = () => {
        try {
            return Number(localStorage.getItem(seenKey)) || 0;
        } catch {
            return 0;
        }
    };

    const writeSeen = (value) => {
        try {
            localStorage.setItem(seenKey, String(value));
        } catch {
            // Storage can be unavailable in hardened/private browser contexts.
        }
    };

    // Items newer than this threshold are highlighted as unread. While the panel
    // is open the threshold stays at its pre-open value so highlights remain visible.
    let unreadThreshold = readSeen();

    const render = () => {
        if (items === null) return;

        const sorted = [...items].sort((a, b) => timestamp(b) - timestamp(a));
        list.replaceChildren();

        if (sorted.length === 0) {
            list.append(createEmptyState());
            return;
        }

        sorted.forEach((item) => list.append(createItem(item, timestamp(item) > unreadThreshold)));
    };

    const isOpen = () => !panel.classList.contains('hidden');

    const open = () => {
        render();
        panel.classList.remove('hidden');
        bell.setAttribute('aria-expanded', 'true');

        if (items !== null) {
            writeSeen(Math.max(readSeen(), ...items.map(timestamp)));
        }
    };

    const close = ({ restoreFocus = false } = {}) => {
        if (!isOpen()) return;

        panel.classList.add('hidden');
        bell.setAttribute('aria-expanded', 'false');
        unreadThreshold = readSeen();

        if (restoreFocus) bell.focus();
    };

    bell.addEventListener('click', (event) => {
        event.preventDefault();
        isOpen() ? close() : open();
    });

    document.addEventListener('click', (event) => {
        if (isOpen() && !menu.contains(event.target)) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) close({ restoreFocus: true });
    });

    window.addEventListener('tupad:notifications-updated', (event) => {
        if (!Array.isArray(event.detail?.project_items)) return;

        const firstLoad = items === null;
        items = event.detail.project_items;
        render();

        // Items that arrived while the panel was already open count as seen.
        if (firstLoad && isOpen()) {
            writeSeen(Math.max(readSeen(), ...items.map(timestamp)));
        }
    });
}
