import { api, ApiError, APP_URL } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

/**
 * Alpine.store('auth'): who is logged in (AUTH-05). A 401 means "guest",
 * not an error to show (auth.md AUTH-05).
 */
export default {
    user: null,
    loaded: false,

    get isLoggedIn() {
        return this.user !== null;
    },

    get isAdmin() {
        return this.user?.role === 'admin';
    },

    async init() {
        window.addEventListener('api:unauthenticated', () => {
            this.user = null;
        });
        await this.refresh();
    },

    async refresh() {
        try {
            const { data } = await api.get(endpoints.auth.me);
            this.user = data;
        } catch (error) {
            // 401 = guest. Anything else (e.g. 501 while the endpoint is still a stub) is logged, not shown.
            if (!(error instanceof ApiError) || error.status !== 401) console.warn('[auth] refresh failed:', error);
            this.user = null;
        } finally {
            this.loaded = true;
        }
    },

    setUser(user) {
        this.user = user;
        this.loaded = true;
    },

    async logout() {
        try {
            await api.post(endpoints.auth.logout);
        } finally {
            this.user = null;
            window.location.href = `${APP_URL}/`;
        }
    },
};
