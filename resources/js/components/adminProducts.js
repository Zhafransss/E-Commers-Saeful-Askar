import { api } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';
import { listState } from './adminList.js';

const KINDS = [
    { type: 'categories', label: 'Kategori', hint: 'Salin kategori dari Jubelio.' },
    { type: 'products', label: 'Produk', hint: 'Hanya produk yang berubah sejak sinkron terakhir.' },
    { type: 'stock', label: 'Stok', hint: 'Samakan stok semua varian dengan Jubelio.' },
    { type: 'full', label: 'Sinkron penuh', hint: 'Kategori, lalu semua produk (nonaktifkan yang hilang), lalu stok.' },
];

/**
 * Alpine.data('adminProducts'): /admin/products (docs/api/admin.md ADM-20 list, ADM-34 show or hide a product
 * for customers, ADM-13 start a sync). The products and their variants come from Jubelio and are not edited
 * here, only hidden or shown; the sync cards that start a sync from
 * Jubelio live here, and what each sync did is in the history on /admin/sync.
 */
export default () => ({
    ...listState(endpoints.admin.products, { q: '', status: '', visibility: '' }),

    kinds: KINDS,
    starting: null,
    notice: null,
    actionError: null,
    expanded: {},
    saving: {},

    toggle(id) {
        this.expanded[id] = !this.expanded[id];
    },

    // ADM-34: the answer replaces the row, so the switch always shows what the server holds.
    async setHidden(product, hidden) {
        this.saving[product.id] = true;
        this.notice = null;
        this.actionError = null;
        try {
            const { data } = await api.patch(endpoints.admin.productVisibility(product.id), { is_hidden: hidden });
            Object.assign(product, data);
            this.notice = hidden ? `"${product.name}" disembunyikan dari pelanggan.` : `"${product.name}" ditampilkan lagi ke pelanggan.`;
        } catch (error) {
            this.actionError = error.message;
        } finally {
            this.saving[product.id] = false;
        }
    },

    // ADM-13
    async start(type) {
        this.starting = type;
        this.notice = null;
        this.actionError = null;
        try {
            const { message } = await api.post(endpoints.admin.sync, { type });
            this.notice = message;
        } catch (error) {
            this.actionError = error.message;
        } finally {
            this.starting = null;
        }
    },

    stockLabel(qty) {
        return qty > 0 ? `${qty}` : 'Habis';
    },
});
