import { api, ApiError } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

/**
 * Alpine.data('orderPage'): /account/orders/{order_number}
 * (docs/api/checkout-orders.md ORD-03 detail, ORD-04 continue paying, ORD-05 cancel).
 */
export default (orderNumber) => ({
    orderNumber,
    order: null,
    loading: true,
    error: null,
    notFound: false,
    now: Date.now(),
    busy: false,
    actionError: null,
    confirmingCancel: false,
    reason: '',

    async init() {
        setInterval(() => (this.now = Date.now()), 1000);
        await this.load();
        // A payment result reaches us by webhook: while waiting, look again every 10 s.
        setInterval(() => {
            if (this.order?.status === 'pending_payment' && !this.busy) this.load(true);
        }, 10000);
    },

    async load(quiet = false) {
        if (!quiet) this.loading = true;
        try {
            const { data } = await api.get(endpoints.orders.show(this.orderNumber));
            this.order = data;
            this.error = null;
        } catch (error) {
            if (error instanceof ApiError && error.status === 404) this.notFound = true;
            else if (!quiet) this.error = error.message;
        } finally {
            this.loading = false;
        }
    },

    get remaining() {
        if (this.order?.status !== 'pending_payment' || !this.order.payment_expires_at) return null;
        const ms = Math.max(0, new Date(this.order.payment_expires_at) - this.now);
        const h = Math.floor(ms / 3600000);
        const m = Math.floor(ms / 60000) % 60;
        const s = Math.floor(ms / 1000) % 60;

        return [h, m, s].map((n) => String(n).padStart(2, '0')).join(':');
    },

    get badge() {
        return (
            {
                pending_payment: 'bg-brand-soft text-ink',
                paid: 'bg-green-100 text-green-700',
                processing: 'bg-blue-100 text-blue-700',
                shipped: 'bg-blue-100 text-blue-700',
                completed: 'bg-green-100 text-green-700',
                expired: 'bg-gray-100 text-gray-600',
                cancelled: 'bg-red-100 text-red-700',
            }[this.order?.status] ?? 'bg-gray-100 text-gray-600'
        );
    },

    async pay() {
        this.busy = true;
        this.actionError = null;
        try {
            const { data } = await api.get(endpoints.orders.payment(this.orderNumber));
            window.location.href = data.checkout_url;
        } catch (error) {
            this.actionError = error.message;
            await this.load(true);
        } finally {
            this.busy = false;
        }
    },

    async cancel() {
        this.busy = true;
        this.actionError = null;
        try {
            const { data } = await api.post(endpoints.orders.cancel(this.orderNumber), { reason: this.reason.trim() || null });
            this.order = data;
            this.confirmingCancel = false;
        } catch (error) {
            this.actionError = error.message;
            this.confirmingCancel = false;
            await this.load(true);
        } finally {
            this.busy = false;
        }
    },
});
