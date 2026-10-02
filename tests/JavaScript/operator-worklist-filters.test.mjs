import assert from 'node:assert/strict';
import test from 'node:test';
import {
    parseDateOnly,
    filterWorklistRow,
    initWorklistFilters,
} from '../../resources/js/operator-worklist-filters.js';

test('parseDateOnly extracts YYYY-MM-DD correctly', () => {
    assert.equal(parseDateOnly('2026-10-02'), '2026-10-02');
    assert.equal(parseDateOnly('2026-10-02 08:30:00'), '2026-10-02');
    assert.equal(parseDateOnly('2026-10-02T14:20:00Z'), '2026-10-02');
    assert.equal(parseDateOnly(''), null);
    assert.equal(parseDateOnly(null), null);
    assert.equal(parseDateOnly('invalid-date'), null);
});

test('filterWorklistRow matches case-insensitive search substring and preserves leading zeros', () => {
    const row = {
        dataset: {
            searchText: 'Budi Santoso RM-00123 DCM-0042 SES-0007',
            rowDate: '2026-10-02',
            rowStatus: 'completed',
        },
    };

    // Substring match
    assert.equal(filterWorklistRow(row, { query: 'budi' }), true);
    assert.equal(filterWorklistRow(row, { query: 'BUDI' }), true);
    assert.equal(filterWorklistRow(row, { query: 'santoso' }), true);

    // Leading zero preservation for ticket/session locator
    assert.equal(filterWorklistRow(row, { query: '0042' }), true);
    assert.equal(filterWorklistRow(row, { query: '0007' }), true);
    assert.equal(filterWorklistRow(row, { query: '9999' }), false);
    assert.equal(filterWorklistRow(row, { query: 'Ahmad' }), false);
});

test('filterWorklistRow handles date range boundaries and inverted dates', () => {
    const row = {
        dataset: {
            searchText: 'Jane Doe',
            rowDate: '2026-10-02',
            rowStatus: 'verified',
        },
    };

    // Exact date
    assert.equal(filterWorklistRow(row, { dateFrom: '2026-10-02', dateTo: '2026-10-02' }), true);

    // Range includes date
    assert.equal(filterWorklistRow(row, { dateFrom: '2026-10-01', dateTo: '2026-10-03' }), true);
    assert.equal(filterWorklistRow(row, { dateFrom: '2026-10-02' }), true);
    assert.equal(filterWorklistRow(row, { dateTo: '2026-10-02' }), true);

    // Date out of bounds
    assert.equal(filterWorklistRow(row, { dateFrom: '2026-10-03' }), false);
    assert.equal(filterWorklistRow(row, { dateTo: '2026-10-01' }), false);

    // Inverted date range (from > to) rejects all
    assert.equal(filterWorklistRow(row, { dateFrom: '2026-10-05', dateTo: '2026-10-01' }), false);
});

test('filterWorklistRow matches exact status', () => {
    const row = {
        dataset: {
            searchText: 'John Doe',
            rowDate: '2026-10-02',
            rowStatus: 'report_ready',
        },
    };

    assert.equal(filterWorklistRow(row, { status: 'report_ready' }), true);
    assert.equal(filterWorklistRow(row, { status: 'processing' }), false);
    assert.equal(filterWorklistRow(row, { status: '' }), true);
});

test('filterWorklistRow combines criteria conjunctively', () => {
    const row = {
        dataset: {
            searchText: 'Siti Aminah DCM-1001',
            rowDate: '2026-10-02',
            rowStatus: 'ready',
        },
    };

    assert.equal(filterWorklistRow(row, {
        query: 'siti',
        dateFrom: '2026-10-01',
        dateTo: '2026-10-03',
        status: 'ready',
    }), true);

    // Fails on query
    assert.equal(filterWorklistRow(row, {
        query: 'budi',
        dateFrom: '2026-10-01',
        dateTo: '2026-10-03',
        status: 'ready',
    }), false);

    // Fails on status
    assert.equal(filterWorklistRow(row, {
        query: 'siti',
        dateFrom: '2026-10-01',
        dateTo: '2026-10-03',
        status: 'in_progress',
    }), false);
});

test('date parser rejects impossible calendar days, date suffixes and malformed criteria', () => {
    for (const value of ['2026-02-30', '2026-13-01', '2026-00-01', '2026-10-02junk', '2026-10-021', '0000-01-01']) {
        assert.equal(parseDateOnly(value), null, value);
        assert.equal(filterWorklistRow({ dataset: { rowDate: '2026-10-02' } }, { dateFrom: value }), false, value);
    }
    assert.equal(parseDateOnly('2024-02-29'), '2024-02-29');
    assert.equal(parseDateOnly('2026-02-29'), null);
});
