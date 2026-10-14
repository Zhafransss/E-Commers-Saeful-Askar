import { api } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

/**
 * Alpine.store('cart'): header badge state. Refreshed from CART-06 on page load;
 * after any cart change, call setFromCart() with the Cart object every cart
 * endpoint returns (cart.md), instead of refetching.
 */
export default {
    totalQty: 0,
    hasIssue: false,

    async init() {
        await this.refresh();
    },

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

    setFromCart(cart) {
        this.totalQty = cart.summary.total_qty;
        this.hasIssue = cart.summary.has_issue;
    },
};
