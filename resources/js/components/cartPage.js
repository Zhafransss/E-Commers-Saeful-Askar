import { ApiError } from '../api/client.js';

/**
 * Alpine.data('cartPage'): the /cart page (docs/api/cart.md CART-01 … CART-05).
 *
 * The cart itself lives in Alpine.store('cart'); this component adds the page state
 * (loading, notice, clear confirmation) and the quantity rules the buttons mirror, so
 * "+" is disabled before the server would answer OUT_OF_STOCK or QTY_LIMIT_EXCEEDED.
 * The server stays the judge: a rejected change shows its message and reloads the cart.
 */
const ISSUE_MESSAGES = {
    QTY_EXCEEDS_STOCK: 'Jumlah melebihi stok. Kurangi jumlah sebelum checkout.',
    OUT_OF_STOCK: 'Stok habis. Hapus item ini untuk melanjutkan.',
    PRODUCT_INACTIVE: 'Produk tidak dijual lagi. Hapus item ini untuk melanjutkan.',
};

const EMPTY_CART = { items: [], summary: { item_count: 0, total_qty: 0, subtotal: 0, total_weight: 0, has_issue: false } };

export default () => ({
    loaded: false,
    busy: false,
    notice: null,
    confirmingClear: false,

    async init() {
        try {
            await this.$store.cart.load();
        } catch (error) {
            this.notice = error instanceof ApiError ? error.message : 'Keranjang tidak dapat dimuat. Coba muat ulang halaman.';
        } finally {
            this.loaded = true;
        }
    },

    get cart() {
        return this.$store.cart.cart ?? EMPTY_CART;
    },

    issueMessage(item) {
        return ISSUE_MESSAGES[item.issue] ?? 'Item ini perlu diperiksa.';
    },

    /** Total qty of every variant of the same product (max_qty is per product, CART-02). */
    productQty(item) {
        return this.cart.items.filter((other) => other.product.id === item.product.id).reduce((sum, other) => sum + other.qty, 0);
    },

    canIncrease(item) {
        if (this.busy || item.issue === 'PRODUCT_INACTIVE' || item.qty >= item.variant.available_qty) return false;
        return item.product.max_qty === null || this.productQty(item) < item.product.max_qty;
    },

    canDecrease(item) {
        return !this.busy && item.issue !== 'PRODUCT_INACTIVE' && item.qty > (item.product.min_qty ?? 1);
    },

    /** CART-03 */
    update(item, qty) {
        if (qty > item.qty && !this.canIncrease(item)) return;
        if (qty < (item.product.min_qty ?? 1)) return;
        return this.run(() => this.$store.cart.update(item.id, qty));
    },

    /** CART-04 */
    remove(item) {
        return this.run(() => this.$store.cart.remove(item.id));
    },

    /** CART-05 */
    async clear() {
        this.confirmingClear = false;
        await this.run(() => this.$store.cart.clear());
    },

    /** Runs a cart change; a rejected one shows the server's message and re-reads the cart. */
    async run(change) {
        if (this.busy) return;
        this.busy = true;
        this.notice = null;
        try {
            await change();
        } catch (error) {
            if (!(error instanceof ApiError)) throw error;
            this.notice = error.message;
            await this.$store.cart.load().catch(() => {});
        } finally {
            this.busy = false;
        }
    },
});
