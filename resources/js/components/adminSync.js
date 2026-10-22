import { api } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

// Rows per page of the history (ADM-14); the rest is on the next pages.
const PER_PAGE = 10;

/**
 * Alpine.data('adminSync'): /admin/sync, the sync history (docs/api/admin.md ADM-14). Starting a sync is on
 * /admin/products (adminProducts.js).
 * While a sync is running the history is refreshed every 3 s.
 */
export default () => ({
    logs: [],
    meta: { current_page: 1, last_page: 1, total: 0 },
    filters: { type: '', status: '' },
    loading: true,
    error: null,
    timer: null,

    init() {
        this.fetchPage(1);
        this.timer = setInterval(() => {
            if (this.running) this.fetchPage(this.meta.current_page, true);
        }, 3000);
    },

    async fetchPage(page, quiet = false) {
        if (!quiet) this.loading = true;
        try {
            const query = { page, per_page: PER_PAGE, ...Object.fromEntries(Object.entries(this.filters).filter(([, v]) => v !== '')) };
            const { data, meta } = await api.get(endpoints.admin.syncLogs, { query });
            this.logs = data;
            this.meta = meta;
            this.error = null;
        } catch (error) {
            if (!quiet) this.error = error.message;
        } finally {
            this.loading = false;
        }
    },

    get running() {
        return this.logs.some((log) => log.status === 'running');
    },

    label(type) {
        return (
            {
                categories: 'Kategori',
                products: 'Produk',
                stock: 'Stok',
                order_push: 'Kirim pesanan',
                order_paid: 'Tandai lunas',
                order_cancel: 'Batalkan pesanan',
            }[type] ?? type
        );
    },

    badge(status) {
        return { success: 'bg-green-100 text-green-700', running: 'bg-blue-100 text-blue-700', failed: 'bg-red-100 text-red-700' }[status] ?? 'bg-gray-100 text-gray-600';
    },

    date(value) {
        return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'medium' }) : '-';
    },

    duration(seconds) {
        if (seconds === null || seconds === undefined) return '-';
        return seconds >= 60 ? `${Math.floor(seconds / 60)} mnt ${seconds % 60} dtk` : `${seconds} dtk`;
    },
});
