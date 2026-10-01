/*
|--------------------------------------------------------------------------
| Table enhancements (all tables inside <main>)
|--------------------------------------------------------------------------
| - Sortable columns: click a header to sort (skips action/blank headers).
| - Sticky header: long tables scroll inside a 70vh box with a fixed header.
| - Column show/hide: tables with 8+ columns get a "Columns" menu.
| - Totals: money columns (₱) get a total row when the table has none.
|
| Opt out per table with data-no-sort, data-no-sticky, data-no-columns,
| data-no-total, or everything with data-no-enhance. A cell can provide its
| own sort key with data-sort-value.
*/

const MONEY_PATTERN = /^₱\s?-?[\d,]+(\.\d+)?$/;
// Per-unit money columns are not meaningful to add up.
const NON_SUMMABLE_HEADER = /\b(rate|unit|per|price|average|avg|wage)\b/i;
const STICKY_MIN_ROWS = 12;
const COLUMN_MENU_MIN_COLUMNS = 8;

const pesoFormatter = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function headerCells(table) {
    const rows = table.tHead?.rows ?? [];
    return rows.length === 1 ? Array.from(rows[0].cells) : null;
}

function dataRows(table) {
    const body = table.tBodies[0];
    if (!body) return [];

    return Array.from(body.rows).filter((row) => !row.classList.contains('tupad-table-total-row')
        && !row.classList.contains('tupad-report-total-row')
        && !Array.from(row.cells).some((cell) => cell.colSpan > 1 || cell.rowSpan > 1));
}

function hasSimpleStructure(table, headers) {
    if (!headers || headers.some((th) => th.colSpan > 1 || th.rowSpan > 1)) return false;

    const body = table.tBodies[0];
    if (!body || table.tBodies.length > 1) return false;

    return Array.from(body.rows).every((row) => row.cells.length === headers.length
        || Array.from(row.cells).some((cell) => cell.colSpan > 1));
}

function cellText(cell) {
    return (cell?.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function sortKey(cell) {
    if (!cell) return '';
    if (cell.dataset.sortValue !== undefined) {
        const numeric = Number(cell.dataset.sortValue);
        return Number.isNaN(numeric) ? cell.dataset.sortValue.toLowerCase() : numeric;
    }

    const text = cellText(cell);
    const numericText = text.replace(/[₱,%\s]/g, '').replace(/^\((.*)\)$/, '-$1');
    if (/^-?\d+(\.\d+)?$/.test(numericText)) return Number(numericText);

    if (/^[A-Z][a-z]{2,8}\.? \d{1,2}, \d{4}/.test(text) || /^\d{4}-\d{2}-\d{2}/.test(text)) {
        const time = Date.parse(text.replace(/ (\d{1,2}:\d{2}) ?([AP]M)/i, ' $1 $2'));
        if (!Number.isNaN(time)) return time;
    }

    return text.toLowerCase();
}

function compareKeys(a, b) {
    if (a === '' && b !== '') return 1;
    if (b === '' && a !== '') return -1;
    if (typeof a === 'number' && typeof b === 'number') return a - b;
    return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
}

function makeSortable(table, headers) {
    const body = table.tBodies[0];

    headers.forEach((th, index) => {
        const label = cellText(th);
        if (!label || th.hasAttribute('data-no-sort') || /^actions?$/i.test(label)) return;

        th.classList.add('tupad-sortable');
        th.tabIndex = 0;
        th.setAttribute('aria-sort', 'none');
        th.title = `Sort by ${label}`;

        const indicator = document.createElement('span');
        indicator.className = 'tupad-sort-indicator';
        indicator.setAttribute('aria-hidden', 'true');
        th.append(indicator);

        const sort = () => {
            const direction = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
            headers.forEach((other) => other.hasAttribute('aria-sort') && other.setAttribute('aria-sort', 'none'));
            th.setAttribute('aria-sort', direction);

            const sortable = dataRows(table);
            const pinned = Array.from(body.rows).filter((row) => !sortable.includes(row));
            const factor = direction === 'ascending' ? 1 : -1;

            sortable
                .map((row, position) => ({ row, position, key: sortKey(row.cells[index]) }))
                .sort((a, b) => (compareKeys(a.key, b.key) * factor) || (a.position - b.position))
                .forEach(({ row }) => body.append(row));

            pinned.forEach((row) => body.append(row));
        };

        th.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('a, button, input, select')) return;
            sort();
        });
        th.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                sort();
            }
        });
    });
}

function makeSticky(table) {
    const wrapper = table.parentElement;
    if (!wrapper || wrapper.tagName !== 'DIV' || !/overflow-(x-)?auto/.test(wrapper.className)) return;
    if (/max-h-/.test(wrapper.className) || wrapper.classList.contains('tupad-data-scroll')) return;
    if (dataRows(table).length < STICKY_MIN_ROWS) return;

    wrapper.classList.add('tupad-table-scroll');
}

function addTotals(table, headers) {
    if (table.tFoot || table.querySelector('.tupad-table-total-row, .tupad-report-total-row')) return;

    const rows = dataRows(table);
    if (rows.length < 2) return;

    const moneyColumns = headers.map((th, index) => {
        if (NON_SUMMABLE_HEADER.test(cellText(th))) return false;
        const values = rows.map((row) => cellText(row.cells[index])).filter((text) => text !== '' && text !== '—');
        return values.length > 0 && values.every((text) => MONEY_PATTERN.test(text));
    });

    const labelIndex = moneyColumns.findIndex((isMoney) => !isMoney);
    if (!moneyColumns.some(Boolean) || labelIndex === -1) return;

    const paginated = document.querySelector('nav[role="navigation"][aria-label*="agination" i], .pagination') !== null;
    const foot = table.createTFoot();
    const row = foot.insertRow();
    row.className = 'tupad-table-total-row tupad-auto-total';

    headers.forEach((th, index) => {
        const cell = row.insertCell();
        cell.className = th.className.replace(/tupad-sortable/g, '');

        if (moneyColumns[index]) {
            const cents = rows.reduce((sum, dataRow) => {
                const text = cellText(dataRow.cells[index]).replace(/[₱,\s]/g, '');
                return sum + Math.round((Number(text) || 0) * 100);
            }, 0);
            cell.textContent = `₱${pesoFormatter.format(cents / 100)}`;
            cell.classList.add('text-right');
        } else if (index === labelIndex) {
            cell.textContent = paginated ? 'Total (this page)' : 'Total';
        }
    });
}

function storageKey(table, position) {
    return `tupad-columns:${window.location.pathname}:${table.id || position}`;
}

function readHidden(key) {
    try {
        return new Set(JSON.parse(window.localStorage.getItem(key) || '[]'));
    } catch {
        return new Set();
    }
}

function writeHidden(key, hidden) {
    try {
        window.localStorage.setItem(key, JSON.stringify(Array.from(hidden)));
    } catch {
        // Storage can be unavailable (private mode); hiding still works for this visit.
    }
}

function applyHidden(table, hidden) {
    Array.from(table.rows).forEach((row) => {
        if (Array.from(row.cells).some((cell) => cell.colSpan > 1)) return;
        Array.from(row.cells).forEach((cell, index) => {
            cell.classList.toggle('tupad-col-hidden', hidden.has(index));
        });
    });
}

function addColumnMenu(table, headers, position) {
    if (headers.length < COLUMN_MENU_MIN_COLUMNS) return;

    const key = storageKey(table, position);
    const hidden = readHidden(key);
    const anchor = table.closest('.overflow-x-auto, .overflow-auto, .tupad-data-scroll') ?? table;

    const bar = document.createElement('div');
    bar.className = 'tupad-column-bar';

    const details = document.createElement('details');
    details.className = 'tupad-column-menu';

    const summary = document.createElement('summary');
    summary.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 4v16M15 4v16M4 4h16v16H4z"/></svg><span>Columns</span>';
    details.append(summary);

    const panel = document.createElement('div');
    panel.className = 'tupad-column-panel';

    headers.forEach((th, index) => {
        const label = cellText(th);
        if (index === 0 || !label) return;

        const option = document.createElement('label');
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = !hidden.has(index);
        checkbox.addEventListener('change', () => {
            if (checkbox.checked) hidden.delete(index);
            else hidden.add(index);
            applyHidden(table, hidden);
            writeHidden(key, hidden);
        });

        option.append(checkbox, document.createTextNode(label));
        panel.append(option);
    });

    const reset = document.createElement('button');
    reset.type = 'button';
    reset.textContent = 'Show all columns';
    reset.addEventListener('click', () => {
        hidden.clear();
        panel.querySelectorAll('input').forEach((checkbox) => { checkbox.checked = true; });
        applyHidden(table, hidden);
        writeHidden(key, hidden);
    });
    panel.append(reset);

    details.append(panel);
    bar.append(details);
    anchor.before(bar);

    document.addEventListener('click', (event) => {
        if (details.open && !details.contains(event.target)) details.open = false;
    });

    applyHidden(table, hidden);
}

export function initializeTupadTables(root = document) {
    root.querySelectorAll('main table').forEach((table, position) => {
        if (table.dataset.tableEnhanced || table.hasAttribute('data-no-enhance')) return;

        const headers = headerCells(table);
        if (!hasSimpleStructure(table, headers)) return;

        table.dataset.tableEnhanced = 'true';

        const hasControls = table.tBodies[0].querySelector('input:not([type="hidden"]), select, textarea') !== null;

        if (!hasControls && !table.hasAttribute('data-no-sort') && dataRows(table).length > 1) {
            makeSortable(table, headers);
        }
        if (!table.hasAttribute('data-no-sticky')) makeSticky(table);
        if (!hasControls && !table.hasAttribute('data-no-total')) addTotals(table, headers);
        if (!table.hasAttribute('data-no-columns')) addColumnMenu(table, headers, position);
    });
}
