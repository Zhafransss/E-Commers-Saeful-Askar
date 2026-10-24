import { api, ApiError } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

const POLL_MS = 3000;
const POLL_MAX_MS = 60000;

/**
 * Alpine.data('finishPage'): /checkout/finish?order=WEB-000123, where DANA brings the customer back
 * (docs/api/checkout-orders.md ORD-03, ORD-04). The result of the payment reaches us by webhook, so
 * the page asks ORD-03 every 3 s for up to 60 s until the order leaves pending_payment.
 */
export default (orderNumber, failed = false) => ({
    orderNumber,
    failed,
    order: null,
    state: 'checking', // checking | paid | pending | failed | closed | login
    now: Date.now(),
    resuming: false,
    resumeError: null,

    async init() {
        setInterval(() => (this.now = Date.now()), 1000);

        if (!this.orderNumber) {
            this.state = 'closed';
            return;
        }

        const startedAt = Date.now();

        do {
            await this.load();
            if (this.state !== 'checking') return;
            await new Promise((resolve) => setTimeout(resolve, POLL_MS));
        } while (Date.now() - startedAt < POLL_MAX_MS);

        this.state = this.failed ? 'failed' : 'pending';
    },

    async load() {
        try {
            const { data } = await api.get(endpoints.orders.show(this.orderNumber));
            this.order = data;
            this.state = this.stateOf(data.status);
        } catch (error) {
            if (error instanceof ApiError && error.status === 404) {
                this.order = null;
                this.state = 'closed';
            } else if (error instanceof ApiError && error.status === 401) {
                // No session on this request (the page may be opened from another site): ask to log in.
                this.state = 'login';
            }
            // Any other error: keep checking until the time is up.
        }
    },

    stateOf(status) {
        if (['paid', 'processing', 'shipped', 'completed'].includes(status)) return 'paid';
        if (['expired', 'cancelled'].includes(status)) return 'closed';

        return this.failed ? 'failed' : 'checking';
    },

    get remaining() {
        if (!this.order?.payment_expires_at) return null;
        const ms = Math.max(0, new Date(this.order.payment_expires_at) - this.now);
        const h = Math.floor(ms / 3600000);
        const m = Math.floor(ms / 60000) % 60;
        const s = Math.floor(ms / 1000) % 60;

        return [h, m, s].map((n) => String(n).padStart(2, '0')).join(':');
    },

    // ORD-04 returns the existing payment page, never a new bill.
    async resume() {
        this.resuming = true;
        this.resumeError = null;
        try {
            const { data } = await api.get(endpoints.orders.payment(this.orderNumber));
            window.location.href = data.checkout_url;
        } catch (error) {
            this.resumeError = error.message;
            await this.load();
        } finally {
            this.resuming = false;
        }
    },
});
