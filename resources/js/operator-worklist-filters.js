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

export function parseDateOnly(dateStr) {
    if (!dateStr || typeof dateStr !== 'string') return null;
    const match = dateStr.trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? `${match[1]}-${match[2]}-${match[3]}` : null;
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
        if (dateFromInput && params.has('date_from')) dateFromInput.value = params.get('date_from');
        if (dateToInput && params.has('date_to')) dateToInput.value = params.get('date_to');
        if (statusSelect && params.has('status')) statusSelect.value = params.get('status');
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

        if (total === 0) {
            // Already empty from server
            return;
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
            emptyRowTemplate.hidden = (visibleCount > 0);
        }

        // Hide server initial empty row if filtered
        if (initialEmptyRow) {
            initialEmptyRow.hidden = (total > 0);
        }

        // Update count indicator
        if (countDisplay) {
            const hasActiveFilter = Boolean(criteria.query || criteria.dateFrom || criteria.dateTo || criteria.status);
            if (hasActiveFilter) {
                countDisplay.textContent = `Menampilkan ${visibleCount} dari ${total}`;
                countDisplay.hidden = false;
            } else {
                countDisplay.textContent = `Total: ${total}`;
                countDisplay.hidden = false;
            }
        }

        // Sync with URL params so 5s auto-refresh preserves state
        updateUrlParams(criteria);

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

    // Setup study selection sync for batch downloads
    const selectionForm = container.querySelector('[data-study-selection]');
    if (selectionForm) {
        const selectAllCheckbox = selectionForm.querySelector('[data-select-all]');
        if (selectAllCheckbox) {
            // Replace click/change behavior to only select currently visible rows
            selectAllCheckbox.onclick = (e) => {
                const visibleCheckboxes = [...selectionForm.querySelectorAll('tr[data-worklist-row]:not([hidden]) input[name="studies[]"]')];
                visibleCheckboxes.forEach((cb) => {
                    cb.checked = selectAllCheckbox.checked;
                });
            };
        }

        // Ensure on submit, any hidden row checkbox is unchecked or disabled
        selectionForm.addEventListener('submit', (e) => {
            const hiddenCheckboxes = [...selectionForm.querySelectorAll('tr[data-worklist-row][hidden] input[name="studies[]"]')];
            hiddenCheckboxes.forEach((cb) => {
                cb.checked = false;
            });
            const visibleChecked = [...selectionForm.querySelectorAll('tr[data-worklist-row]:not([hidden]) input[name="studies[]"]:checked')];
            if (visibleChecked.length === 0) {
                e.preventDefault();
            }
        });
    }

    return {
        applyFilters,
        resetFilters,
        getCriteria,
    };
}
