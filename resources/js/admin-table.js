import { createTable, getCoreRowModel, functionalUpdate } from '@tanstack/table-core';

export const columnIds = ['business_name', 'category', 'city', 'state', 'status', 'created_at'];

export function tableUrl(current, { sorting, pagination, visibility } = {}) {
    const url = new URL(current);
    if (sorting) {
        url.searchParams.set('sort', sorting[0]?.id ?? 'created_at');
        url.searchParams.set('direction', sorting[0]?.desc ? 'desc' : 'asc');
        url.searchParams.set('page', '1');
    }
    if (pagination) {
        url.searchParams.set('page', String(pagination.pageIndex + 1));
        url.searchParams.set('per_page', String(pagination.pageSize));
    }
    if (visibility) {
        [...url.searchParams.keys()].filter((key) => key === 'columns' || key.startsWith('columns[')).forEach((key) => url.searchParams.delete(key));
        columnIds.filter((key) => key === 'business_name' || visibility[key] !== false).forEach((key) => url.searchParams.append('columns[]', key));
    }
    return url;
}

export function installAdminTable(root = document) {
    const shell = root.querySelector('[data-admin-table-shell]');
    if (!shell) return;
    let table;
    let controller;
    let retryUrl;
    const loading = shell.querySelector('[data-table-loading]');
    const error = shell.querySelector('[data-table-error]');

    const applyVisibility = () => {
        const visible = new Set(table.getVisibleLeafColumns().map((column) => column.id));
        shell.querySelectorAll('[data-column]').forEach((cell) => { cell.hidden = !visible.has(cell.dataset.column); });
    };

    const initialize = () => {
        const container = shell.querySelector('[data-table-container]');
        const rows = [...container.querySelectorAll('[data-record-row]')];
        const data = rows.map((row) => Object.fromEntries(columnIds.map((id) => [id, row.querySelector(`[data-column="${id}"]`)?.textContent.trim()])));
        let state = {
            sorting: [{ id: container.dataset.sort, desc: container.dataset.direction === 'desc' }],
            pagination: { pageIndex: Number(container.dataset.page) - 1, pageSize: Number(container.dataset.pageSize) },
            columnVisibility: Object.fromEntries([...shell.querySelectorAll('[data-column-toggle]')].map((input) => [input.dataset.columnToggle, input.checked])),
        };
        table = createTable({
            data, columns: columnIds.map((id) => ({ accessorKey: id, enableHiding: id !== 'business_name' })),
            getCoreRowModel: getCoreRowModel(), manualPagination: true, manualSorting: true, manualFiltering: true,
            rowCount: Number(container.dataset.total), enableSortingRemoval: false,
            state,
            onStateChange(updater) {
                const previous = state;
                state = functionalUpdate(updater, state);
                table.setOptions((options) => ({ ...options, state }));
                if (previous.columnVisibility !== state.columnVisibility) {
                    applyVisibility();
                    const url = tableUrl(window.location.href, { visibility: state.columnVisibility });
                    history.replaceState(null, '', url);
                    shell.querySelectorAll('.crm-record-link, .crm-context-link, [data-table-pagination] a, [data-sort-column]').forEach((link) => {
                        link.href = tableUrl(link.href, { visibility: state.columnVisibility });
                    });
                    const form = shell.querySelector('[data-table-filters]');
                    form.querySelectorAll('[name="columns[]"]').forEach((input) => input.remove());
                    columnIds.filter((id) => state.columnVisibility[id] !== false).forEach((id) => {
                        const input = document.createElement('input');
                        input.type = 'hidden'; input.name = 'columns[]'; input.value = id; form.append(input);
                    });
                } else if (previous.sorting !== state.sorting) {
                    load(tableUrl(window.location.href, { sorting: state.sorting }));
                } else if (previous.pagination !== state.pagination) {
                    load(tableUrl(window.location.href, { pagination: state.pagination }));
                }
            },
        });
        table.getRowModel().rows.forEach((row) => { rows[row.index].dataset.tableRowId = row.id; });
        applyVisibility();
    };

    async function load(destination, push = true) {
        const url = new URL(destination, window.location.href);
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) return;
        controller?.abort();
        const active = new AbortController(); controller = active; retryUrl = url;
        loading.hidden = false; error.hidden = true; shell.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: active.signal });
            if (!response.ok || response.redirected) throw new Error('Unable to load table');
            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const region = parsed.querySelector('[data-table-region]');
            if (!region) throw new Error('Invalid table response');
            if (active !== controller) return;
            shell.querySelector('[data-table-region]').replaceWith(region);
            if (push) history.pushState(null, '', url);
            initialize();
            shell.querySelector('#table-result-count')?.focus();
        } catch (exception) {
            if (exception.name !== 'AbortError' && active === controller) error.hidden = false;
        } finally {
            if (active === controller) { loading.hidden = true; shell.removeAttribute('aria-busy'); }
        }
    }

    shell.addEventListener('submit', (event) => {
        if (!event.target.matches('[data-table-filters]')) return;
        event.preventDefault();
        const url = new URL(event.target.action);
        url.search = new URLSearchParams(new FormData(event.target));
        load(url);
    });
    shell.addEventListener('change', (event) => {
        if (event.target.matches('[data-column-toggle]')) table.getColumn(event.target.dataset.columnToggle)?.toggleVisibility(event.target.checked);
        if (event.target.matches('select[data-page-size]')) {
            const form = event.target.form;
            const url = new URL(form.action);
            url.search = new URLSearchParams(new FormData(form));
            load(url);
        }
    });
    shell.addEventListener('click', (event) => {
        if (event.target.closest('[data-table-retry]')) { load(retryUrl); return; }
        const link = event.target.closest('a');
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        if (link?.matches('[data-sort-column]')) { event.preventDefault(); table.getColumn(link.dataset.sortColumn).toggleSorting(); return; }
        if (link?.closest('[data-table-pagination]')) { event.preventDefault(); load(link.href); return; }
        const row = event.target.closest('[data-record-row]');
        if (row && !event.target.closest('a,button,input,select,textarea,label,[data-row-actions]') && !window.getSelection()?.toString()) window.location.assign(row.querySelector('a').href);
    });
    window.addEventListener('popstate', () => load(window.location.href, false));
    initialize();
}
