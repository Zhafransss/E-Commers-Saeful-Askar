import { api } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';
import { listState, statusBadge } from './adminList.js';

/**
 * Alpine.data('adminShipments'): /admin/shipments (docs/api/admin.md ADM-21 list, ADM-22 take resi from
 * Jubelio). The resi is read from the order's Jubelio sales order; nobody types one in here.
 */
export default () => ({
    ...listState(endpoints.admin.shipments, { q: '', resi: '' }),

    refreshing: false,
    notice: null,
    actionError: null,

    // ADM-22
    async refreshFromJubelio() {
        if (this.refreshing) return;
        this.refreshing = true;
        this.notice = null;
        this.actionError = null;
        try {
            const { message } = await api.post(endpoints.admin.refreshShipments);
            this.notice = message;
            await this.fetchPage(this.meta.current_page);
        } catch (error) {
            this.actionError = error.message;
        } finally {
            this.refreshing = false;
        }
    },

    badge: statusBadge,
});
