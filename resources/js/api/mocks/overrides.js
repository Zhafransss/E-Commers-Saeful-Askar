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
export default {
    // Browse as a guest:
    // 'GET /api/v1/auth/me': {
    //     status: 401,
    //     body: { message: 'Silakan masuk terlebih dahulu.', code: 'UNAUTHENTICATED' },
    // },
    //
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
