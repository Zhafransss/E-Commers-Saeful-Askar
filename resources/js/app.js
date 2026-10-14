import Alpine from 'alpinejs';

import authStore from './stores/auth.js';
import cartStore from './stores/cart.js';
import { api } from './api/client.js';
import { endpoints } from './api/endpoints.js';
import * as format from './utils/format.js';

Alpine.store('auth', authStore);
Alpine.store('cart', cartStore);

// Page components live in ./components and are registered here, e.g.:
//   import productList from './components/productList.js';
//   Alpine.data('productList', productList);

// Available in Blade expressions: x-text="$format.formatRupiah(variant.price)"
Alpine.magic('format', () => format);

// Handy for debugging in the browser console.
window.Alpine = Alpine;
window.api = { client: api, endpoints };

Alpine.start();
