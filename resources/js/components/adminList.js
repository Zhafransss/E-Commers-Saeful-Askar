import { api } from '../api/client.js';

// Rows per page on every admin list; the rest is on the next pages.
export const PER_PAGE = 10;

/**
 * State and paging shared by the admin list pages (users, products, shipments): filters, the current page,
 * a debounced search, and a request counter so a slow answer never replaces a newer one. A page spreads this
 * into its own component and adds what is special to it. No getters here: spreading would flatten them.
 *
 * @param {string} endpoint  The list endpoint, newest first on the server
 * @param {Record<string, string>} defaults  The filters and their empty values
 */
export function listState(endpoint, defaults) {
    return {
        filters: { ...defaults },
        items: [],
        meta: { current_page: 1, last_page: 1, total: 0 },
        loading: true,
        error: null,
        requestId: 0,
        searchTimer: null,

        init() {
            this.fetchPage(1);
        },

        search() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.fetchPage(1), 350);
        },

        async fetchPage(page) {
            const id = ++this.requestId;
            this.loading = true;
            this.error = null;
            try {
                const query = { page, per_page: PER_PAGE, ...Object.fromEntries(Object.entries(this.filters).filter(([, v]) => v !== '')) };
                const { data, meta } = await api.get(endpoint, { query });
                if (id !== this.requestId) return;
                this.items = data;
                this.meta = meta;
            } catch (error) {
                if (id === this.requestId) this.error = error.message;
            } finally {
                if (id === this.requestId) this.loading = false;
            }
        },

        reset() {
            this.filters = { ...defaults };
            this.fetchPage(1);
        },

        canNext() {
            return this.meta.current_page < this.meta.last_page;
        },

        date(value) {
            return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
        },

        day(value) {
            return value ? new Date(value).toLocaleDateString('id-ID', { dateStyle: 'medium' }) : '-';
        },
    };
}

/** Badge colours of the order statuses used on the admin pages. */
export function statusBadge(status) {
    return (
        {
            pending_payment: 'bg-yellow-100 text-yellow-800',
            paid: 'bg-green-100 text-green-700',
            processing: 'bg-blue-100 text-blue-700',
            shipped: 'bg-blue-100 text-blue-700',
            completed: 'bg-green-100 text-green-700',
            expired: 'bg-gray-100 text-gray-600',
            cancelled: 'bg-red-100 text-red-700',
        }[status] ?? 'bg-gray-100 text-gray-600'
    );
}
