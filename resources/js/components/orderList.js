import { api } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

export const ORDER_TABS = [
    { key: 'all', label: 'Semua', status: '' },
    { key: 'unpaid', label: 'Belum dibayar', status: 'pending_payment' },
    { key: 'processing', label: 'Diproses', status: 'paid,processing' },
    { key: 'shipped', label: 'Dikirim', status: 'shipped' },
    { key: 'completed', label: 'Selesai', status: 'completed' },
    { key: 'closed', label: 'Dibatalkan', status: 'expired,cancelled' },
];

/**
 * Alpine.data('orderList'): /account/orders (docs/api/checkout-orders.md ORD-02).
 * Tabs filter by status (the grouping suggested by the contract); 10 orders per page, "Muat lebih banyak" appends.
 */
export default () => ({
    tabs: ORDER_TABS,
    tab: 'all',
    orders: [],
    meta: { current_page: 0, last_page: 0, total: 0 },
    loading: true,
    loadingMore: false,
    error: null,
    requestId: 0,

    init() {
        const wanted = new URLSearchParams(window.location.search).get('tab');
        if (this.tabs.some((t) => t.key === wanted)) this.tab = wanted;
        this.fetchPage(1);
    },

    select(key) {
        if (key === this.tab) return;
        this.tab = key;
        const url = new URL(window.location.href);
        key === 'all' ? url.searchParams.delete('tab') : url.searchParams.set('tab', key);
        window.history.replaceState({}, '', url);
        this.fetchPage(1);
    },

    async fetchPage(page) {
        const id = ++this.requestId;
        page === 1 ? (this.loading = true) : (this.loadingMore = true);
        this.error = null;
        try {
            const status = this.tabs.find((t) => t.key === this.tab).status;
            const { data, meta } = await api.get(endpoints.orders.index, { query: { page, status: status || undefined } });
            if (id !== this.requestId) return; // the customer already switched tab
            this.orders = page === 1 ? data : [...this.orders, ...data];
            this.meta = meta;
        } catch (error) {
            if (id === this.requestId) this.error = error.message;
        } finally {
            if (id === this.requestId) this.loading = this.loadingMore = false;
        }
    },

    get hasMore() {
        return this.meta.current_page < this.meta.last_page;
    },

    badge(status) {
        return (
            {
                pending_payment: 'bg-brand-soft text-ink',
                paid: 'bg-green-100 text-green-700',
                processing: 'bg-blue-100 text-blue-700',
                shipped: 'bg-blue-100 text-blue-700',
                completed: 'bg-green-100 text-green-700',
                expired: 'bg-gray-100 text-gray-600',
                cancelled: 'bg-red-100 text-red-700',
            }[status] ?? 'bg-gray-100 text-gray-600'
        );
    },

    date(value) {
        return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
    },
});
