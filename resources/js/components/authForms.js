import { api, ApiError, APP_URL } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

/**
 * Alpine.data components for the auth pages (docs/api/auth.md AUTH-02, AUTH-03,
 * AUTH-08, AUTH-09). Each one is a form with the same state: `form` (the request
 * body), `errors` (per-field messages from a 422), `message` (an error not tied to a
 * field, like INVALID_CREDENTIALS or 429) and `busy` (blocks a double submit).
 */
function authForm(fields, send) {
    return {
        form: { ...fields },
        errors: {},
        message: null,
        success: null,
        busy: false,

        fieldError(name) {
            return this.errors[name]?.[0] ?? null;
        },

        async submit() {
            if (this.busy) return;
            this.busy = true;
            this.errors = {};
            this.message = null;
            this.success = null;

            try {
                await send.call(this);
            } catch (error) {
                if (!(error instanceof ApiError)) throw error;
                this.errors = error.errors;
                // Field errors already sit under their inputs; the banner is for everything else.
                this.message = error.code === 'VALIDATION_ERROR' ? 'Periksa kembali isian yang ditandai.' : error.message;
            } finally {
                this.busy = false;
            }
        },
    };
}

/** Where to go after logging in: the page that sent the guest here, else the home page. */
const destination = (intended) => (intended ? intended : `${APP_URL}/`);

/** AUTH-03. `intended` is the URL the page guard stored, if any (see AuthPageController). */
export const loginForm = ({ intended = null } = {}) =>
    authForm({ email: '', password: '', remember: false }, async function () {
        const { data } = await api.post(endpoints.auth.login, this.form);
        this.$store.auth.setUser(data);
        window.location.href = destination(intended);
    });

/** AUTH-02. The user is logged in by the response itself. */
export const registerForm = ({ intended = null } = {}) => ({
    ...authForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' }, async function () {
        const { data } = await api.post(endpoints.auth.register, this.form);
        this.$store.auth.setUser(data);
        window.location.href = destination(intended);
    }),

    // Live checklist next to the password fields; the server stays the judge.
    get passwordLongEnough() {
        return this.form.password.length >= 8;
    },
    get passwordHasLettersAndDigits() {
        return /[A-Za-z]/.test(this.form.password) && /\d/.test(this.form.password);
    },
    get passwordsMatch() {
        return this.form.password_confirmation.length > 0 && this.form.password_confirmation === this.form.password;
    },
});

/** AUTH-08. The answer is the same whether or not the email exists. */
export const forgotPasswordForm = () =>
    authForm({ email: '' }, async function () {
        const { message } = await api.post(endpoints.auth.forgotPassword, this.form);
        this.success = message;
    });

/** AUTH-09. `token` and `email` come from the link in the AUTH-08 email. */
export const resetPasswordForm = ({ token, email }) =>
    authForm({ token, email, password: '', password_confirmation: '' }, async function () {
        const { message } = await api.post(endpoints.auth.resetPassword, this.form);
        this.success = message;
    });
