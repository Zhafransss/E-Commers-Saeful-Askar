import * as staticCart from '../static/cart.js';

/**
 * Alpine.data('cartPage'): the /cart page (docs/api/cart.md).
 *
 * Static phase: works on the localStorage cart from ../static/cart.js, which applies
 * the backend's rules, so every button already behaves and survives a refresh. When
 * the backend is ready, load the cart from CART-01 and replace the body of
 * update()/remove()/clear() with the API call noted there, assigning the returned
 * Cart to `this.cart`; the markup stays the same.
 */
const ISSUE_MESSAGES = {
    QTY_EXCEEDS_STOCK: 'Jumlah melebihi stok. Kurangi jumlah sebelum checkout.',
    OUT_OF_STOCK: 'Stok habis. Hapus item ini untuk melanjutkan.',
    PRODUCT_INACTIVE: 'Produk tidak dijual lagi. Hapus item ini untuk melanjutkan.',
};

export default () => ({
    cart: staticCart.load(),
    notice: null,
    confirmingClear: false,

    init() {
        this.$store.cart.setFromCart(this.cart);
    },

    issueMessage(item) {
        return ISSUE_MESSAGES[item.issue] ?? 'Item ini perlu diperiksa.';
    },

    /** Total qty of every variant of the same product (max_qty is per product, CART-02). */
    productQty(item) {
        return this.cart.items.filter((other) => other.product.id === item.product.id).reduce((sum, other) => sum + other.qty, 0);
    },

    canIncrease(item) {
        if (item.issue === 'PRODUCT_INACTIVE' || item.qty >= item.variant.available_qty) return false;
        return item.product.max_qty === null || this.productQty(item) < item.product.max_qty;
    },

    canDecrease(item) {
        return item.issue !== 'PRODUCT_INACTIVE' && item.qty > (item.product.min_qty ?? 1);
    },

    /** CART-03: PATCH endpoints.cart.item(item.id) { qty } */
    update(item, qty) {
        if (qty > item.qty && !this.canIncrease(item)) {
            this.notice = item.qty >= item.variant.available_qty
                ? `Stok ${item.variant.name} tersisa ${item.variant.available_qty}.`
                : `Maksimal ${item.product.max_qty} per pesanan untuk ${item.product.name}.`;
            return;
        }
        if (qty < (item.product.min_qty ?? 1)) return;

        item.qty = qty;
        this.notice = null;
        this.commit();
    },

    /** CART-04: DELETE endpoints.cart.item(item.id) */
    remove(item) {
        this.cart.items = this.cart.items.filter((other) => other.id !== item.id);
        this.notice = null;
        this.commit();
    },

    /** CART-05: DELETE endpoints.cart.show */
    clear() {
        this.cart.items = [];
        this.confirmingClear = false;
        this.notice = null;
        this.commit();
    },

    /** Recalculate, persist, and update the header badge. */
    commit() {
        staticCart.save(staticCart.recompute(this.cart));
        this.$store.cart.setFromCart(this.cart);
    },
});
