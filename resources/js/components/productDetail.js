import { ApiError } from '../api/client.js';

/**
 * Alpine.data('productDetail'): variant choice, quantity, and "Tambah ke keranjang" on
 * the product page (docs/api/catalog.md CAT-03, cart.md CART-02).
 *
 * `variants` and `images` come from the CAT-03 object the page was rendered with. How
 * many of each variant the customer already has in the cart comes from CART-01, so the
 * quantity stepper stops where CART-02 would answer OUT_OF_STOCK or QTY_LIMIT_EXCEEDED.
 * The server still has the last word: its message is shown if the add is refused.
 */
export default ({ productId, variants, imageVariants, selected, minQty, maxQty: productMaxQty }) => ({
    variants,
    imageVariants,
    selected,
    image: 0,
    qty: minQty,
    adding: false,
    added: null,
    error: null,

    async init() {
        // An image tagged with variant_id is shown while that variant is selected.
        this.$watch('variant', (variant) => this.showVariantImage(variant));
        this.showVariantImage(this.variant);

        // Keep qty inside the limits as variant, stock, or the cart change.
        this.$watch('maxQty', (max) => {
            if (this.qty > max) this.qty = Math.max(1, max);
        });

        // Guests have no cart; the badge already says 0 for them.
        await this.$store.auth.ready;
        if (this.$store.auth.isLoggedIn) await this.$store.cart.load().catch(() => {});
    },

    showVariantImage(variant) {
        const index = variant ? this.imageVariants.indexOf(variant.id) : -1;
        if (index !== -1) this.image = index;
    },

    get variant() {
        return this.variants.find((v) => v.variation_values.every((o) => this.selected[o.label] === o.value));
    },

    /** Quantity of the chosen variant already in the cart. */
    get variantInCart() {
        return this.variant ? this.$store.cart.qtyOf(this.variant.id) : 0;
    },

    /** Quantity of every variant of this product in the cart (max_qty is per product). */
    get productInCart() {
        return (this.$store.cart.cart?.items ?? []).filter((line) => line.product.id === productId).reduce((sum, line) => sum + line.qty, 0);
    },

    // CART-02 rejects qty + qty already in the cart > stock, and the product total > max_qty.
    get maxQty() {
        if (!this.variant) return 0;
        const byStock = this.variant.available_qty - this.variantInCart;
        const byLimit = (productMaxQty ?? Infinity) - this.productInCart;
        return Math.max(0, Math.min(byStock, byLimit));
    },

    get blockedReason() {
        if (!this.variant || !this.variant.in_stock) return 'Stok habis';
        if (this.maxQty < 1) return this.variantInCart >= this.variant.available_qty ? 'Semua stok sudah di keranjang' : 'Batas per pesanan tercapai';
        return null;
    },

    /** CART-02. A guest is sent to the login page by the store and comes back here. */
    async addToCart() {
        if (this.adding || this.blockedReason || this.qty > this.maxQty) return;
        this.adding = true;
        this.error = null;
        this.added = null;

        try {
            const result = await this.$store.cart.add(this.variant.id, this.qty);
            if (result) {
                this.added = `${this.qty} × ${this.variant.name}`;
                this.qty = minQty;
            }
        } catch (error) {
            if (!(error instanceof ApiError)) throw error;
            this.error = error.message;
            // Stock may have changed under us; re-read so the stepper reflects it.
            await this.$store.cart.load().catch(() => {});
        } finally {
            this.adding = false;
        }
    },
});
