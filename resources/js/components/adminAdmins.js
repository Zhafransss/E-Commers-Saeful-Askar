import { api, ApiError } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';
import { listState } from './adminList.js';

const emptyForm = () => ({ name: '', email: '', password: '', password_confirmation: '' });

/**
 * Alpine.data('adminAdmins'): /admin/admins (docs/api/admin.md ADM-25 list, ADM-26 create, ADM-27 update,
 * ADM-28 delete). One form serves both adding and editing; the password is only sent when it is filled in.
 */
export default () => ({
    ...listState(endpoints.admin.admins, { q: '' }),

    // null = form closed, 0 = adding, otherwise the id being edited
    editing: null,
    form: emptyForm(),
    fieldErrors: {},
    formError: null,
    saving: false,

    removing: null,
    removeError: null,
    removeBusy: false,

    notice: null,

    openCreate() {
        this.form = emptyForm();
        this.fieldErrors = {};
        this.formError = null;
        this.editing = 0;
    },

    openEdit(admin) {
        this.form = { ...emptyForm(), name: admin.name, email: admin.email };
        this.fieldErrors = {};
        this.formError = null;
        this.editing = admin.id;
    },

    closeForm() {
        this.editing = null;
    },

    async save() {
        this.saving = true;
        this.fieldErrors = {};
        this.formError = null;
        try {
            const creating = this.editing === 0;
            if (creating) {
                await api.post(endpoints.admin.admins, this.form);
            } else {
                await api.put(endpoints.admin.admin(this.editing), this.form);
            }
            this.notice = creating ? 'Admin ditambahkan.' : 'Perubahan disimpan.';
            this.editing = null;
            await this.fetchPage(creating ? 1 : this.meta.current_page);
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                this.fieldErrors = Object.fromEntries(Object.entries(error.errors).map(([field, messages]) => [field, messages[0]]));
            } else {
                this.formError = error.message;
            }
        } finally {
            this.saving = false;
        }
    },

    askRemove(admin) {
        this.removing = admin;
        this.removeError = null;
    },

    async remove() {
        this.removeBusy = true;
        this.removeError = null;
        try {
            await api.delete(endpoints.admin.admin(this.removing.id));
            this.notice = `${this.removing.name} dihapus.`;
            this.removing = null;
            // Deleting the only row of the last page would leave an empty page behind.
            await this.fetchPage(this.items.length === 1 && this.meta.current_page > 1 ? this.meta.current_page - 1 : this.meta.current_page);
        } catch (error) {
            this.removeError = error.message;
        } finally {
            this.removeBusy = false;
        }
    },
});
