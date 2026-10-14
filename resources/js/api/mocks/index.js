/**
 * Mock backend used when VITE_API_MOCK=true. Loaded lazily by api/client.js,
 * so it is never part of the production bundle.
 *
 * Lookup order: overrides.js (hand-written by the frontend team) first, then
 * contract.json (generated from docs/api; do not edit by hand).
 */
import { ApiError } from '../client.js';
import contract from './contract.json';
import overrides from './overrides.js';

const LATENCY_MS = 250;

function compile(table) {
    return Object.entries(table).map(([key, handler]) => {
        const [method, template] = key.split(' ');
        const names = [...template.matchAll(/\{(\w+)\}/g)].map((m) => m[1]);
        const pattern = new RegExp(`^${template.replace(/\{\w+\}/g, '([^/]+)')}$`);
        return { method, pattern, names, handler };
    });
}

const routes = [...compile(overrides), ...compile(contract)];

function find(method, path) {
    for (const route of routes) {
        if (route.method !== method) continue;
        const match = path.match(route.pattern);
        if (match) {
            const params = Object.fromEntries(route.names.map((name, i) => [name, decodeURIComponent(match[i + 1])]));
            return { route, params };
        }
    }
    return null;
}

export async function mockRequest(method, path, { query = {}, body } = {}) {
    await new Promise((resolve) => setTimeout(resolve, LATENCY_MS));

    const found = find(method, path);
    if (!found) {
        throw new ApiError(404, { message: `Mock belum tersedia untuk ${method} ${path}.`, code: 'NOT_FOUND' });
    }

    const { handler } = found.route;
    const result = typeof handler === 'function' ? await handler({ params: found.params, query, body }) : handler;

    // Copy so pages mutating a response never change the shared mock data.
    const responseBody = result.body == null ? null : structuredClone(result.body);

    if (result.status >= 400) {
        throw new ApiError(result.status, responseBody ?? {});
    }

    console.debug(`[mock] ${method} ${path} → ${result.status}${result.code ? ` (${result.code})` : ''}`);
    return responseBody;
}
