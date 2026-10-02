import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import { chromium } from 'playwright';

// Entirely synthetic DOM; every request is intercepted, no live application or service.
const source = fs.readFileSync('resources/js/operator-worklist-filters.js', 'utf8').replaceAll('export function', 'function');
const messages = { filteredCount: 'MATCH :visible/:total', totalCount: 'ROWS :total', invalidRange: 'INVALID RANGE' };
function html(kind, empty = false) {
    const states = kind === 'verification' ? ['unclaimed', 'open', 'matched', 'nonclinical_validation', 'mismatch_reported', 'insufficient_evidence', 'cancelled'] : kind === 'study' ? ['report_ready', 'queued', 'processing', 'retryable_failure', 'terminal_failure', 'not_queued'] : kind === 'basic' ? ['waiting', 'called', 'in_service'] : ['waiting', 'called', 'dicom_processing_failed'];
    const rows = empty ? '' : states.map((state, i) => `<tr data-worklist-row data-search-text="${i ? 'Siti' : 'Budi'} RM-000${i} SESSION-0007" data-row-date="2026-10-0${i % 2 + 1}" data-row-status="${state}"><td><input name="studies[]" type="checkbox" value="${i}"></td></tr>`).join('');
    const legacy = kind === 'study' ? fs.readFileSync('resources/views/operator/study-results.blade.php', 'utf8').match(/<script>([\s\S]*?)<\/script>/)?.[1] || '' : '';
    return `<script type="application/json" data-operator-list-messages>${JSON.stringify(messages)}</script>
    <div data-worklist-filter-bar data-target-table="#list"><input data-filter-query><input type="date" data-filter-date-from><input type="date" data-filter-date-to><select data-filter-status><option value=""></option>${states.map(s => `<option>${s}</option>`).join('')}</select><button data-filter-reset>Reset</button><span data-filter-count hidden></span><span data-filter-error role="alert" hidden></span></div>
    <form data-study-selection><input type="checkbox" data-select-all><table id="list"><tbody>${rows}${empty ? '<tr data-empty-initial-row><td>Initial empty</td></tr>' : ''}<tr data-empty-filtered-row hidden><td>No match</td></tr></tbody></table></form><script>${legacy}</script>`;
}
async function setup(t, kind, empty, query = '') {
    const browser = await chromium.launch({ headless: true });
    t.after(() => browser.close());
    const page = await browser.newPage();
    await page.route('**/*', route => route.fulfill({ contentType: 'text/html', body: html(kind, empty) }));
    await page.goto(`http://worklist.test/?${query}`);
    await page.addScriptTag({ content: `${source}\nwindow.filters = initWorklistFilters(document);` });
    return page;
}
for (const kind of ['study', 'verification', 'basic', 'xray']) {
    test(`${kind}: actual DOM combines criteria, statuses, reset, empty matches and refresh URL`, async t => {
        const page = await setup(t, kind, false, 'q=0007&date_from=2026-10-01&date_to=2026-10-01');
        assert.equal(await page.locator('tr[data-worklist-row]:not([hidden])').count(), Math.ceil((await page.locator('[data-filter-status] option').count() - 1) / 2));
        const options = await page.locator('[data-filter-status] option').evaluateAll(options => options.map(o => o.value).filter(Boolean));
        for (const state of options) {
            await page.locator('[data-filter-reset]').click();
            await page.locator('[data-filter-status]').selectOption(state);
            assert.deepEqual(await page.locator('tr[data-worklist-row]:not([hidden])').evaluateAll(rows => rows.map(r => r.dataset.rowStatus)), [state]);
        }
        await page.locator('[data-filter-query]').fill('missing');
        assert.equal(await page.locator('tr[data-worklist-row]:not([hidden])').count(), 0);
        assert.equal(await page.locator('[data-empty-filtered-row]').isVisible(), true);
        await page.reload();
        await page.addScriptTag({ content: `${source}\nwindow.filters = initWorklistFilters(document);` });
        assert.equal(await page.locator('[data-filter-query]').inputValue(), 'missing');
        assert.equal(await page.locator('tr[data-worklist-row]:not([hidden])').count(), 0);
        await page.locator('[data-filter-reset]').click();
        assert.equal(new URL(await page.evaluate(() => location.href)).search, '');
        assert.equal(await page.locator('tr[data-worklist-row]:not([hidden])').count(), options.length);
        assert.equal(await page.locator('[data-filter-count]').textContent(), `ROWS ${options.length}`);
    });
}
test('empty server list updates count, URL, and reset; refresh-arriving row obeys saved filter', async t => {
    const page = await setup(t, 'basic', true, 'q=absent&unrelated=keep');
    assert.equal(await page.locator('[data-filter-count]').textContent(), 'MATCH 0/0');
    assert.equal(await page.locator('[data-empty-initial-row]').isVisible(), true);
    assert.equal(await page.locator('[data-empty-filtered-row]').isVisible(), false);
    await page.locator('[data-filter-query]').fill('Budi');
    assert.equal(new URL(await page.evaluate(() => location.href)).searchParams.get('q'), 'Budi');
    await page.route('**/*', route => route.fulfill({ contentType: 'text/html', body: html('basic') }));
    await page.reload();
    await page.addScriptTag({ content: `${source}\nwindow.filters = initWorklistFilters(document);` });
    assert.equal(await page.locator('tr[data-worklist-row]:not([hidden])').count(), 1);
    await page.locator('[data-filter-reset]').click();
    assert.equal(new URL(await page.evaluate(() => location.href)).search, '?unrelated=keep');
});
test('visible-only selection, indeterminate state and actual ZIP FormData exclude hidden checks', async t => {
    const page = await setup(t, 'study', false);
    await page.locator('input[name="studies[]"]').first().check();
    assert.equal(await page.locator('[data-select-all]').evaluate(el => el.indeterminate), true);
    await page.locator('[data-filter-query]').fill('Budi');
    await page.locator('[data-select-all]').check();
    assert.equal(await page.locator('tr[hidden] input:checked').count(), 0);
    const submission = await page.evaluate(() => {
        const form = document.querySelector('[data-study-selection]');
        form.dispatchEvent(new Event('submit', { cancelable: true }));
        return new FormData(form).getAll('studies[]');
    });
    assert.deepEqual(submission, ['0']);
    assert.equal(await page.locator('tr[hidden] input:checked').count(), 0);
    await page.locator('[data-filter-query]').fill('missing');
    assert.equal(await page.locator('[data-select-all]').isChecked(), false);
    assert.equal(await page.evaluate(() => document.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))), false);
});
test('malformed URL dates/status are removed; inverted range announces registry copy and reset clears it', async t => {
    const page = await setup(t, 'basic', false, 'date_from=2026-02-30&date_to=2026-10-02junk&status=invented');
    assert.equal(new URL(await page.evaluate(() => location.href)).search, '');
    await page.locator('[data-filter-date-from]').fill('2026-10-03');
    await page.locator('[data-filter-date-to]').fill('2026-10-01');
    assert.equal(await page.locator('[data-filter-error]').textContent(), 'INVALID RANGE');
    assert.equal(await page.locator('[data-filter-error]').isVisible(), true);
    assert.equal(await page.locator('tr[data-worklist-row]:not([hidden])').count(), 0);
    await page.locator('[data-filter-reset]').click();
    assert.equal(await page.locator('[data-filter-error]').isVisible(), false);
});

test('select-all after filtering exports exactly visible IDs despite hidden rows', async t => {
    const page = await setup(t, 'study', false);
    await page.locator('[data-filter-query]').fill('Budi');
    await page.locator('[data-select-all]').check();
    assert.equal(await page.locator('tr[hidden] input:checked').count(), 0);
    assert.deepEqual(await page.evaluate(() => {
        const form = document.querySelector('form');
        form.dispatchEvent(new Event('submit', { cancelable: true }));
        return new FormData(form).getAll('studies[]');
    }), ['0']);
});

test('existing automatic refresh callback retains empty-list filters for newly arriving records', async t => {
    const page = await setup(t, 'basic', true);
    await page.locator('[data-filter-query]').fill('Budi');
    await page.evaluate(() => {
        const marker = document.createElement('section');
        marker.dataset.worklistAutoRefresh = '';
        document.body.append(marker);
        window.setInterval = (callback, delay) => { window.refreshWorklist = callback; window.refreshDelay = delay; return 1; };
    });
    const refresh = fs.readFileSync('resources/views/operator/layout.blade.php', 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
    await page.addScriptTag({ content: refresh });
    assert.equal(await page.evaluate(() => window.refreshDelay), 5000);
    await page.route('**/*', route => route.fulfill({ contentType: 'text/html', body: html('basic') }));
    await Promise.all([page.waitForEvent('load'), page.evaluate(() => window.refreshWorklist())]);
    await page.addScriptTag({ content: `${source}\nwindow.filters = initWorklistFilters(document);` });
    assert.equal(await page.locator('[data-filter-query]').inputValue(), 'Budi');
    assert.equal(await page.locator('tr[data-worklist-row]:not([hidden])').count(), 1);
    assert.equal(await page.locator('[data-filter-count]').textContent(), 'MATCH 1/3');
});
