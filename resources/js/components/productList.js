/**
 * Alpine.data('productList'): the /products catalog (docs/api/catalog.md CAT-01, CAT-02).
 *
 * Static phase: filters, sorts, and pages a list rendered by Blade, entirely in the
 * browser. The state mirrors the CAT-02 query (q, category, in_stock, price_min,
 * price_max, sort, page) and is kept in the URL, so moving to the API later is a
 * matter of sending `query` to endpoints.products.index instead of filtering here.
 */
const SORTS = {
    latest: 'Terbaru',
    price_asc: 'Harga terendah',
    price_desc: 'Harga tertinggi',
    name_asc: 'Nama A–Z',
};

const PER_PAGE = 9;

/** Lowercase, accent- and punctuation-insensitive text for matching. */
const normalize = (text) =>
    String(text ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();

export default ({ products, categories, content }) => ({
    products,
    categories,
    content,
    sorts: SORTS,

    q: '',
    category: '',
    inStock: false,
    priceMin: '',
    priceMax: '',
    sort: 'latest',
    shown: PER_PAGE,
    filtersOpen: false,

    init() {
        const params = new URLSearchParams(window.location.search);
        this.q = params.get('q') ?? '';
        this.category = params.get('category') ?? '';
        this.inStock = params.get('in_stock') === '1';
        this.priceMin = params.get('price_min') ?? '';
        this.priceMax = params.get('price_max') ?? '';
        this.sort = SORTS[params.get('sort')] ? params.get('sort') : 'latest';

        // Any filter change restarts paging and is written back to the URL.
        this.$watch('query', () => {
            this.shown = PER_PAGE;
            this.syncUrl();
        });

        // Keep the tab title in step with the category copy, as the server renders it.
        this.$watch('category', () => (document.title = `${this.pageContent.title} | Saeful Askar`));
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

    get currentCategory() {
        return this.categories.find((c) => c.slug === this.category) ?? null;
    },

    /** SEO copy (h1, intro, body) for the selected category; falls back to the full catalog's. */
    get pageContent() {
        return this.content[this.category] ?? this.content[''];
    },

    /** Category slug plus its children: a parent shows its sub-categories' products (CAT-02). */
    categorySlugs(slug) {
        const parent = this.categories.find((c) => c.slug === slug);
        if (!parent) return [slug];
        return [slug, ...this.categories.filter((c) => c.parent_id === parent.id).map((c) => c.slug)];
    },

    get priceInvalid() {
        return this.priceMin !== '' && this.priceMax !== '' && Number(this.priceMax) < Number(this.priceMin);
    },

    matches(product, { ignoreCategory = false } = {}) {
        const terms = normalize(this.q).split(' ').filter(Boolean);
        if (terms.length && !terms.every((term) => product.search.includes(term))) return false;
        if (!ignoreCategory && this.category && !this.categorySlugs(this.category).includes(product.category.slug)) return false;
        if (this.inStock && !product.in_stock) return false;
        if (!this.priceInvalid) {
            // A product matches when any variant price falls in the range (CAT-02).
            if (this.priceMin !== '' && product.price_max < Number(this.priceMin)) return false;
            if (this.priceMax !== '' && product.price_min > Number(this.priceMax)) return false;
        }
        return true;
    },

    get results() {
        const list = this.products.filter((product) => this.matches(product));
        const byName = (a, b) => a.name.localeCompare(b.name, 'id');
        const sorters = {
            latest: (a, b) => a.position - b.position,
            price_asc: (a, b) => a.price_min - b.price_min || byName(a, b),
            price_desc: (a, b) => b.price_max - a.price_max || byName(a, b),
            name_asc: byName,
        };
        return list.sort(sorters[this.sort]);
    },

    /** Display position of a product, or -1 when it is filtered out or beyond the loaded pages. */
    rank(id) {
        const index = this.results.findIndex((product) => product.id === id);
        return index < this.shown ? index : -1;
    },

    /** Count per category for the current search/stock/price, ignoring the category filter itself. */
    countFor(slug) {
        const slugs = slug ? this.categorySlugs(slug) : null;
        return this.products.filter((p) => this.matches(p, { ignoreCategory: true }) && (!slugs || slugs.includes(p.category.slug))).length;
    },

    get hasMore() {
        return this.results.length > this.shown;
    },

    loadMore() {
        this.shown += PER_PAGE;
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
});
