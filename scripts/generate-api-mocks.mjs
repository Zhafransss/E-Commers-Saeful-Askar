/**
 * Builds resources/js/api/mocks/contract.json from the API Contract.
 *
 * For every frontend endpoint in docs/api/README.md ("Indeks endpoint"), it takes
 * the HTTP status and the first JSON example under "### Response Success" in the
 * module file. Endpoints marked (v1.1) are skipped.
 *
 * Run after any contract change: npm run mocks:generate
 * (scripts/check-api-contract.mjs reuses buildMocks() to detect a stale file.)
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const root = join(dirname(fileURLToPath(import.meta.url)), '..');
export const mocksFile = join(root, 'resources', 'js', 'api', 'mocks', 'contract.json');

const docs = join(root, 'docs', 'api');

// | CODE | METHOD | `path` ... | Auth | file.md | PRD |
const rowPattern = /^\| ([A-Z]+-\d+)( \(v1\.1\))? \| (GET|POST|PUT|PATCH|DELETE) \| `([^`]+)`[^|]* \| [^|]+ \| ([\w-]+\.md) \|/gm;

function sectionsOf(file) {
    const text = readFileSync(join(docs, file), 'utf8');
    const sections = new Map();
    for (const part of text.split(/^## /m).slice(1)) {
        const code = part.match(/^([A-Z]+-\d+) /)?.[1];
        if (code) sections.set(code, part);
    }
    return sections;
}

/**
 * @returns {{ mocks: Record<string, { code: string, status: number, body: unknown }>, problems: string[] }}
 */
export function buildMocks() {
    const readme = readFileSync(join(docs, 'README.md'), 'utf8');
    const frontendIndex = readme.split('### Untuk frontend')[1].split('### Webhook masuk')[0];
    const cache = new Map();
    const mocks = {};
    const problems = [];

    for (const [, code, deferred, method, path, file] of frontendIndex.matchAll(rowPattern)) {
        if (deferred) continue;

        if (!cache.has(file)) cache.set(file, sectionsOf(file));
        const section = cache.get(file).get(code);
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

    return { mocks, problems };
}

export const serialize = (mocks) => JSON.stringify(mocks, null, 2) + '\n';

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
    const { mocks, problems } = buildMocks();
    writeFileSync(mocksFile, serialize(mocks));

    console.log(`Wrote ${Object.keys(mocks).length} mocks to ${mocksFile.replace(root, '.')}`);
    if (problems.length) {
        console.error(`\n${problems.length} problem(s):\n- ${problems.join('\n- ')}`);
        process.exitCode = 1;
    }
}
