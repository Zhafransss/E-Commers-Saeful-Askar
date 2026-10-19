import Alpine from 'alpinejs';

import { api, APP_URL } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

/**
 * Alpine.store('cart'): the customer's cart (docs/api/cart.md) and the header badge.
 *
 * Every cart endpoint answers with the whole Cart object, so each action just replaces
 * `cart` with the response (CART-01 … CART-05). The badge is filled from CART-06 on every
 * page load; guests get 0 there, and nothing else is fetched until a page needs the cart.
 * Errors are thrown as ApiError for the caller to show (OUT_OF_STOCK, QTY_LIMIT_EXCEEDED …).
 */
export default {
    cart: null,
    totalQty: 0,
    hasIssue: false,

    async init() {
        await this.refresh();
    },

    /** CART-06: just the numbers for the badge. */
    async refresh() {
        try {
            const { data } = await api.get(endpoints.cart.count);
            this.totalQty = data.total_qty;
            this.hasIssue = data.has_issue;
        } catch (error) {
            // The badge is not critical; never break the page over it.
            console.warn('[cart] badge refresh failed:', error);
        }
    },

    /** CART-01 */
    async load() {
        const { data } = await api.get(endpoints.cart.show);
        this.setFromCart(data);
        return data;
    },

    /** CART-02. A guest is sent to the login page and brought back here afterwards. */
    async add(variantId, qty) {
        if (!(await this.ensureLoggedIn())) return null;
        const { data, message } = await api.post(endpoints.cart.items, { variant_id: variantId, qty });
        this.setFromCart(data);
        return { cart: data, message };
    },

    /** CART-03 */
    async update(itemId, qty) {
        const { data } = await api.patch(endpoints.cart.item(itemId), { qty });
        this.setFromCart(data);
        return data;
    },

    /** CART-04 */
    async remove(itemId) {
        const { data } = await api.delete(endpoints.cart.item(itemId));
        this.setFromCart(data);
        return data;
    },

    /** CART-05 */
    async clear() {
        const { data } = await api.delete(endpoints.cart.show);
        this.setFromCart(data);
        return data;
    },

    /** Keeps the badge in step with a Cart object. */
    setFromCart(cart) {
        this.cart = cart;
        this.totalQty = cart.summary.total_qty;
        this.hasIssue = cart.summary.has_issue;
    },

    /** Quantity of a variant already in the loaded cart. */
    qtyOf(variantId) {
        return this.cart?.items.find((item) => item.variant.id === variantId)?.qty ?? 0;
    },

    async ensureLoggedIn() {
        const auth = Alpine.store('auth');
        if (!auth.loaded) await auth.refresh();
        if (auth.isLoggedIn) return true;

        const next = window.location.pathname + window.location.search;
        window.location.href = `${APP_URL}/login?next=${encodeURIComponent(next)}`;
        return false;
    },
};
