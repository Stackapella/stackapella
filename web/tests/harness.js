// A tiny test runner (no npm, so no Vitest): test(), expect helpers, and a report the headless
// runner (tool/test.sh) reads from window.testReport.
const tests = [];

export function test(name, body) {
    tests.push({ name, body });
}

export function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

export function close(actual, expected, tolerance, what = 'value') {
    assert(Math.abs(actual - expected) <= tolerance, `${what}: expected ${expected} (within ${tolerance}), got ${actual}`);
}

export async function run() {
    const results = [];
    for (const t of tests) {
        const started = performance.now();
        try {
            await t.body();
            results.push({ name: t.name, ok: true, ms: Math.round(performance.now() - started) });
        } catch (error) {
            results.push({ name: t.name, ok: false, error: String(error?.stack ?? error) });
        }
        render(results);
    }
    window.testReport = { passed: results.every((r) => r.ok), results, userAgent: navigator.userAgent };
    return window.testReport;
}

function render(results) {
    document.getElementById('results').innerHTML = results
        .map((r) => `<li style="color:${r.ok ? '#93c26e' : '#f0654a'}">${r.ok ? 'ok' : 'FAIL'} ${escape(r.name)}${r.ok ? '' : `<pre>${escape(r.error)}</pre>`}</li>`)
        .join('');
}

function escape(text) {
    return text.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[c]);
}
