import axios from 'axios';
import httpClient from '../bootstrap';

export class HttpRequestError extends Error {
    constructor(message, { status = 0, errors = {}, retryAfter = null, requestId = null, cause = null } = {}) {
        super(message, { cause });
        this.name = 'HttpRequestError';
        this.status = status;
        this.errors = errors;
        this.retryAfter = retryAfter;
        this.requestId = requestId;
    }
}

const requestIdentifier = () => window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;

function sameOriginPath(url) {
    const parsedUrl = new URL(url, window.location.origin);

    if (!['http:', 'https:'].includes(parsedUrl.protocol) || parsedUrl.origin !== window.location.origin) {
        throw new HttpRequestError('The request was blocked because its destination is not trusted.');
    }

    return `${parsedUrl.pathname}${parsedUrl.search}`;
}

function dispatchSecurityEvent(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail }));
}

function normalizeError(error, requestId) {
    if (error instanceof HttpRequestError) return error;

    if (axios.isCancel(error)) {
        return new HttpRequestError('The request was cancelled.', { requestId, cause: error });
    }

    const status = error.response?.status ?? 0;
    const responseData = error.response?.data ?? {};
    const retryAfterHeader = error.response?.headers?.['retry-after'];
    const retryAfter = retryAfterHeader ? Number.parseInt(retryAfterHeader, 10) : null;

    let message = 'The request could not be completed. Please try again.';
    if (status === 401) message = 'Your session has ended. Please sign in again.';
    else if (status === 403) message = 'You are not authorized to perform this action.';
    else if (status === 419) message = 'Your secure session expired. Refresh the page and try again.';
    else if (status === 422) message = responseData.message || 'Some information needs your attention.';
    else if (status === 429) message = responseData.message || 'Too many requests. Please wait before trying again.';
    else if (status > 0 && status < 500 && responseData.message) message = responseData.message;
    else if (!error.response) message = 'The server could not be reached. Check your connection and try again.';

    const normalized = new HttpRequestError(message, {
        status,
        errors: status === 422 && responseData.errors && typeof responseData.errors === 'object' ? responseData.errors : {},
        retryAfter: Number.isNaN(retryAfter) ? null : retryAfter,
        requestId,
        cause: error,
    });

    if ([401, 403].includes(status)) dispatchSecurityEvent('http:unauthorized', normalized);
    if (status === 419) dispatchSecurityEvent('http:session-expired', normalized);

    return normalized;
}

async function mutate(method, url, payload = {}, options = {}) {
    const requestId = requestIdentifier();
    const headers = {
        ...options.headers,
        'X-Request-ID': requestId,
        ...(method === 'post' ? { 'X-Idempotency-Key': requestIdentifier() } : {}),
    };

    try {
        return await httpClient.request({
            method,
            url: sameOriginPath(url),
            data: payload,
            headers,
            signal: options.signal,
            timeout: options.timeout ?? httpClient.defaults.timeout,
        });
    } catch (error) {
        throw normalizeError(error, requestId);
    }
}

export const store = (url, payload = {}, options = {}) => mutate('post', url, payload, options);
export const update = (url, payload = {}, options = {}) => mutate(options.method === 'patch' ? 'patch' : 'put', url, payload, options);
export const destroy = (url, payload = {}, options = {}) => mutate('delete', url, payload, options);

export default { store, update, destroy };
