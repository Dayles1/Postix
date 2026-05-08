// resources/js/request.js
// Pro request utilities (axios kerak bo'ladi — loyihangizda allaqachon mavjud)
// Ikkita eksport: requestRaw (axios response qaytaradi) va requestJson (JSON + auto-toast)

import axios from 'axios';

const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

/**
 * Raw axios wrapper (return whole axios response)
 * @param {string} url
 * @param {object} options { method, data, params, headers, withCredentials }
 */
export async function requestRaw(url, options = {}) {
    const method = (options.method || 'post').toLowerCase();
    const config = {
        method,
        url,
        data: method === 'get' ? undefined : (options.data ?? {}),
        params: method === 'get' ? (options.params ?? options.data ?? {}) : undefined,
        headers: {
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
            ...(options.headers ?? {})
        },
        withCredentials: typeof options.withCredentials === 'undefined' ? true : !!options.withCredentials,
        timeout: options.timeout ?? 20000,
    };

    return axios(config);
}

/**
 * High-level JSON request helper
 * - Expects backend to return { success: boolean, message: string, data: any } ideally.
 * - Auto-dispatches a "toast" event: { type: 'success'|'error', message: '...' }
 * - Throws on network/axios error (but still shows error toast)
 *
 * @param {string} url
 * @param {object} body
 * @param {string} method
 * @param {object} opts { autoToast: boolean }
 * @returns {object} response.data (or raw response fallback)
 */
export async function requestJson(url, body = {}, method = 'post', opts = {}) {
    const autoToast = typeof opts.autoToast === 'undefined' ? true : !!opts.autoToast;
    try {
        const axiosRes = await requestRaw(url, { method, data: body });
        const payload = axiosRes.data ?? axiosRes; // support non-axios fallbacks

        // If backend uses { success: boolean, message: string, data: {...} }
        if (payload && typeof payload.success !== 'undefined') {
            if (autoToast) {
                const msg = payload.message ?? (payload.success ? 'OK' : 'Server error');
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: payload.success ? 'success' : 'error', message: msg }
                }));
            }
            return payload;
        }

        // Fallback: backend didn't return "success" boolean — treat 2xx as success
        if (autoToast) {
            const msg = payload?.message ?? 'Operation completed';
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { type: 'success', message: msg }
            }));
        }
        return payload;

    } catch (err) {
        // Try to get a useful message
        const remoteMsg = err?.response?.data?.message
            || err?.response?.data?.error
            || err?.response?.data
            || err?.message
            || 'Server error';

        if (autoToast) {
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { type: 'error', message: String(remoteMsg) }
            }));
        }

        // Re-throw so caller can handle (e.g., set UI states)
        throw err;
    }
}

/**
 * Convenience: GET wrapper
 */
export async function getJson(url, params = {}, opts = {}) {
    return requestJson(url, params, 'get', opts);
}