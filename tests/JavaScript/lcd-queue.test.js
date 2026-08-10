import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const template = readFileSync('resources/views/lcd/queue.blade.php', 'utf8');
const script = template.match(/<script>\n([\s\S]*?)\n<\/script>/)[1].replace(
    "const endpoint = @json(route('lcd.queue', $siteId));",
    "const endpoint = 'http://localhost/lcd/site/queue';",
);

test('LCD shows stale state after refresh failure and clears it after recovery', async () => {
    const status = { hidden: true };
    const elements = {
        'queue-status': status,
        'current-calls': { replaceChildren() {}, append() {} },
        'recent-calls': { replaceChildren() {}, append() {} },
    };
    const context = {
        document: {
            getElementById: (id) => elements[id],
            createElement: () => ({ className: '', textContent: '', append() {} }),
        },
        fetch: async () => { throw new Error('synthetic disconnect'); },
        setInterval: (callback) => { context.refresh = callback; },
    };

    vm.runInNewContext(script, context);
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(status.hidden, false);

    context.fetch = async () => ({
        ok: true,
        json: async () => ({ current: [], recent_calls: [] }),
    });
    await context.refresh();
    assert.equal(status.hidden, true);
});
