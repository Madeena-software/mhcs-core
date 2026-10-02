/**
 * Operator Worklist Filters
 *
 * Provides client-side searching and filtering for:
 * 1. Patient name and examination/ticket/session/locator code search (case-insensitive substring)
 * 2. Date from/to range filtering on row timestamp (YYYY-MM-DD)
 * 3. Status filter dropdown
 * 4. Filter reset button
 * 5. Matching result count display and filtered-empty state
 * 6. Preserves URL query parameters via history.replaceState across the 5s auto-refresh
 * 7. On study-results worklist: synchronizes selection and batch download so hidden/filtered rows are not selected/submitted.
 */

export function parseDateOnly(value) {
    if (typeof value !== 'string') return null;
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?)?$/);
    if (!match || Number(match[1]) === 0) return null;
    const date = `${match[1]}-${match[2]}-${match[3]}`;
    const parsed = new Date(`${date}T00:00:00Z`);
    return !Number.isNaN(parsed.getTime()) && parsed.toISOString().slice(0, 10) === date ? date : null;
}

export function filterWorklistRow(row, criteria) {
    const { query = '', dateFrom = '', dateTo = '', status = '' } = criteria;

    // 1. Search text (case-insensitive substring match)
    if (query.trim() !== '') {
        const searchText = (row.dataset.searchText || '').toLowerCase();
        const terms = query.trim().toLowerCase();
        if (!searchText.includes(terms)) {
            return false;
        }
    }

    // 2. Date range filter
    const rowDate = parseDateOnly(row.dataset.rowDate);
    const parsedFrom = parseDateOnly(dateFrom);
    const parsedTo = parseDateOnly(dateTo);

    if ((dateFrom && !parsedFrom) || (dateTo && !parsedTo)) return false;

    if (parsedFrom && parsedTo && parsedFrom > parsedTo) {
        // If date range is inverted (from > to), no rows match
        return false;
    }

    if (parsedFrom) {
        if (!rowDate || rowDate < parsedFrom) {
            return false;
        }
    }

    if (parsedTo) {
        if (!rowDate || rowDate > parsedTo) {
            return false;
        }
    }

    // 3. Status filter
    if (status && status.trim() !== '') {
        const rowStatus = (row.dataset.rowStatus || '').trim();
        if (rowStatus !== status.trim()) {
            return false;
        }
    }

    return true;
}

export function initWorklistFilters(container = document) {
    const filterBar = container.querySelector('[data-worklist-filter-bar]');
    if (!filterBar) return null;

    const table = container.querySelector(filterBar.dataset.targetTable || 'table');
    if (!table) return null;

    const tbody = table.querySelector('tbody');
    if (!tbody) return null;

    const queryInput = filterBar.querySelector('[data-filter-query]');
    const dateFromInput = filterBar.querySelector('[data-filter-date-from]');
    const dateToInput = filterBar.querySelector('[data-filter-date-to]');
    const statusSelect = filterBar.querySelector('[data-filter-status]');
    const resetButton = filterBar.querySelector('[data-filter-reset]');
    const countDisplay = filterBar.querySelector('[data-filter-count]');
    const errorDisplay = filterBar.querySelector('[data-filter-error]');
    const messages = JSON.parse(container.querySelector('[data-operator-list-messages]')?.textContent || '{}');
    const selectionForm = container.querySelector('[data-study-selection]');
    const selectAll = selectionForm?.querySelector('[data-select-all]');
    const visibleCheckboxes = () => [...table.querySelectorAll('tr[data-worklist-row]:not([hidden]) input[name="studies[]"]')];
    const syncSelection = () => {
        if (!selectAll) return;
        const checkboxes = visibleCheckboxes();
        const checked = checkboxes.filter(checkbox => checkbox.checked).length;
        selectAll.checked = checked > 0 && checked === checkboxes.length;
        selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
    };

    const emptyRowTemplate = tbody.querySelector('[data-empty-filtered-row]');
    const initialEmptyRow = tbody.querySelector('[data-empty-initial-row]');

    const getRows = () => [...tbody.querySelectorAll('tr[data-worklist-row]')];

    // Read initial values from URL search params
    const getUrlParams = () => {
        if (typeof window === 'undefined' || !window.location) return new URLSearchParams();
        return new URLSearchParams(window.location.search);
    };

    const updateUrlParams = (criteria) => {
        if (typeof window === 'undefined' || !window.history || !window.location) return;
        const url = new URL(window.location.href);
        const { query, dateFrom, dateTo, status } = criteria;

        if (query) url.searchParams.set('q', query);
        else url.searchParams.delete('q');

        if (dateFrom) url.searchParams.set('date_from', dateFrom);
        else url.searchParams.delete('date_from');

        if (dateTo) url.searchParams.set('date_to', dateTo);
        else url.searchParams.delete('date_to');

        if (status) url.searchParams.set('status', status);
        else url.searchParams.delete('status');

        window.history.replaceState({}, '', url.toString());
    };

    const populateInputsFromUrl = () => {
        const params = getUrlParams();
        if (queryInput && params.has('q')) queryInput.value = params.get('q');
        if (dateFromInput) dateFromInput.value = /^\d{4}-\d{2}-\d{2}$/.test(params.get('date_from') || '') && parseDateOnly(params.get('date_from')) ? params.get('date_from') : '';
        if (dateToInput) dateToInput.value = /^\d{4}-\d{2}-\d{2}$/.test(params.get('date_to') || '') && parseDateOnly(params.get('date_to')) ? params.get('date_to') : '';
        if (statusSelect) statusSelect.value = [...statusSelect.options].some(option => option.value === params.get('status')) ? params.get('status') : '';
    };

    const getCriteria = () => ({
        query: queryInput ? queryInput.value : '',
        dateFrom: dateFromInput ? dateFromInput.value : '',
        dateTo: dateToInput ? dateToInput.value : '',
        status: statusSelect ? statusSelect.value : '',
    });

    const applyFilters = () => {
        const criteria = getCriteria();
        const rows = getRows();
        const total = rows.length;

        const invertedRange = Boolean(criteria.dateFrom && criteria.dateTo && criteria.dateFrom > criteria.dateTo);
        if (errorDisplay) {
            errorDisplay.textContent = invertedRange ? messages.invalidRange : '';
            errorDisplay.hidden = !invertedRange;
        }

        let visibleCount = 0;
        rows.forEach((row) => {
            const matches = filterWorklistRow(row, criteria);
            if (matches) {
                row.hidden = false;
                row.removeAttribute('aria-hidden');
                visibleCount++;
            } else {
                row.hidden = true;
                row.setAttribute('aria-hidden', 'true');
                // If row has a study selection checkbox, uncheck it when hidden
                const checkbox = row.querySelector('input[name="studies[]"]');
                if (checkbox) {
                    checkbox.checked = false;
                }
            }
        });

        // Toggle filtered empty state row
        if (emptyRowTemplate) {
            emptyRowTemplate.hidden = total === 0 || visibleCount > 0;
        }

        // Hide server initial empty row if filtered
        if (initialEmptyRow) {
            initialEmptyRow.hidden = (total > 0);
        }

        // Update count indicator
        if (countDisplay) {
            const hasActiveFilter = Boolean(criteria.query || criteria.dateFrom || criteria.dateTo || criteria.status);
            if (hasActiveFilter) {
                countDisplay.textContent = (messages.filteredCount || '').replace(':visible', visibleCount).replace(':total', total);
                countDisplay.hidden = false;
            } else {
                countDisplay.textContent = (messages.totalCount || '').replace(':total', total);
                countDisplay.hidden = false;
            }
        }

        // Sync with URL params so 5s auto-refresh preserves state
        updateUrlParams(criteria);

        syncSelection();

        // Notify study-selection handlers if present
        table.dispatchEvent(new CustomEvent('worklist:filtered', { detail: { visibleCount, total } }));
    };

    const resetFilters = () => {
        if (queryInput) queryInput.value = '';
        if (dateFromInput) dateFromInput.value = '';
        if (dateToInput) dateToInput.value = '';
        if (statusSelect) statusSelect.value = '';
        applyFilters();
    };

    // Bind event listeners
    if (queryInput) queryInput.addEventListener('input', applyFilters);
    if (dateFromInput) dateFromInput.addEventListener('change', applyFilters);
    if (dateToInput) dateToInput.addEventListener('change', applyFilters);
    if (statusSelect) statusSelect.addEventListener('change', applyFilters);
    if (resetButton) resetButton.addEventListener('click', resetFilters);

    // Initial run
    populateInputsFromUrl();
    applyFilters();

    if (selectionForm) {
        selectAll?.addEventListener('change', () => {
            visibleCheckboxes().forEach(checkbox => { checkbox.checked = selectAll.checked; });
            syncSelection();
        });
        selectionForm.addEventListener('change', syncSelection);
        selectionForm.addEventListener('submit', event => {
            table.querySelectorAll('tr[data-worklist-row][hidden] input[name="studies[]"]').forEach(checkbox => { checkbox.checked = false; });
            if (!visibleCheckboxes().some(checkbox => checkbox.checked)) event.preventDefault();
            syncSelection();
        });
    }

    return {
        applyFilters,
        resetFilters,
        getCriteria,
    };
}
