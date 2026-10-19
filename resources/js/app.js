import Alpine from 'alpinejs';

import authStore from './stores/auth.js';
import cartStore from './stores/cart.js';
import cartPage from './components/cartPage.js';
import checkoutPage from './components/checkoutPage.js';
import productList from './components/productList.js';
import * as staticCart from './static/cart.js';
import { api } from './api/client.js';
import { endpoints } from './api/endpoints.js';
import * as format from './utils/format.js';

Alpine.store('auth', authStore);
Alpine.store('cart', cartStore);

// Page components live in ./components and are registered here.
Alpine.data('cartPage', cartPage);
Alpine.data('checkoutPage', checkoutPage);
Alpine.data('productList', productList);

// Available in Blade expressions: x-text="$format.formatRupiah(variant.price)"
Alpine.magic('format', () => format);

// Handy for debugging in the browser console.
window.Alpine = Alpine;
window.api = { client: api, endpoints };

// Static phase: shared localStorage cart, used by the product page's "Tambah ke keranjang".
window.staticCart = staticCart;

Alpine.start();
