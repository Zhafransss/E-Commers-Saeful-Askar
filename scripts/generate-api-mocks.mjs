/**
 * Builds resources/js/api/mocks/contract.json from the API Contract.
 *
 * For every frontend endpoint in docs/api/README.md ("Indeks endpoint"), it takes
 * the HTTP status and the first JSON example under "### Response Success" in the
 * module file. Endpoints marked (v1.1) are skipped.
 *
 * Run after any contract change: npm run mocks:generate
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const docs = join(root, 'docs', 'api');
const output = join(root, 'resources', 'js', 'api', 'mocks', 'contract.json');

const readme = readFileSync(join(docs, 'README.md'), 'utf8');
const frontendIndex = readme.split('### Untuk frontend')[1].split('### Webhook masuk')[0];

// | CODE | METHOD | `path` ... | Auth | file.md | PRD |
const rowPattern = /^\| ([A-Z]+-\d+)( \(v1\.1\))? \| (GET|POST|PUT|PATCH|DELETE) \| `([^`]+)`[^|]* \| [^|]+ \| ([\w-]+\.md) \|/gm;

const sectionCache = new Map();
function sectionsOf(file) {
    if (!sectionCache.has(file)) {
        const text = readFileSync(join(docs, file), 'utf8');
        const sections = new Map();
        for (const part of text.split(/^## /m).slice(1)) {
            const code = part.match(/^([A-Z]+-\d+) /)?.[1];
            if (code) sections.set(code, part);
        }
        sectionCache.set(file, sections);
    }
    return sectionCache.get(file);
}

const mocks = {};
const problems = [];

for (const [, code, deferred, method, path, file] of frontendIndex.matchAll(rowPattern)) {
    if (deferred) continue;

    const section = sectionsOf(file).get(code);
    if (!section) {
        problems.push(`${code}: section not found in ${file}`);
        continue;
    }

    const success = section.split('### Response Success')[1]?.split(/^### /m)[0] ?? '';
    const status = Number(success.match(/`(\d{3})[^`]*`/)?.[1] ?? 200);
    const json = success.match(/```json\n([\s\S]*?)\n```/)?.[1];

    let body = null;
    if (json) {
        try {
            body = JSON.parse(json);
        } catch (error) {
            problems.push(`${code}: invalid JSON example (${error.message})`);
            continue;
        }
    }

    const fullPath = path.startsWith('/sanctum') ? path : `/api/v1${path}`;
    mocks[`${method} ${fullPath}`] = { code, status, body };
}

writeFileSync(output, JSON.stringify(mocks, null, 2) + '\n');

console.log(`Wrote ${Object.keys(mocks).length} mocks to ${output.replace(root, '.')}`);
if (problems.length) {
    console.error(`\n${problems.length} problem(s):\n- ${problems.join('\n- ')}`);
    process.exitCode = 1;
}
