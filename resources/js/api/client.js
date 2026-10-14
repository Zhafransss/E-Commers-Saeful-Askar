/**
 * Single entry point for calling the backend (docs/api/README.md conventions).
 *
 * - Session cookie auth (Sanctum stateful): fetches /sanctum/csrf-cookie before
 *   the first non-GET request and sends X-XSRF-TOKEN.
 * - Every failure is thrown as ApiError carrying the contract `code`.
 * - With VITE_API_MOCK=true, requests are answered from ./mocks (no backend needed).
 *
 * Usage:
 *   import { api } from './api/client';
 *   import { endpoints } from './api/endpoints';
 *   const { data, meta } = await api.get(endpoints.products.index, { query: { q: 'baterai' } });
 */

const BASE_URL = '/api/v1';
const CSRF_URL = '/sanctum/csrf-cookie';
const USE_MOCK = import.meta.env.VITE_API_MOCK === 'true';

export class ApiError extends Error {
    /**
     * @param {number} status HTTP status
     * @param {object} body   Contract error body: { message, code, errors?, ...extra }
     */
    constructor(status, body = {}) {
        super(body.message || 'Terjadi kesalahan. Coba lagi beberapa saat.');
        this.name = 'ApiError';
        this.status = status;
        this.code = body.code || (status === 0 ? 'NETWORK_ERROR' : 'SERVER_ERROR');
        this.errors = body.errors || {};
        this.body = body;
    }

    /** First message for a field, for showing under form inputs. */
    fieldError(field) {
        return this.errors[field]?.[0] ?? null;
    }
}

function readCookie(name) {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
}

async function ensureCsrfCookie(force = false) {
    if (!force && readCookie('XSRF-TOKEN')) return;
    await fetch(CSRF_URL, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
}

function buildUrl(path, query) {
    const url = new URL(BASE_URL + path, window.location.origin);
    Object.entries(query || {}).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return;
        url.searchParams.set(key, Array.isArray(value) ? value.join(',') : value);
    });
    return url.pathname + url.search;
}

async function send(method, path, { query, body, headers = {} } = {}, retried = false) {
    const isForm = body instanceof FormData;
    const requestHeaders = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(body !== undefined && !isForm ? { 'Content-Type': 'application/json' } : {}),
        ...headers,
    };

    if (method !== 'GET') {
        await ensureCsrfCookie();
        requestHeaders['X-XSRF-TOKEN'] = readCookie('XSRF-TOKEN') ?? '';
    }

    let response;
    try {
        response = await fetch(buildUrl(path, query), {
            method,
            credentials: 'same-origin',
            headers: requestHeaders,
            body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
        });
    } catch {
        throw new ApiError(0, { message: 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.' });
    }

    // Expired CSRF token: refresh once and retry transparently.
    if (response.status === 419 && !retried) {
        await ensureCsrfCookie(true);
        return send(method, path, { query, body, headers }, true);
    }

    const payload = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        const error = new ApiError(response.status, payload ?? {});
        if (error.code === 'UNAUTHENTICATED') {
            window.dispatchEvent(new CustomEvent('api:unauthenticated'));
        }
        throw error;
    }

    return payload;
}

async function request(method, path, options = {}) {
    if (USE_MOCK) {
        const { mockRequest } = await import('./mocks/index.js');
        return mockRequest(method, BASE_URL + path, options);
    }
    return send(method, path, options);
}

export const api = {
    get: (path, options) => request('GET', path, options),
    post: (path, body, options = {}) => request('POST', path, { ...options, body }),
    put: (path, body, options = {}) => request('PUT', path, { ...options, body }),
    patch: (path, body, options = {}) => request('PATCH', path, { ...options, body }),
    delete: (path, options) => request('DELETE', path, options),
    isMock: USE_MOCK,
};
