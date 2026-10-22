import { api, ApiError } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';
import { listState, statusBadge } from './adminList.js';

/**
 * Alpine.data('adminUsers'): /admin/users (docs/api/admin.md ADM-18 list, ADM-19 detail). The customers are
 * only shown: there is nothing here that edits them.
 */
export default () => ({
    ...listState(endpoints.admin.users, { q: '' }),

    detail: null,
    detailLoading: false,
    detailError: null,

    async open(id) {
        this.detail = null;
        this.detailError = null;
        this.detailLoading = true;
        try {
            const { data } = await api.get(endpoints.admin.user(id));
            this.detail = data;
        } catch (error) {
            this.detailError = error instanceof ApiError && error.status === 404 ? 'Pelanggan tidak ditemukan.' : error.message;
        } finally {
            this.detailLoading = false;
        }
    },

    close() {
        this.detail = null;
        this.detailError = null;
        this.detailLoading = false;
    },

    badge: statusBadge,
});
