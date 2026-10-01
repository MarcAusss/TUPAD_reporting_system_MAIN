/*
|--------------------------------------------------------------------------
| Global search (top bar)
|--------------------------------------------------------------------------
| Live suggestions from /search/suggest. "/" or Ctrl/Cmd+K focuses the box,
| arrow keys move through results, Enter opens one, Escape closes.
*/

function isTypingTarget(element) {
    return element instanceof HTMLElement
        && (element.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName));
}

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function initializeSearchBox(form) {
    const input = form.querySelector('[data-global-search-input]');
    const results = form.querySelector('[data-global-search-results]');
    const url = form.dataset.suggestUrl;
    if (!input || !results || !url) return null;

    let timer = null;
    let controller = null;
    let active = -1;

    const options = () => Array.from(results.querySelectorAll('[role="option"]'));

    const close = () => {
        results.classList.add('hidden');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };

    const open = () => {
        results.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
    };

    const highlight = (index) => {
        const items = options();
        if (items.length === 0) return;
        active = (index + items.length) % items.length;
        items.forEach((item, position) => {
            const selected = position === active;
            item.setAttribute('aria-selected', selected ? 'true' : 'false');
            item.classList.toggle('bg-blue-50', selected);
        });
        input.setAttribute('aria-activedescendant', items[active].id);
        items[active].scrollIntoView({ block: 'nearest' });
    };

    const option = (href, idSuffix) => {
        const link = el('a', 'flex items-start gap-3 px-4 py-2.5 hover:bg-slate-50 focus:outline-none');
        link.href = href;
        link.id = `${results.id}-${idSuffix}`;
        link.setAttribute('role', 'option');
        link.setAttribute('aria-selected', 'false');
        return link;
    };

    const heading = (text) => el('div', 'px-4 pb-1 pt-3 text-[10px] font-bold uppercase tracking-wider text-slate-400', text);

    const render = (data, query) => {
        results.replaceChildren();
        const projects = data.projects || [];
        const adls = data.adls || [];

        if (projects.length === 0 && adls.length === 0) {
            const empty = el('div', 'px-4 py-6 text-center');
            empty.append(
                el('div', 'text-sm font-semibold text-slate-700', `No matches for “${query}”`),
                el('p', 'mt-1 text-xs text-slate-500', 'Try a project title, project code, ADL number, municipality, or barangay.'),
            );
            results.append(empty);
            open();
            return;
        }

        if (projects.length > 0) {
            results.append(heading('Projects'));
            projects.forEach((project, index) => {
                const link = option(project.url, `p${index}`);
                const icon = el('span', 'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#063b86]');
                icon.innerHTML = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>';

                const body = el('span', 'min-w-0 flex-1');
                body.append(el('span', 'block truncate text-sm font-semibold text-slate-900', project.title));

                const meta = el('span', 'mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500');
                [project.code, project.adl ? `ADL ${project.adl}` : null, project.location]
                    .filter(Boolean)
                    .forEach((part, position) => {
                        if (position > 0) meta.append(el('span', 'text-slate-300', '•'));
                        meta.append(el('span', position === 0 && project.code ? 'font-semibold text-slate-700' : '', part));
                    });
                const pill = el('span', `rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset ${project.status_classes || ''}`, project.status);
                meta.append(pill);
                if (project.days !== null && project.days !== undefined) {
                    meta.append(el('span', project.days >= 14 ? 'font-semibold text-rose-600' : '', project.days === 0 ? 'today' : `${project.days}d in stage`));
                }
                body.append(meta);

                link.append(icon, body);
                results.append(link);
            });
        }

        if (adls.length > 0) {
            results.append(heading('ADLs'));
            adls.forEach((adl, index) => {
                const link = option(adl.url, `a${index}`);
                link.append(el('span', 'text-sm font-semibold text-slate-800', `ADL ${adl.adl_number}`));
                results.append(link);
            });
        }

        const more = el('a', 'block border-t border-slate-100 px-4 py-2.5 text-center text-xs font-semibold text-[#063b86] hover:bg-slate-50', `See all results for “${query}” →`);
        more.href = data.more_url;
        results.append(more);
        open();
    };

    const search = () => {
        const query = input.value.trim();
        if (query.length < 2) {
            close();
            return;
        }

        controller?.abort();
        controller = new AbortController();

        fetch(`${url}?q=${encodeURIComponent(query)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((data) => {
                if (input.value.trim() === query) render(data, query);
            })
            .catch((error) => {
                if (error?.name !== 'AbortError') close();
            });
    };

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(search, 200);
    });

    input.addEventListener('focus', () => {
        if (results.childElementCount > 0 && input.value.trim().length >= 2) open();
    });

    input.addEventListener('keydown', (event) => {
        const expanded = !results.classList.contains('hidden');

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (!expanded) search();
            else highlight(active + 1);
        } else if (event.key === 'ArrowUp' && expanded) {
            event.preventDefault();
            highlight(active - 1);
        } else if (event.key === 'Enter' && expanded && active >= 0) {
            event.preventDefault();
            window.location.href = options()[active].href;
        } else if (event.key === 'Escape') {
            if (expanded) {
                event.preventDefault();
                close();
            } else {
                input.blur();
            }
        }
    });

    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) close();
    });

    return input;
}

function initializeGlobalSearch() {
    const inputs = Array.from(document.querySelectorAll('form[data-global-search]'))
        .map(initializeSearchBox)
        .filter(Boolean);

    if (inputs.length === 0) return;

    const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
    document.querySelectorAll('[data-global-search-hint]').forEach((hint) => {
        hint.textContent = isMac ? '⌘K' : 'Ctrl K';
    });

    document.addEventListener('keydown', (event) => {
        const shortcut = (event.key === 'k' || event.key === 'K') && (event.ctrlKey || event.metaKey);
        const slash = event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !isTypingTarget(event.target);

        if (!shortcut && !slash) return;
        if (document.querySelector('dialog[open]')) return;

        const visible = inputs.find((input) => input.offsetParent !== null) ?? inputs[0];
        event.preventDefault();
        visible.focus();
        visible.select();
    });
}

/*
|--------------------------------------------------------------------------
| Remembered filters
|--------------------------------------------------------------------------
| Filter forms marked data-remember-filters store their query string per
| page. Coming back to the page without filters restores them; "Clear" /
| "Reset" links (data-clear-filters or a bare link to this page) forget them.
*/

function filterKey() {
    return `tupad-filters:${window.location.pathname}`;
}

function storage(action, value) {
    try {
        if (action === 'get') return window.localStorage.getItem(filterKey());
        if (action === 'set') window.localStorage.setItem(filterKey(), value);
        if (action === 'remove') window.localStorage.removeItem(filterKey());
    } catch {
        return null;
    }
    return null;
}

function initializeRememberedFilters() {
    if (!document.querySelector('form[data-remember-filters]')) return;

    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    const current = params.toString();

    if (current !== '') {
        storage('set', current);
    } else {
        const saved = storage('get');
        if (saved) {
            const restored = new URL(window.location.href);
            restored.search = saved;
            window.location.replace(restored.toString());
            return;
        }
    }

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link) return;

        const target = new URL(link.href, window.location.href);
        const isBareLinkToThisPage = target.origin === window.location.origin
            && target.pathname === window.location.pathname
            && target.search === '';

        if (link.hasAttribute('data-clear-filters') || isBareLinkToThisPage) {
            storage('remove');
        }
    }, true);
}

export function initializeNavigation() {
    initializeRememberedFilters();
    initializeGlobalSearch();
}
