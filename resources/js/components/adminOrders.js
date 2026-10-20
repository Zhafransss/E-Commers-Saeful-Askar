import { api, ApiError } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

const STATUSES = [
    ['pending_payment', 'Menunggu pembayaran'],
    ['paid', 'Dibayar'],
    ['processing', 'Diproses'],
    ['shipped', 'Dikirim'],
    ['completed', 'Selesai'],
    ['expired', 'Kedaluwarsa'],
    ['cancelled', 'Dibatalkan'],
];

/**
 * Alpine.data('adminOrders'): /admin/orders (docs/api/admin.md ADM-09 list, ADM-10 detail,
 * ADM-11 resend to Jubelio, ADM-12 re-check payment). The detail opens in a side panel and is
 * linked as ?order=WEB-000123.
 */
export default () => ({
    statuses: STATUSES,
    filters: { q: '', status: '', jubelio_sync_status: '', date_from: '', date_to: '' },
    orders: [],
    meta: { current_page: 1, last_page: 1, total: 0 },
    loading: true,
    error: null,
    requestId: 0,
    searchTimer: null,

    detail: null,
    detailLoading: false,
    detailError: null,
    busy: false,
    notice: null,
    actionError: null,

    init() {
        const params = new URLSearchParams(window.location.search);
        for (const key of Object.keys(this.filters)) this.filters[key] = params.get(key) ?? '';
        this.fetchPage(1);
        if (params.get('order')) this.open(params.get('order'));
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
            const query = { page, ...Object.fromEntries(Object.entries(this.filters).filter(([, v]) => v !== '')) };
            const { data, meta } = await api.get(endpoints.admin.orders, { query });
            if (id !== this.requestId) return;
            this.orders = data;
            this.meta = meta;
            this.remember();
        } catch (error) {
            if (id === this.requestId) this.error = error.message;
        } finally {
            if (id === this.requestId) this.loading = false;
        }
    },

    remember(order = undefined) {
        const url = new URL(window.location.href);
        url.search = '';
        for (const [key, value] of Object.entries(this.filters)) if (value !== '') url.searchParams.set(key, value);
        const current = order === undefined ? new URLSearchParams(window.location.search).get('order') : order;
        if (current) url.searchParams.set('order', current);
        window.history.replaceState({}, '', url);
    },

    reset() {
        this.filters = { q: '', status: '', jubelio_sync_status: '', date_from: '', date_to: '' };
        this.fetchPage(1);
    },

    async open(orderNumber) {
        this.detail = null;
        this.detailError = null;
        this.notice = null;
        this.actionError = null;
        this.detailLoading = true;
        this.remember(orderNumber);
        await this.loadDetail(orderNumber);
        this.detailLoading = false;
    },

    async loadDetail(orderNumber) {
        try {
            const { data } = await api.get(endpoints.admin.order(orderNumber));
            this.detail = data;
        } catch (error) {
            this.detailError = error instanceof ApiError && error.status === 404 ? 'Pesanan tidak ditemukan.' : error.message;
        }
    },

    close() {
        this.detail = null;
        this.detailError = null;
        this.detailLoading = false;
        this.remember(null);
    },

    // ADM-12
    async refreshPayment() {
        await this.act(async () => {
            const { data, message } = await api.post(endpoints.admin.refreshPayment(this.detail.order_number));
            this.notice = message;
            if (data.changed) await this.refreshAfterChange();
        });
    },

    // ADM-11: `action` is chosen by the server when left out.
    async pushJubelio(action = undefined) {
        await this.act(async () => {
            const { message } = await api.post(endpoints.admin.pushJubelio(this.detail.order_number), action ? { action } : {});
            this.notice = message;
            // The work runs in the queue: look again shortly, and once more later.
            setTimeout(() => this.refreshAfterChange(), 4000);
            setTimeout(() => this.refreshAfterChange(), 12000);
        });
    },

    async refreshAfterChange() {
        if (!this.detail) return;
        await this.loadDetail(this.detail.order_number);
        this.fetchPage(this.meta.current_page);
    },

    async act(work) {
        this.busy = true;
        this.actionError = null;
        this.notice = null;
        try {
            await work();
        } catch (error) {
            this.actionError = error.message;
        } finally {
            this.busy = false;
        }
    },

    get canResend() {
        return this.detail && !this.detail.is_checkout_failed;
    },

    get hasMore() {
        return this.meta.current_page < this.meta.last_page;
    },

    badge(status) {
        return (
            {
                pending_payment: 'bg-yellow-100 text-yellow-800',
                paid: 'bg-green-100 text-green-700',
                processing: 'bg-blue-100 text-blue-700',
                shipped: 'bg-blue-100 text-blue-700',
                completed: 'bg-green-100 text-green-700',
                expired: 'bg-gray-100 text-gray-600',
                cancelled: 'bg-red-100 text-red-700',
                pending: 'bg-yellow-100 text-yellow-800',
                synced: 'bg-green-100 text-green-700',
                failed: 'bg-red-100 text-red-700',
                success: 'bg-green-100 text-green-700',
                running: 'bg-blue-100 text-blue-700',
            }[status] ?? 'bg-gray-100 text-gray-600'
        );
    },

    date(value) {
        return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
    },
});
