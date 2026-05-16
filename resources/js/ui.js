export function showLogin() {
    document.getElementById('login-screen').style.display = 'flex';
    document.getElementById('main-app').style.display = 'none';
    document.getElementById('login-password').focus();
}

export function showApp() {
    document.getElementById('login-screen').style.display = 'none';
    document.getElementById('main-app').style.display = 'block';
}

export function setLoginError(msg) {
    document.getElementById('login-error').textContent = msg;
}

export function clearLoginError() {
    document.getElementById('login-error').textContent = '';
}

export function setSaving(isSaving) {
    document.getElementById('saving-indicator').textContent = isSaving ? 'Saving…' : '';
}

export function updateOfflineBadge(count) {
    const badge = document.getElementById('offline-badge');
    const countEl = document.getElementById('offline-count');
    countEl.textContent = count;
    badge.classList.toggle('visible', count > 0);
}

export function setupInstallButton(deferredPrompt, onInstall) {
    const banner = document.getElementById('install-banner');
    const btn = document.getElementById('install-btn');
    banner.classList.add('visible');
    btn.addEventListener('click', async () => {
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        if (outcome === 'accepted') {
            banner.classList.remove('visible');
            onInstall();
        }
    });
}

export function renderDay(day) {
    const fields = ['weight_kg', 'kcal', 'protein_g', 'carbs_g', 'fat_g', 'steps', 'sleep_hours', 'lifts', 'notes', 'waist_cm'];
    for (const field of fields) {
        const el = document.getElementById(field);
        if (el) el.value = day?.[field] ?? '';
    }

    const checkboxes = ['refeed', 'photos_taken'];
    for (const field of checkboxes) {
        const el = document.getElementById(field);
        if (el) el.checked = day?.[field] ?? false;
    }

    setActiveButton('session-group', day?.session ?? null);
    setActiveRating('rpe-group', day?.rpe ?? null);
    setActiveRating('hunger-group', day?.hunger ?? null);
    setActiveRating('energy-group', day?.energy ?? null);
}

export function readFormData() {
    const numericFields = ['kcal', 'protein_g', 'carbs_g', 'fat_g', 'steps'];
    const decimalFields = ['weight_kg', 'sleep_hours', 'waist_cm'];
    const textFields = ['lifts', 'notes'];
    const booleanFields = ['refeed', 'photos_taken'];

    const data = {};

    for (const f of numericFields) {
        const val = document.getElementById(f)?.value;
        data[f] = val !== '' && val != null ? parseInt(val, 10) : null;
    }

    for (const f of decimalFields) {
        const el = document.getElementById(f);
        const val = el?.value;
        data[f] = val !== '' && val != null ? parseFloat(val) : null;
    }

    for (const f of textFields) {
        const val = document.getElementById(f)?.value;
        data[f] = val !== '' ? val : null;
    }

    for (const f of booleanFields) {
        data[f] = document.getElementById(f)?.checked ?? false;
    }

    data.session = getActiveSession();
    data.rpe = getActiveRating('rpe-group');
    data.hunger = getActiveRating('hunger-group');
    data.energy = getActiveRating('energy-group');

    return data;
}

export function setActiveButton(groupId, value) {
    const group = document.getElementById(groupId);
    if (!group) return;
    group.querySelectorAll('[data-value]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.value === String(value ?? ''));
    });
}

export function setActiveRating(groupId, value) {
    const group = document.getElementById(groupId);
    if (!group) return;
    group.querySelectorAll('[data-value]').forEach(btn => {
        btn.classList.toggle('active', value != null && Number(btn.dataset.value) === Number(value));
    });
}

function getActiveSession() {
    const active = document.querySelector('#session-group .session-btn.active');
    return active?.dataset.value ?? null;
}

function getActiveRating(groupId) {
    const active = document.querySelector(`#${groupId} .rating-btn.active`);
    return active ? Number(active.dataset.value) : null;
}

export function setupSessionButtons(onToggle) {
    document.getElementById('session-group')?.querySelectorAll('.session-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const isActive = btn.classList.contains('active');
            setActiveButton('session-group', isActive ? null : btn.dataset.value);
            onToggle(isActive ? null : btn.dataset.value);
        });
    });
}

export function setupRatingGroup(groupId, onToggle) {
    document.getElementById(groupId)?.querySelectorAll('.rating-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const isActive = btn.classList.contains('active');
            setActiveRating(groupId, isActive ? null : Number(btn.dataset.value));
            onToggle(isActive ? null : Number(btn.dataset.value));
        });
    });
}
