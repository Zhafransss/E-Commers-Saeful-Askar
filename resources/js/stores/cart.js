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
 *
 * The customer ticks which lines to buy on /cart; /checkout then pays only for those (`item_ids` of SHIP-01
 * and ORD-01). The unticked ids are what is remembered, so a line added later starts ticked. They live in
 * sessionStorage, which only carries the choice from the cart page to the checkout page.
 */
const UNCHECKED_KEY = 'cart:unchecked';

function readUnchecked() {
    try {
        const ids = JSON.parse(sessionStorage.getItem(UNCHECKED_KEY) ?? '[]');
        return Array.isArray(ids) ? ids : [];
    } catch {
        return [];
    }
}

export default {
    cart: null,
    totalQty: 0,
    hasIssue: false,
    unchecked: readUnchecked(),

    /** Lines the customer is buying: every line that is not unticked. */
    get selectedItems() {
        return (this.cart?.items ?? []).filter((item) => !this.unchecked.includes(item.id));
    },

    get selectedIds() {
        return this.selectedItems.map((item) => item.id);
    },

    /** The Cart `summary` of the ticked lines, with the same rules as the server's (CART-01). */
    get selectedSummary() {
        const items = this.selectedItems;
        const payable = items.filter((item) => item.issue !== 'PRODUCT_INACTIVE');
        const sum = (list, key) => list.reduce((total, item) => total + item[key], 0);

        return {
            item_count: items.length,
            total_qty: sum(items, 'qty'),
            subtotal: sum(payable, 'subtotal'),
            total_weight: sum(payable, 'weight_total'),
            has_issue: items.some((item) => item.issue !== null),
        };
    },

    get allSelected() {
        return (this.cart?.items.length ?? 0) > 0 && this.unchecked.length === 0;
    },

    isSelected(item) {
        return !this.unchecked.includes(item.id);
    },

    toggle(item) {
        this.setUnchecked(this.isSelected(item) ? [...this.unchecked, item.id] : this.unchecked.filter((id) => id !== item.id));
    },

    /** Ticks everything, or unticks everything when everything is ticked. */
    toggleAll() {
        this.setUnchecked(this.allSelected ? (this.cart?.items ?? []).map((item) => item.id) : []);
    },

    setUnchecked(ids) {
        this.unchecked = ids;
        try {
            sessionStorage.setItem(UNCHECKED_KEY, JSON.stringify(ids));
        } catch {
            // Private mode: the choice just lasts until the page is left.
        }
    },

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
        const known = cart.items.map((item) => item.id);
        if (this.unchecked.some((id) => !known.includes(id))) this.setUnchecked(this.unchecked.filter((id) => known.includes(id)));
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
