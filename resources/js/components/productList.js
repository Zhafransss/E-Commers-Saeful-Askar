import { api, ApiError } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

/**
 * Alpine.data('productList'): the /products catalog (docs/api/catalog.md CAT-01, CAT-02).
 *
 * The state mirrors the CAT-02 query (q, category, in_stock, price_min, price_max, sort,
 * page) and is kept in the URL. Every filter change starts again at page 1; "Muat lebih
 * banyak" fetches the next page and appends it. A response that arrives after a newer
 * request was sent is dropped, so fast typing never shows stale results.
 */
const SORTS = {
    latest: 'Terbaru',
    price_asc: 'Harga terendah',
    price_desc: 'Harga tertinggi',
    name_asc: 'Nama A–Z',
};

const PER_PAGE = 12;

export default ({ content }) => ({
    content,
    sorts: SORTS,

    categories: [],
    products: [],
    meta: { current_page: 0, last_page: 0, total: 0 },
    loading: true,
    loadingMore: false,
    error: null,
    // Set after the first response, so "0 produk" is not shown while the first load runs.
    loaded: false,

    q: '',
    category: '',
    inStock: false,
    priceMin: '',
    priceMax: '',
    sort: 'latest',
    filtersOpen: false,

    controller: null,

    init() {
        const params = new URLSearchParams(window.location.search);
        this.q = params.get('q') ?? '';
        this.category = params.get('category') ?? '';
        this.inStock = params.get('in_stock') === '1';
        this.priceMin = params.get('price_min') ?? '';
        this.priceMax = params.get('price_max') ?? '';
        this.sort = SORTS[params.get('sort')] ? params.get('sort') : 'latest';

        // Any filter change goes back to page 1 and is written to the URL.
        this.$watch('query', () => {
            this.syncUrl();
            this.fetchPage(1);
        });

        // Keep the tab title in step with the category copy, as the server renders it.
        this.$watch('category', () => (document.title = `${this.pageContent.title} | Saeful Askar`));

        this.loadCategories();
        this.fetchPage(1);
    },

    /** CAT-01 */
    async loadCategories() {
        try {
            const { data } = await api.get(endpoints.categories.index);
            this.categories = data;
        } catch (error) {
            // Filtering by typing still works without the category list.
            console.warn('[products] categories failed:', error);
        }
    },

    /** CAT-02 query for the current state (empty values omitted). */
    get query() {
        const query = { q: this.q.trim(), category: this.category, sort: this.sort === 'latest' ? '' : this.sort };
        if (this.inStock) query.in_stock = 1;
        if (this.priceMin !== '') query.price_min = Number(this.priceMin);
        if (this.priceMax !== '') query.price_max = Number(this.priceMax);
        return Object.fromEntries(Object.entries(query).filter(([, value]) => value !== ''));
    },

    syncUrl() {
        const url = new URL(window.location.href);
        url.search = new URLSearchParams(this.query).toString();
        window.history.replaceState(null, '', url);
    },

    /** CAT-02. Page 1 replaces the list, later pages are appended. */
    async fetchPage(page) {
        // A price range the server would reject (422) is explained under the inputs instead.
        if (this.priceInvalid) {
            this.loading = false;
            return;
        }

        this.controller?.abort();
        const controller = (this.controller = new AbortController());
        const append = page > 1;

        this.error = null;
        append ? (this.loadingMore = true) : (this.loading = true);

        try {
            const { data, meta } = await api.get(endpoints.products.index, {
                query: { ...this.query, page, per_page: PER_PAGE },
                signal: controller.signal,
            });
            this.products = append ? [...this.products, ...data] : data;
            this.meta = meta;
            this.loaded = true;
        } catch (error) {
            if (error.name === 'AbortError') return;
            this.error = error instanceof ApiError ? error.message : 'Produk tidak dapat dimuat. Coba lagi.';
        } finally {
            if (this.controller === controller) this.loading = this.loadingMore = false;
        }
    },

    get currentCategory() {
        return this.categories.find((c) => c.slug === this.category) ?? null;
    },

    /** SEO copy (h1, intro, body) for the selected category; falls back to the full catalog's. */
    get pageContent() {
        return this.content[this.category] ?? this.content[''];
    },

    /** Products matching the filters, in the order the server returned them. */
    get results() {
        return this.products;
    },

    get priceInvalid() {
        return this.priceMin !== '' && this.priceMax !== '' && Number(this.priceMax) < Number(this.priceMin);
    },

    /**
     * The categories as the sidebar shows them: each parent followed by its own sub-categories, with
     * `depth` for the indent. CAT-01 is a flat list in name order, so without this a child would sit
     * under whichever parent happens to come before it alphabetically.
     */
    get categoryTree() {
        const ids = new Set(this.categories.map((c) => c.id));
        const children = {};
        for (const c of this.categories) (children[ids.has(c.parent_id) ? c.parent_id : 0] ??= []).push(c);

        const walk = (parent, depth) => (children[parent] ?? []).flatMap((c) => [{ ...c, depth }, ...walk(c.id, depth + 1)]);

        return walk(0, 0);
    },

    /** Product count per category from CAT-01 (a parent includes its sub-categories). */
    countFor(slug) {
        if (!this.categories.length) return '';
        if (slug) return this.categories.find((c) => c.slug === slug)?.product_count ?? 0;
        return this.categories.filter((c) => c.parent_id === null).reduce((sum, c) => sum + c.product_count, 0);
    },

    get hasMore() {
        return this.meta.current_page < this.meta.last_page;
    },

    loadMore() {
        if (this.hasMore && !this.loadingMore) this.fetchPage(this.meta.current_page + 1);
    },

    /** Removable chips for every active filter. */
    get chips() {
        const rupiah = (value) => this.$format.formatRupiah(Number(value));
        const chips = [];
        if (this.q.trim()) chips.push({ key: 'q', label: `“${this.q.trim()}”` });
        if (this.category) chips.push({ key: 'category', label: this.categories.find((c) => c.slug === this.category)?.name ?? this.category });
        if (this.priceMin !== '' || this.priceMax !== '') {
            const label = this.priceMin !== '' && this.priceMax !== ''
                ? `${rupiah(this.priceMin)} – ${rupiah(this.priceMax)}`
                : this.priceMin !== '' ? `≥ ${rupiah(this.priceMin)}` : `≤ ${rupiah(this.priceMax)}`;
            chips.push({ key: 'price', label });
        }
        if (this.inStock) chips.push({ key: 'in_stock', label: 'Stok tersedia' });
        return chips;
    },

    removeChip(key) {
        if (key === 'q') this.q = '';
        if (key === 'category') this.category = '';
        if (key === 'price') this.priceMin = this.priceMax = '';
        if (key === 'in_stock') this.inStock = false;
    },

    reset() {
        this.q = '';
        this.category = '';
        this.inStock = false;
        this.priceMin = '';
        this.priceMax = '';
    },

    // Prices are kept as strings, like the inputs and the URL give them.
    setPrice(min, max) {
        this.priceMin = min == null ? '' : String(min);
        this.priceMax = max == null ? '' : String(max);
    },

    isPrice(min, max) {
        return String(this.priceMin) === (min == null ? '' : String(min)) && String(this.priceMax) === (max == null ? '' : String(max));
    },

    /** Same fallback icon choice as <x-product-card>. */
    iconFor(product) {
        return product.category?.slug === 'baterai' ? 'battery' : 'part';
    },
});
