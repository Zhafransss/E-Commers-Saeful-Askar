/**
 * Frontend side of the contract check (the backend side is ContractRoutesTest).
 *
 *   1. contract.json is up to date with docs/api (no stale mocks).
 *   2. Every frontend endpoint in the contract has a path in endpoints.js, and
 *      every path in endpoints.js exists in the contract.
 *   3. Fields read by the Alpine stores exist in the contract responses.
 *
 * Run: npm run contract:check   (exit code 1 on any mismatch)
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { buildMocks, mocksFile, root, serialize } from './generate-api-mocks.mjs';

const failures = [];
const check = (ok, message) => ok || failures.push(message);

// 1. Stale mocks ------------------------------------------------------------------
const { mocks, problems } = buildMocks();
problems.forEach((problem) => failures.push(`docs/api: ${problem}`));
check(
    readFileSync(mocksFile, 'utf8') === serialize(mocks),
    'resources/js/api/mocks/contract.json is out of date with docs/api — run `npm run mocks:generate`.',
);

// 2. endpoints.js <-> contract ----------------------------------------------------
const { endpoints } = await import(pathToFileURL(join(root, 'resources', 'js', 'api', 'endpoints.js')).href);

const SAMPLE = 'SAMPLE';
const paths = [];
(function collect(node, name) {
    if (typeof node === 'string') paths.push({ name, path: node });
    else if (typeof node === 'function') paths.push({ name, path: node(SAMPLE) });
    else Object.entries(node).forEach(([key, child]) => collect(child, name ? `${name}.${key}` : key));
})(endpoints, '');

const templates = Object.keys(mocks)
    .map((key) => key.split(' ')[1])
    .filter((path) => path.startsWith('/api/v1'))
    .map((path) => path.slice('/api/v1'.length));
const toRegex = (template) => new RegExp(`^${template.replace(/\{\w+\}/g, '[^/]+')}$`);

for (const template of new Set(templates)) {
    check(
        paths.some(({ path }) => toRegex(template).test(path)),
        `Contract path ${template} has no entry in endpoints.js.`,
    );
}
for (const { name, path } of paths) {
    check(
        templates.some((template) => toRegex(template).test(path)),
        `endpoints.${name} (${path}) is not in the API Contract.`,
    );
}

// The CSRF endpoint is called by client.js directly, outside endpoints.js.
check(
    readFileSync(join(root, 'resources', 'js', 'api', 'client.js'), 'utf8').includes('/sanctum/csrf-cookie') &&
        'GET /sanctum/csrf-cookie' in mocks,
    'client.js CSRF URL does not match AUTH-01.',
);

// 3. Store assumptions --------------------------------------------------------------
const body = (key) => mocks[key]?.body;
const has = (object, path) => path.split('.').reduce((value, key) => (value == null ? undefined : value[key]), object) !== undefined;

const storeReads = [
    ['stores/auth.js', 'GET /api/v1/auth/me', 'data.role'],
    ['stores/cart.js', 'GET /api/v1/cart/count', 'data.total_qty'],
    ['stores/cart.js', 'GET /api/v1/cart/count', 'data.has_issue'],
    ['stores/cart.js setFromCart()', 'GET /api/v1/cart', 'data.summary.total_qty'],
    ['stores/cart.js setFromCart()', 'POST /api/v1/cart/items', 'data.summary.has_issue'],
];
for (const [reader, key, field] of storeReads) {
    check(has(body(key), field), `${reader} reads ${field} from ${key}, but the contract example has no such field.`);
}

// --------------------------------------------------------------------------------
if (failures.length) {
    console.error(`Contract check failed (${failures.length}):\n- ${failures.join('\n- ')}`);
    process.exit(1);
}
console.log(`Contract check passed: ${Object.keys(mocks).length} mocks, ${paths.length} endpoints.js paths, ${storeReads.length} store fields.`);
