import * as api from './api.js';
import * as db from './db.js';
import * as ui from './ui.js';

let currentDate = todayDate();
let saveTimeout = null;

function todayDate() {
    return new Date().toISOString().slice(0, 10);
}

function stepDate(iso, days) {
    const d = new Date(iso);
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

async function loadDay(date) {
    let day = null;
    try {
        day = await api.getDay(date);
    } catch (err) {
        if (!(err instanceof api.ApiError && err.status === 404) && !(err instanceof api.OfflineError)) {
            console.error('loadDay error', err);
        }
    }
    ui.renderDay(day);
    document.getElementById('date-picker').value = date;
}

function scheduleSave() {
    clearTimeout(saveTimeout);
    ui.setSaving(false);
    saveTimeout = setTimeout(() => saveCurrentDay(), 500);
}

async function saveCurrentDay() {
    ui.setSaving(true);
    const data = ui.readFormData();

    try {
        await api.upsertDay(currentDate, data);
        ui.setSaving(false);
    } catch (err) {
        ui.setSaving(false);
        if (err instanceof api.OfflineError) {
            await refreshOfflineBadge();
        }
    }
}

async function refreshOfflineBadge() {
    const n = await db.count();
    ui.updateOfflineBadge(n);
}

async function attemptSync() {
    try {
        await api.syncPending();
        await refreshOfflineBadge();
    } catch {
        // silent - will retry on next online event
    }
}

async function initApp() {
    ui.showApp();

    document.getElementById('date-picker').value = currentDate;

    await loadDay(currentDate);
    await refreshOfflineBadge();

    if (navigator.onLine) {
        attemptSync();
    }

    window.addEventListener('online', () => attemptSync());

    document.getElementById('date-picker').addEventListener('change', async (e) => {
        currentDate = e.target.value;
        await loadDay(currentDate);
    });

    document.getElementById('prev-day').addEventListener('click', async () => {
        currentDate = stepDate(currentDate, -1);
        await loadDay(currentDate);
    });

    document.getElementById('next-day').addEventListener('click', async () => {
        currentDate = stepDate(currentDate, 1);
        await loadDay(currentDate);
    });

    const inputFields = ['weight_kg', 'kcal', 'protein_g', 'carbs_g', 'fat_g', 'steps', 'sleep_hours', 'lifts', 'notes', 'waist_cm'];
    for (const id of inputFields) {
        document.getElementById(id)?.addEventListener('input', scheduleSave);
    }

    document.getElementById('refeed').addEventListener('change', scheduleSave);
    document.getElementById('photos_taken').addEventListener('change', scheduleSave);

    ui.setupSessionButtons(() => scheduleSave());
    ui.setupRatingGroup('rpe-group', () => scheduleSave());
    ui.setupRatingGroup('hunger-group', () => scheduleSave());
    ui.setupRatingGroup('energy-group', () => scheduleSave());

    document.getElementById('logout-btn').addEventListener('click', async () => {
        try { await api.logout(); } catch { /* ignore */ }
        api.clearToken();
        ui.showLogin();
    });

    document.getElementById('export-btn').addEventListener('click', async () => {
        try {
            const data = await api.exportData();
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `cut-export-${todayDate()}.json`;
            a.click();
            URL.revokeObjectURL(url);
        } catch (err) {
            console.error('export failed', err);
        }
    });

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        ui.setupInstallButton(e, () => {});
    });
}

async function initLogin() {
    ui.showLogin();

    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        ui.clearLoginError();
        const password = document.getElementById('login-password').value;
        const btn = document.getElementById('login-btn');
        btn.disabled = true;
        btn.textContent = 'Signing in…';

        try {
            const res = await api.login(password);
            api.setToken(res.token);
            document.getElementById('login-password').value = '';
            await initApp();
        } catch (err) {
            ui.setLoginError(err instanceof api.ApiError ? err.message : 'Could not connect. Try again.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Sign in';
        }
    });
}

document.addEventListener('DOMContentLoaded', async () => {
    if (api.getToken()) {
        try {
            await api.getMe();
            await initApp();
        } catch (err) {
            if (err instanceof api.AuthError) {
                await initLogin();
            } else if (err instanceof api.OfflineError) {
                await initApp();
            } else {
                await initLogin();
            }
        }
    } else {
        await initLogin();
    }
});
