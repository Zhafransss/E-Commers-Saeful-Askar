/**
 * Hand-written mock responses that win over contract.json. Owner: frontend.
 *
 * Key: "METHOD /api/v1/path" with {param} placeholders, same as contract.json.
 * Value: { status, body } or a function ({ params, query, body }) => { status, body }.
 * A status >= 400 is thrown as ApiError, exactly like a real backend error.
 *
 * Use this to try error states (copy the "Response Failed" examples from docs/api)
 * or to make a mock react to input. Keep shapes identical to the contract.
 */
import contract from './contract.json';

/*
| Mock session: contract.json always answers AUTH-05 with a logged-in user, so
| logging out would undo itself on the next page. The session state is kept in
| localStorage: logout makes AUTH-05 answer 401 (guest), login/register restore it.
*/
const SESSION_KEY = 'saefulaskar.mock-session';

const setSession = (state) => {
    try {
        window.localStorage.setItem(SESSION_KEY, state);
    } catch {
        // Storage unavailable: the mock stays logged in.
    }
};

const isGuest = () => {
    try {
        return window.localStorage.getItem(SESSION_KEY) === 'guest';
    } catch {
        return false;
    }
};

const UNAUTHENTICATED = { status: 401, body: { message: 'Silakan masuk terlebih dahulu.', code: 'UNAUTHENTICATED' } };

export default {
    'GET /api/v1/auth/me': () => (isGuest() ? UNAUTHENTICATED : contract['GET /api/v1/auth/me']),

    'POST /api/v1/auth/logout': () => {
        setSession('guest');
        return contract['POST /api/v1/auth/logout'];
    },

    'POST /api/v1/auth/login': () => {
        setSession('user');
        return contract['POST /api/v1/auth/login'];
    },

    'POST /api/v1/auth/register': () => {
        setSession('user');
        return contract['POST /api/v1/auth/register'];
    },

    // Out-of-stock error when adding to cart (cart.md CART-02):
    // 'POST /api/v1/cart/items': {
    //     status: 409,
    //     body: {
    //         message: 'Stok tidak cukup. Tersisa 4 unit.',
    //         code: 'OUT_OF_STOCK',
    //         errors: { qty: ['Maksimal 4 unit untuk varian ini.'] },
    //         available_qty: 4,
    //     },
    // },
};
