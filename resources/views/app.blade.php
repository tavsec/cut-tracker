<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Cut">
    <title>Cut Tracker</title>

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

{{-- Login Screen --}}
<div id="login-screen" style="display:none">
    <h1>Cut Tracker</h1>
    <p>Enter your password to continue.</p>
    <form class="login-form" id="login-form">
        <input type="password" id="login-password" placeholder="Password" autocomplete="current-password" required>
        <div class="login-error" id="login-error"></div>
        <button type="submit" class="btn-primary" id="login-btn">Sign in</button>
    </form>
</div>

{{-- Main App --}}
<div id="main-app" style="display:none">

    {{-- Install Banner --}}
    <div id="install-banner">
        <span>Install app for offline use</span>
        <button id="install-btn">Install</button>
    </div>

    <div id="app">

        {{-- Top Nav --}}
        <nav id="top-nav">
            <button class="btn-icon" id="prev-day" aria-label="Previous day">&#8249;</button>
            <input type="date" id="date-picker" aria-label="Select date">
            <button class="btn-icon" id="next-day" aria-label="Next day">&#8250;</button>
        </nav>

        {{-- Body Metrics --}}
        <section class="card" id="section-body">
            <div class="card-title">Body</div>
            <div class="field-grid">
                <div>
                    <label class="field-label" for="weight_kg">Weight (kg)</label>
                    <input type="number" id="weight_kg" name="weight_kg" step="0.1" min="0" max="999" placeholder="—">
                </div>
                <div>
                    <label class="field-label" for="waist_cm">Waist (cm)</label>
                    <input type="number" id="waist_cm" name="waist_cm" step="0.1" min="0" max="999" placeholder="—">
                </div>
            </div>
            <div class="toggle-row" style="margin-top:12px">
                <span class="toggle-label">Photos taken</span>
                <label class="toggle">
                    <input type="checkbox" id="photos_taken" name="photos_taken">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </section>

        {{-- Nutrition --}}
        <section class="card" id="section-nutrition">
            <div class="card-title">Nutrition</div>
            <div class="field-grid">
                <div>
                    <label class="field-label" for="kcal">Calories</label>
                    <input type="number" id="kcal" name="kcal" min="0" placeholder="—" inputmode="numeric">
                </div>
                <div>
                    <label class="field-label" for="protein_g">Protein (g)</label>
                    <input type="number" id="protein_g" name="protein_g" min="0" placeholder="—" inputmode="numeric">
                </div>
                <div>
                    <label class="field-label" for="carbs_g">Carbs (g)</label>
                    <input type="number" id="carbs_g" name="carbs_g" min="0" placeholder="—" inputmode="numeric">
                </div>
                <div>
                    <label class="field-label" for="fat_g">Fat (g)</label>
                    <input type="number" id="fat_g" name="fat_g" min="0" placeholder="—" inputmode="numeric">
                </div>
            </div>
            <div class="toggle-row" style="margin-top:12px">
                <span class="toggle-label">Refeed day</span>
                <label class="toggle">
                    <input type="checkbox" id="refeed" name="refeed">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </section>

        {{-- Training --}}
        <section class="card" id="section-training">
            <div class="card-title">Training</div>
            <div class="session-grid" id="session-group">
                <button type="button" class="session-btn" data-value="Push">Push</button>
                <button type="button" class="session-btn" data-value="Pull">Pull</button>
                <button type="button" class="session-btn" data-value="Legs">Legs</button>
                <button type="button" class="session-btn" data-value="Other">Other</button>
            </div>
            <div style="margin-top:10px">
                <label class="field-label">RPE</label>
                <div class="rating-group" id="rpe-group">
                    @for($i = 1; $i <= 10; $i++)
                    <button type="button" class="rating-btn" data-value="{{ $i }}">{{ $i }}</button>
                    @endfor
                </div>
            </div>
            <div style="margin-top:10px">
                <label class="field-label" for="lifts">Lifts</label>
                <textarea id="lifts" name="lifts" placeholder="Squat 3×5 @ 100kg..."></textarea>
            </div>
        </section>

        {{-- Wellness --}}
        <section class="card" id="section-wellness">
            <div class="card-title">Wellness</div>
            <div class="field-grid">
                <div>
                    <label class="field-label" for="sleep_hours">Sleep (h)</label>
                    <input type="number" id="sleep_hours" name="sleep_hours" step="0.5" min="0" max="24" placeholder="—">
                </div>
                <div>
                    <label class="field-label" for="steps">Steps</label>
                    <input type="number" id="steps" name="steps" min="0" placeholder="—" inputmode="numeric">
                </div>
            </div>
            <div style="margin-top:10px">
                <label class="field-label">Hunger (1–5)</label>
                <div class="rating-group" id="hunger-group">
                    @for($i = 1; $i <= 5; $i++)
                    <button type="button" class="rating-btn" data-value="{{ $i }}">{{ $i }}</button>
                    @endfor
                </div>
            </div>
            <div style="margin-top:10px">
                <label class="field-label">Energy (1–5)</label>
                <div class="rating-group" id="energy-group">
                    @for($i = 1; $i <= 5; $i++)
                    <button type="button" class="rating-btn" data-value="{{ $i }}">{{ $i }}</button>
                    @endfor
                </div>
            </div>
        </section>

        {{-- Notes --}}
        <section class="card" id="section-notes">
            <div class="card-title">Notes</div>
            <div class="field-grid full">
                <textarea id="notes" name="notes" placeholder="Anything to note..."></textarea>
            </div>
        </section>

    </div>{{-- /#app --}}

    {{-- Status Bar --}}
    <div id="status-bar">
        <span id="saving-indicator"></span>
        <span id="offline-badge">↑ <span id="offline-count">0</span> unsaved</span>
        <div class="status-actions">
            <button class="btn-secondary" id="export-btn" title="Export data">Export</button>
            <button class="btn-secondary" id="logout-btn" title="Log out">Log out</button>
        </div>
    </div>

</div>{{-- /#main-app --}}

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js');
        });
    }
</script>

</body>
</html>
