import * as db from './db.js';

const TOKEN_KEY = 'cut_token';

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
}

async function request(method, path, body = null) {
    const token = getToken();
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    let response;
    try {
        response = await fetch(`/api${path}`, {
            method,
            headers,
            body: body !== null ? JSON.stringify(body) : undefined,
        });
    } catch {
        throw new OfflineError();
    }

    if (response.status === 401) {
        clearToken();
        throw new AuthError();
    }

    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new ApiError(response.status, data.message ?? 'Request failed');
    }

    if (response.status === 204) return null;
    return response.json();
}

export class OfflineError extends Error {
    constructor() { super('offline'); this.name = 'OfflineError'; }
}

export class AuthError extends Error {
    constructor() { super('unauthenticated'); this.name = 'AuthError'; }
}

export class ApiError extends Error {
    constructor(status, message) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
    }
}

export const getMe = () => request('GET', '/me');
export const login = (password) => request('POST', '/login', { password });
export const logout = () => request('POST', '/logout');

export const getDays = () => request('GET', '/days');
export const getDay = (date) => request('GET', `/days/${date}`);
export const deleteDay = (date) => request('DELETE', `/days/${date}`);

export const getSettings = () => request('GET', '/settings');
export const updateSettings = (data) => request('PUT', '/settings', data);

export const exportData = () => request('GET', '/export');

export async function upsertDay(date, data) {
    try {
        return await request('PUT', `/days/${date}`, data);
    } catch (err) {
        if (err instanceof OfflineError) {
            await db.enqueue('put', date, data);
            return null;
        }
        throw err;
    }
}

export async function syncPending() {
    const ops = await db.getPending();
    if (ops.length === 0) return;

    const payload = ops.map(op => ({ type: op.type, date: op.date, data: op.data }));

    let results;
    try {
        const res = await request('POST', '/sync', { ops: payload });
        results = res.results;
    } catch (err) {
        if (err instanceof OfflineError) return;
        throw err;
    }

    for (let i = 0; i < results.length; i++) {
        if (results[i].success) {
            await db.removeOp(ops[i].id);
        }
    }
}
