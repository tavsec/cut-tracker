# Cut Tracker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a single-user PWA fitness cut-tracker with offline support, Laravel 13 backend, SQLite persistence, and Kubernetes deployment.

**Architecture:** Laravel 13 API backend with Sanctum token auth; single-user password checked against `APP_PASSWORD_HASH` env var; vanilla JS SPA served from one Blade view; Workbox service worker with IndexedDB offline queue; multi-stage Docker build with nginx+php-fpm in one container.

**Tech Stack:** PHP 8.3, Laravel 13, Sanctum, SQLite, Pest 4, Tailwind v4, Vite 8, Workbox (CDN), IndexedDB, Docker (nginx + php-fpm + supervisord)

---

## File Map

### Created
- `routes/api.php` — all API route definitions
- `database/migrations/2026_05_16_000001_create_days_table.php`
- `database/migrations/2026_05_16_000002_create_settings_table.php`
- `app/Models/Day.php`
- `app/Models/Setting.php`
- `app/Console/Commands/HashPassword.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Api/DayController.php`
- `app/Http/Controllers/Api/SettingController.php`
- `app/Http/Controllers/Api/ExportController.php`
- `app/Http/Controllers/Api/SyncController.php`
- `tests/Feature/AuthTest.php`
- `tests/Feature/DaysTest.php`
- `tests/Feature/SettingsTest.php`
- `tests/Feature/ExportTest.php`
- `tests/Feature/SyncTest.php`
- `resources/views/app.blade.php` — SPA shell
- `resources/js/db.js` — IndexedDB wrapper
- `resources/js/api.js` — fetch wrapper + offline queue
- `resources/js/ui.js` — DOM manipulation + render functions
- `public/sw.js` — Workbox service worker
- `public/manifest.webmanifest`
- `public/icons/icon-192.png` and `icon-512.png`
- `Dockerfile`
- `docker/nginx.conf`
- `docker/supervisord.conf`
- `docker/entrypoint.sh`
- `k8s/deployment.yaml`, `service.yaml`, `pvc.yaml`, `secret.yaml.example`, `ingress.yaml`, `kustomization.yaml`

### Modified
- `bootstrap/app.php` — add API routing + Sanctum stateful middleware
- `app/Models/User.php` — add `HasApiTokens`
- `database/seeders/DatabaseSeeder.php` — seed single app user
- `config/app.php` — add `password_hash` entry
- `resources/js/app.js` — SPA bootstrap
- `resources/css/app.css` — dark theme tokens
- `routes/web.php` — serve app.blade.php for all non-API routes
- `.env` / `.env.example` — add `APP_PASSWORD_HASH`

---

## Task 1: Install Sanctum + configure API routing

**Files:**
- Modify: `bootstrap/app.php`
- Modify: `app/Models/User.php`
- Create: `routes/api.php`
- Modify: `composer.json` (via composer require)
- Modify: `config/sanctum.php` (via vendor:publish)

- [ ] **Step 1: Require Sanctum**

```bash
cd /home/timotej/Documents/projects/cut-tracker && composer require laravel/sanctum --no-interaction
```

Expected: resolves and installs `laravel/sanctum`.

- [ ] **Step 2: Publish Sanctum config and migration**

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --no-interaction
```

Expected: publishes `config/sanctum.php` and `database/migrations/*_create_personal_access_tokens_table.php`.

- [ ] **Step 3: Set token expiration in config/sanctum.php**

Open `config/sanctum.php`. Find the `expiration` key and set it to `43200` (30 days in minutes):

```php
'expiration' => env('SANCTUM_EXPIRATION', 43200),
```

- [ ] **Step 4: Create routes/api.php**

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DayController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/health', [AuthController::class, 'health']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/days', [DayController::class, 'index']);
    Route::get('/days/{date}', [DayController::class, 'show']);
    Route::put('/days/{date}', [DayController::class, 'upsert']);
    Route::delete('/days/{date}', [DayController::class, 'destroy']);

    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);

    Route::get('/export', [ExportController::class, 'export']);
    Route::post('/sync', [SyncController::class, 'sync']);
});
```

- [ ] **Step 5: Register API routes in bootstrap/app.php**

Replace the `withRouting` call to add `api:`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

- [ ] **Step 6: Add HasApiTokens to User model**

Open `app/Models/User.php` and add the trait:

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

- [ ] **Step 7: Verify routes are registered**

```bash
php artisan route:list --path=api
```

Expected: shows `/api/login`, `/api/health`, and the protected routes.

- [ ] **Step 8: Commit**

```bash
git init && git add -A && git commit -m "feat: install Sanctum and configure API routing"
```

---

## Task 2: Migrations

**Files:**
- Create: `database/migrations/2026_05_16_000001_create_days_table.php`
- Create: `database/migrations/2026_05_16_000002_create_settings_table.php`

- [ ] **Step 1: Generate days migration**

```bash
php artisan make:migration create_days_table --no-interaction
```

- [ ] **Step 2: Write days migration**

Open the generated file and replace its `up()` content:

```php
public function up(): void
{
    Schema::create('days', function (Blueprint $table) {
        $table->id();
        $table->date('date')->unique();
        $table->decimal('weight_kg', 5, 2)->nullable();
        $table->integer('kcal')->nullable();
        $table->integer('protein_g')->nullable();
        $table->integer('carbs_g')->nullable();
        $table->integer('fat_g')->nullable();
        $table->integer('steps')->nullable();
        $table->decimal('sleep_hours', 3, 1)->nullable();
        $table->tinyInteger('hunger')->nullable();
        $table->tinyInteger('energy')->nullable();
        $table->boolean('refeed')->default(false);
        $table->enum('session', ['Push', 'Pull', 'Legs', 'Other'])->nullable();
        $table->decimal('rpe', 3, 1)->nullable();
        $table->text('lifts')->nullable();
        $table->text('notes')->nullable();
        $table->decimal('waist_cm', 5, 1)->nullable();
        $table->boolean('photos_taken')->default(false);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('days');
}
```

- [ ] **Step 3: Generate settings migration**

```bash
php artisan make:migration create_settings_table --no-interaction
```

- [ ] **Step 4: Write settings migration**

```php
public function up(): void
{
    Schema::create('settings', function (Blueprint $table) {
        $table->string('key')->primary();
        $table->text('value')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('settings');
}
```

- [ ] **Step 5: Run migrations**

```bash
php artisan migrate --no-interaction
```

Expected: runs all migrations including personal_access_tokens.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/ && git commit -m "feat: add days and settings migrations"
```

---

## Task 3: Models

**Files:**
- Create: `app/Models/Day.php`
- Create: `app/Models/Setting.php`

- [ ] **Step 1: Create Day model**

```bash
php artisan make:model Day --no-interaction
```

- [ ] **Step 2: Write Day model**

Replace `app/Models/Day.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Day extends Model
{
    protected $fillable = [
        'date',
        'weight_kg',
        'kcal',
        'protein_g',
        'carbs_g',
        'fat_g',
        'steps',
        'sleep_hours',
        'hunger',
        'energy',
        'refeed',
        'session',
        'rpe',
        'lifts',
        'notes',
        'waist_cm',
        'photos_taken',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'weight_kg' => 'decimal:2',
            'sleep_hours' => 'decimal:1',
            'rpe' => 'decimal:1',
            'waist_cm' => 'decimal:1',
            'refeed' => 'boolean',
            'photos_taken' => 'boolean',
        ];
    }
}
```

- [ ] **Step 3: Create Setting model**

```bash
php artisan make:model Setting --no-interaction
```

- [ ] **Step 4: Write Setting model**

Replace `app/Models/Setting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['key', 'value'];
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/ && git commit -m "feat: add Day and Setting models"
```

---

## Task 4: App config, HashPassword command, Seeder

**Files:**
- Modify: `config/app.php`
- Create: `app/Console/Commands/HashPassword.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `.env` and `.env.example`

- [ ] **Step 1: Add password_hash to config/app.php**

Open `config/app.php`. Find the array return and add before the closing bracket:

```php
'password_hash' => env('APP_PASSWORD_HASH'),
```

- [ ] **Step 2: Create HashPassword command**

```bash
php artisan make:command HashPassword --no-interaction
```

- [ ] **Step 3: Write HashPassword command**

Replace `app/Console/Commands/HashPassword.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class HashPassword extends Command
{
    protected $signature = 'app:hash-password';
    protected $description = 'Hash a password for use in APP_PASSWORD_HASH env var';

    public function handle(): void
    {
        $password = $this->secret('Enter password');
        $this->line(Hash::make($password));
    }
}
```

- [ ] **Step 4: Update DatabaseSeeder**

Replace `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (User::count() === 0) {
            User::create([
                'name' => 'Owner',
                'email' => 'owner@cut-tracker.local',
                'password' => bcrypt('changeme'),
            ]);
        }
    }
}
```

- [ ] **Step 5: Add APP_PASSWORD_HASH to .env and .env.example**

In `.env`, add after `APP_DEBUG`:
```
APP_PASSWORD_HASH=
```

In `.env.example`, add after `APP_DEBUG`:
```
APP_PASSWORD_HASH=
```

- [ ] **Step 6: Run seeder**

```bash
php artisan db:seed --no-interaction
```

Expected: creates the single user row.

- [ ] **Step 7: Commit**

```bash
git add app/Console/ config/app.php database/seeders/ .env.example && git commit -m "feat: add HashPassword command, seed single user"
```

---

## Task 5: AuthController + AuthTest

**Files:**
- Create: `app/Http/Controllers/Api/AuthController.php`
- Create: `tests/Feature/AuthTest.php`

- [ ] **Step 1: Create controller directory and AuthController**

```bash
mkdir -p app/Http/Controllers/Api
php artisan make:controller Api/AuthController --no-interaction
```

- [ ] **Step 2: Write AuthController**

Replace `app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many login attempts.'], 429);
        }

        $password = $request->input('password', '');
        $hash = config('app.password_hash');

        if (! $hash || ! Hash::check($password, $hash)) {
            RateLimiter::hit($key, 60);

            return response()->json(['message' => 'Invalid password.'], 401);
        }

        RateLimiter::clear($key);

        $user = User::first();
        $user->tokens()->where('name', 'api')->delete();
        $token = $user->createToken('api', ['*'], now()->addDays(30));

        return response()->json(['token' => $token->plainTextToken]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(): JsonResponse
    {
        return response()->json(['authenticated' => true]);
    }

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
```

- [ ] **Step 3: Enable RefreshDatabase in Pest.php**

Open `tests/Pest.php` and uncomment/add `RefreshDatabase`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

- [ ] **Step 4: Create AuthTest**

```bash
php artisan make:test AuthTest --pest --no-interaction
```

- [ ] **Step 5: Write AuthTest**

Replace `tests/Feature/AuthTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config(['app.password_hash' => Hash::make('secret')]);
    User::factory()->create(['email' => 'owner@cut-tracker.local']);
});

test('health endpoint returns 200 without auth', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

test('login returns token with correct password', function () {
    $this->postJson('/api/login', ['password' => 'secret'])
        ->assertOk()
        ->assertJsonStructure(['token']);
});

test('login returns 401 with wrong password', function () {
    $this->postJson('/api/login', ['password' => 'wrong'])
        ->assertUnauthorized();
});

test('login is rate limited after 5 attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', ['password' => 'wrong']);
    }

    $this->postJson('/api/login', ['password' => 'wrong'])
        ->assertStatus(429);
});

test('me returns authenticated true with valid token', function () {
    $user = User::first();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->getJson('/api/me')
        ->assertOk()
        ->assertJson(['authenticated' => true]);
});

test('me returns 401 without token', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

test('logout revokes token', function () {
    $user = User::first();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->postJson('/api/logout')->assertNoContent();
    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});
```

- [ ] **Step 6: Run AuthTest**

```bash
php artisan test --compact --filter=AuthTest
```

Expected: all 7 tests pass.

- [ ] **Step 7: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php tests/Feature/AuthTest.php tests/Pest.php && git commit -m "feat: add AuthController with login/logout/me + tests"
```

---

## Task 6: DayController + DaysTest

**Files:**
- Create: `app/Http/Controllers/Api/DayController.php`
- Create: `tests/Feature/DaysTest.php`

- [ ] **Step 1: Create DayController**

```bash
php artisan make:controller Api/DayController --no-interaction
```

- [ ] **Step 2: Write DayController**

Replace `app/Http/Controllers/Api/DayController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Day;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DayController extends Controller
{
    private const DATE_PATTERN = 'required|date_format:Y-m-d';

    public function index(): JsonResponse
    {
        return response()->json(Day::orderBy('date')->get());
    }

    public function show(string $date): JsonResponse
    {
        $day = Day::where('date', $date)->first();

        if (! $day) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($day);
    }

    public function upsert(Request $request, string $date): JsonResponse
    {
        if (! $this->isValidDate($date)) {
            return response()->json(['message' => 'Invalid date format.'], 422);
        }

        $validated = $request->validate([
            'weight_kg' => 'nullable|numeric|between:0,999.99',
            'kcal' => 'nullable|integer|min:0',
            'protein_g' => 'nullable|integer|min:0',
            'carbs_g' => 'nullable|integer|min:0',
            'fat_g' => 'nullable|integer|min:0',
            'steps' => 'nullable|integer|min:0',
            'sleep_hours' => 'nullable|numeric|between:0,24',
            'hunger' => 'nullable|integer|between:1,5',
            'energy' => 'nullable|integer|between:1,5',
            'refeed' => 'nullable|boolean',
            'session' => ['nullable', Rule::in(['Push', 'Pull', 'Legs', 'Other'])],
            'rpe' => 'nullable|numeric|between:0,10',
            'lifts' => 'nullable|string',
            'notes' => 'nullable|string',
            'waist_cm' => 'nullable|numeric|between:0,999.9',
            'photos_taken' => 'nullable|boolean',
        ]);

        $day = Day::updateOrCreate(
            ['date' => $date],
            $request->only(array_keys($validated))
        );

        return response()->json($day);
    }

    public function destroy(string $date): Response
    {
        Day::where('date', $date)->delete();

        return response()->noContent();
    }

    private function isValidDate(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = explode('-', $date);

        return checkdate((int) $month, (int) $day, (int) $year);
    }
}
```

- [ ] **Step 3: Create DaysTest**

```bash
php artisan make:test DaysTest --pest --no-interaction
```

- [ ] **Step 4: Write DaysTest**

Replace `tests/Feature/DaysTest.php`:

```php
<?php

use App\Models\Day;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api')->plainTextToken;
});

test('unauthenticated requests get 401', function () {
    $this->getJson('/api/days')->assertUnauthorized();
    $this->putJson('/api/days/2026-01-01', [])->assertUnauthorized();
    $this->deleteJson('/api/days/2026-01-01')->assertUnauthorized();
});

test('index returns all days sorted ascending', function () {
    Day::factory()->create(['date' => '2026-01-03']);
    Day::factory()->create(['date' => '2026-01-01']);
    Day::factory()->create(['date' => '2026-01-02']);

    $this->withToken($this->token)->getJson('/api/days')
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonPath('0.date', '2026-01-01')
        ->assertJsonPath('2.date', '2026-01-03');
});

test('upsert creates new day', function () {
    $this->withToken($this->token)
        ->putJson('/api/days/2026-01-15', ['kcal' => 2000, 'protein_g' => 150])
        ->assertOk()
        ->assertJsonPath('kcal', 2000)
        ->assertJsonPath('protein_g', 150)
        ->assertJsonPath('date', '2026-01-15');

    $this->assertDatabaseHas('days', ['date' => '2026-01-15', 'kcal' => 2000]);
});

test('upsert updates existing day without wiping other fields', function () {
    Day::factory()->create(['date' => '2026-01-15', 'kcal' => 2000, 'protein_g' => 150, 'weight_kg' => 80.5]);

    $this->withToken($this->token)
        ->putJson('/api/days/2026-01-15', ['kcal' => 1800])
        ->assertOk()
        ->assertJsonPath('kcal', 1800)
        ->assertJsonPath('protein_g', 150)
        ->assertJsonPath('weight_kg', '80.50');
});

test('show returns 404 for missing day', function () {
    $this->withToken($this->token)->getJson('/api/days/2026-01-15')->assertNotFound();
});

test('delete removes a day and returns 204', function () {
    Day::factory()->create(['date' => '2026-01-15']);

    $this->withToken($this->token)->deleteJson('/api/days/2026-01-15')->assertNoContent();
    $this->assertDatabaseMissing('days', ['date' => '2026-01-15']);
});

test('invalid date format returns 422', function () {
    $this->withToken($this->token)->putJson('/api/days/2025-13-99', [])->assertStatus(422);
    $this->withToken($this->token)->putJson('/api/days/not-a-date', [])->assertStatus(422);
});
```

- [ ] **Step 5: Create Day factory**

```bash
php artisan make:factory DayFactory --model=Day --no-interaction
```

Write `database/factories/DayFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Day>
 */
class DayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->date(),
            'weight_kg' => $this->faker->randomFloat(2, 60, 120),
            'kcal' => $this->faker->numberBetween(1500, 3000),
            'protein_g' => $this->faker->numberBetween(100, 250),
            'carbs_g' => $this->faker->numberBetween(100, 400),
            'fat_g' => $this->faker->numberBetween(40, 100),
            'steps' => $this->faker->numberBetween(2000, 20000),
            'sleep_hours' => $this->faker->randomFloat(1, 4, 10),
            'hunger' => $this->faker->numberBetween(1, 5),
            'energy' => $this->faker->numberBetween(1, 5),
            'refeed' => false,
            'session' => $this->faker->randomElement(['Push', 'Pull', 'Legs', 'Other', null]),
            'rpe' => $this->faker->randomFloat(1, 5, 10),
            'photos_taken' => false,
        ];
    }
}
```

- [ ] **Step 6: Run DaysTest**

```bash
php artisan test --compact --filter=DaysTest
```

Expected: all tests pass.

- [ ] **Step 7: Run pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/DayController.php tests/Feature/DaysTest.php database/factories/DayFactory.php && git commit -m "feat: add DayController with CRUD + tests"
```

---

## Task 7: SettingController + SettingsTest

**Files:**
- Create: `app/Http/Controllers/Api/SettingController.php`
- Create: `tests/Feature/SettingsTest.php`

- [ ] **Step 1: Create SettingController**

```bash
php artisan make:controller Api/SettingController --no-interaction
```

- [ ] **Step 2: Write SettingController**

Replace `app/Http/Controllers/Api/SettingController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const KEYS = ['start_date', 'kcal_target', 'protein_target'];

    public function index(): JsonResponse
    {
        $settings = Setting::whereIn('key', self::KEYS)->pluck('value', 'key');

        return response()->json(array_merge(
            array_fill_keys(self::KEYS, null),
            $settings->toArray()
        ));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'kcal_target' => 'nullable|integer|min:0',
            'protein_target' => 'nullable|integer|min:0',
        ]);

        foreach (array_intersect_key($validated, array_flip(self::KEYS)) as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return $this->index();
    }
}
```

- [ ] **Step 3: Create SettingsTest**

```bash
php artisan make:test SettingsTest --pest --no-interaction
```

- [ ] **Step 4: Write SettingsTest**

Replace `tests/Feature/SettingsTest.php`:

```php
<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api')->plainTextToken;
});

test('unauthenticated settings requests get 401', function () {
    $this->getJson('/api/settings')->assertUnauthorized();
    $this->putJson('/api/settings', [])->assertUnauthorized();
});

test('index returns all keys with nulls for unset values', function () {
    $this->withToken($this->token)->getJson('/api/settings')
        ->assertOk()
        ->assertJson([
            'start_date' => null,
            'kcal_target' => null,
            'protein_target' => null,
        ]);
});

test('update stores and returns merged settings', function () {
    $this->withToken($this->token)
        ->putJson('/api/settings', ['kcal_target' => 2200, 'protein_target' => 180])
        ->assertOk()
        ->assertJsonPath('kcal_target', '2200')
        ->assertJsonPath('protein_target', '180')
        ->assertJsonPath('start_date', null);
});

test('update is a partial merge, not a full replacement', function () {
    $this->withToken($this->token)->putJson('/api/settings', ['kcal_target' => 2200]);
    $this->withToken($this->token)->putJson('/api/settings', ['protein_target' => 180]);

    $this->withToken($this->token)->getJson('/api/settings')
        ->assertOk()
        ->assertJsonPath('kcal_target', '2200')
        ->assertJsonPath('protein_target', '180');
});
```

- [ ] **Step 5: Run SettingsTest**

```bash
php artisan test --compact --filter=SettingsTest
```

Expected: all 4 tests pass.

- [ ] **Step 6: Run pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/SettingController.php tests/Feature/SettingsTest.php && git commit -m "feat: add SettingController + tests"
```

---

## Task 8: ExportController + ExportTest

**Files:**
- Create: `app/Http/Controllers/Api/ExportController.php`
- Create: `tests/Feature/ExportTest.php`

- [ ] **Step 1: Create ExportController**

```bash
php artisan make:controller Api/ExportController --no-interaction
```

- [ ] **Step 2: Write ExportController**

Replace `app/Http/Controllers/Api/ExportController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Day;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ExportController extends Controller
{
    public function export(): JsonResponse
    {
        $settings = Setting::all()->pluck('value', 'key');

        return response()->json([
            'exported_at' => now()->toISOString(),
            'settings' => $settings,
            'days' => Day::orderBy('date')->get(),
        ]);
    }
}
```

- [ ] **Step 3: Create ExportTest**

```bash
php artisan make:test ExportTest --pest --no-interaction
```

- [ ] **Step 4: Write ExportTest**

Replace `tests/Feature/ExportTest.php`:

```php
<?php

use App\Models\Day;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api')->plainTextToken;
});

test('export requires authentication', function () {
    $this->getJson('/api/export')->assertUnauthorized();
});

test('export returns correct shape', function () {
    Day::factory()->create(['date' => '2026-01-01']);
    Setting::create(['key' => 'kcal_target', 'value' => '2200']);

    $this->withToken($this->token)->getJson('/api/export')
        ->assertOk()
        ->assertJsonStructure(['exported_at', 'settings', 'days'])
        ->assertJsonCount(1, 'days')
        ->assertJsonPath('days.0.date', '2026-01-01');
});

test('export days are sorted ascending by date', function () {
    Day::factory()->create(['date' => '2026-01-03']);
    Day::factory()->create(['date' => '2026-01-01']);

    $response = $this->withToken($this->token)->getJson('/api/export')->assertOk();
    expect($response->json('days.0.date'))->toBe('2026-01-01');
    expect($response->json('days.1.date'))->toBe('2026-01-03');
});
```

- [ ] **Step 5: Run ExportTest**

```bash
php artisan test --compact --filter=ExportTest
```

Expected: all 3 tests pass.

- [ ] **Step 6: Run pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/ExportController.php tests/Feature/ExportTest.php && git commit -m "feat: add ExportController + tests"
```

---

## Task 9: SyncController + SyncTest

**Files:**
- Create: `app/Http/Controllers/Api/SyncController.php`
- Create: `tests/Feature/SyncTest.php`

- [ ] **Step 1: Create SyncController**

```bash
php artisan make:controller Api/SyncController --no-interaction
```

- [ ] **Step 2: Write SyncController**

Replace `app/Http/Controllers/Api/SyncController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Day;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'ops' => 'required|array',
            'ops.*.type' => 'required|in:put,delete',
            'ops.*.date' => 'required|date_format:Y-m-d',
            'ops.*.data' => 'nullable|array',
        ]);

        $results = [];

        foreach ($request->input('ops') as $op) {
            try {
                if ($op['type'] === 'put') {
                    $day = Day::updateOrCreate(
                        ['date' => $op['date']],
                        $op['data'] ?? []
                    );
                    $results[] = ['date' => $op['date'], 'success' => true, 'day' => $day];
                } elseif ($op['type'] === 'delete') {
                    Day::where('date', $op['date'])->delete();
                    $results[] = ['date' => $op['date'], 'success' => true];
                }
            } catch (Throwable $e) {
                $results[] = ['date' => $op['date'], 'success' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }
}
```

- [ ] **Step 3: Create SyncTest**

```bash
php artisan make:test SyncTest --pest --no-interaction
```

- [ ] **Step 4: Write SyncTest**

Replace `tests/Feature/SyncTest.php`:

```php
<?php

use App\Models\Day;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api')->plainTextToken;
});

test('sync requires authentication', function () {
    $this->postJson('/api/sync', ['ops' => []])->assertUnauthorized();
});

test('sync applies a batch of put ops', function () {
    $this->withToken($this->token)->postJson('/api/sync', [
        'ops' => [
            ['type' => 'put', 'date' => '2026-01-01', 'data' => ['kcal' => 2000]],
            ['type' => 'put', 'date' => '2026-01-02', 'data' => ['kcal' => 1800]],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'results')
        ->assertJsonPath('results.0.success', true)
        ->assertJsonPath('results.1.success', true);

    $this->assertDatabaseHas('days', ['date' => '2026-01-01', 'kcal' => 2000]);
    $this->assertDatabaseHas('days', ['date' => '2026-01-02', 'kcal' => 1800]);
});

test('sync applies delete ops', function () {
    Day::factory()->create(['date' => '2026-01-01']);

    $this->withToken($this->token)->postJson('/api/sync', [
        'ops' => [
            ['type' => 'delete', 'date' => '2026-01-01'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('results.0.success', true);

    $this->assertDatabaseMissing('days', ['date' => '2026-01-01']);
});

test('sync returns per-op results for mix of success and failure', function () {
    $this->withToken($this->token)->postJson('/api/sync', [
        'ops' => [
            ['type' => 'put', 'date' => '2026-01-01', 'data' => ['kcal' => 2000]],
            ['type' => 'put', 'date' => '2026-01-01', 'data' => ['kcal' => 1800]],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'results');
});
```

- [ ] **Step 5: Run SyncTest**

```bash
php artisan test --compact --filter=SyncTest
```

Expected: all 4 tests pass.

- [ ] **Step 6: Run full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass, under 10 seconds.

- [ ] **Step 7: Run pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/SyncController.php tests/Feature/SyncTest.php && git commit -m "feat: add SyncController + tests"
```

---

## Task 10: Frontend HTML/CSS

**Files:**
- Create: `resources/views/app.blade.php`
- Modify: `resources/css/app.css`
- Modify: `routes/web.php`
- Modify: `vite.config.js`

- [ ] **Step 1: Update routes/web.php to serve the SPA**

Replace `routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*');
```

- [ ] **Step 2: Update vite.config.js for the correct font**

Replace `vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

- [ ] **Step 3: Update resources/css/app.css with dark theme**

Replace `resources/css/app.css`:

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
    --color-bg: #0a0a0a;
    --color-surface: #141414;
    --color-card: #1a1a1a;
    --color-border: #2a2a2a;
    --color-accent: #3b82f6;
    --color-accent-hover: #2563eb;
    --color-text: #f9fafb;
    --color-muted: #a1a1aa;
    --color-input-bg: #111111;
    --color-error: #ef4444;
    --color-success: #10b981;
    --color-warning: #f59e0b;
}

html, body {
    background-color: var(--color-bg);
    color: var(--color-text);
    font-family: var(--font-sans);
    min-height: 100dvh;
    overscroll-behavior: none;
}

* { box-sizing: border-box; }

/* Inputs */
input, select, textarea {
    background-color: var(--color-input-bg);
    color: var(--color-text);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 10px 12px;
    font-family: var(--font-sans);
    font-size: 16px;
    width: 100%;
    transition: border-color 0.15s;
    -webkit-appearance: none;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--color-accent);
}

input[type="date"] { color-scheme: dark; }

textarea { resize: vertical; min-height: 72px; }

/* Buttons */
.btn-primary {
    background-color: var(--color-accent);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 12px 20px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    transition: background-color 0.15s;
}

.btn-primary:active { background-color: var(--color-accent-hover); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-secondary {
    background-color: var(--color-card);
    color: var(--color-text);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: border-color 0.15s;
}

.btn-secondary:active { border-color: var(--color-accent); }

.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    color: var(--color-muted);
    font-size: 20px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: color 0.15s;
}

.btn-icon:hover { color: var(--color-text); }

/* Layout */
#app {
    max-width: 480px;
    margin: 0 auto;
    padding: 0 0 80px;
    min-height: 100dvh;
}

/* Login */
#login-screen {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100dvh;
    padding: 24px;
}

#login-screen h1 {
    font-size: 28px;
    font-weight: 600;
    letter-spacing: -0.5px;
    margin-bottom: 8px;
}

#login-screen p {
    color: var(--color-muted);
    font-size: 14px;
    margin-bottom: 32px;
}

.login-form {
    width: 100%;
    max-width: 360px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.login-error {
    color: var(--color-error);
    font-size: 14px;
    text-align: center;
    min-height: 20px;
}

/* Top nav */
#top-nav {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: var(--color-bg);
    border-bottom: 1px solid var(--color-border);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

#date-picker {
    flex: 1;
    text-align: center;
    background: none;
    border: none;
    color: var(--color-text);
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    padding: 4px;
}

/* Cards */
.card {
    background-color: var(--color-card);
    border-radius: 12px;
    padding: 16px;
    margin: 12px 16px 0;
}

.card-title {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

/* Form grid */
.field-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.field-grid.full { grid-template-columns: 1fr; }

.field-label {
    display: block;
    font-size: 12px;
    color: var(--color-muted);
    margin-bottom: 4px;
}

/* Toggle */
.toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
}

.toggle-row:not(:last-child) {
    border-bottom: 1px solid var(--color-border);
}

.toggle-label {
    font-size: 14px;
    font-weight: 500;
}

.toggle {
    position: relative;
    width: 44px;
    height: 24px;
}

.toggle input { display: none; }

.toggle-slider {
    position: absolute;
    inset: 0;
    background-color: var(--color-border);
    border-radius: 9999px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.toggle-slider::before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    left: 3px;
    top: 3px;
    background-color: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
}

.toggle input:checked + .toggle-slider { background-color: var(--color-accent); }
.toggle input:checked + .toggle-slider::before { transform: translateX(20px); }

/* Session radio */
.session-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.session-btn {
    padding: 8px 4px;
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: var(--color-input-bg);
    color: var(--color-muted);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-align: center;
    transition: all 0.15s;
}

.session-btn.active {
    border-color: var(--color-accent);
    background: color-mix(in srgb, var(--color-accent) 15%, transparent);
    color: var(--color-accent);
}

/* Rating */
.rating-group {
    display: flex;
    gap: 8px;
}

.rating-btn {
    flex: 1;
    padding: 8px 0;
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: var(--color-input-bg);
    color: var(--color-muted);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    transition: all 0.15s;
}

.rating-btn.active {
    border-color: var(--color-accent);
    background: color-mix(in srgb, var(--color-accent) 15%, transparent);
    color: var(--color-accent);
}

/* Status bar */
#status-bar {
    position: fixed;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    max-width: 480px;
    background-color: var(--color-surface);
    border-top: 1px solid var(--color-border);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    z-index: 10;
}

#saving-indicator {
    font-size: 12px;
    color: var(--color-muted);
    min-width: 60px;
}

#offline-badge {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-warning);
    display: none;
}

#offline-badge.visible { display: block; }

.status-actions { display: flex; gap: 8px; }

/* Install banner */
#install-banner {
    margin: 12px 16px 0;
    background: color-mix(in srgb, var(--color-accent) 15%, var(--color-card));
    border: 1px solid color-mix(in srgb, var(--color-accent) 40%, transparent);
    border-radius: 12px;
    padding: 12px 16px;
    display: none;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

#install-banner.visible { display: flex; }

#install-banner span { font-size: 14px; }

#install-banner button {
    background: var(--color-accent);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
```

- [ ] **Step 4: Create resources/views/app.blade.php**

```php
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
```

- [ ] **Step 5: Run pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/ routes/web.php vite.config.js && git commit -m "feat: add app.blade.php SPA shell + dark theme CSS"
```

---

## Task 11: Frontend db.js (IndexedDB)

**Files:**
- Create: `resources/js/db.js`

- [ ] **Step 1: Create db.js**

Create `resources/js/db.js`:

```js
const DB_NAME = 'cut-tracker';
const STORE = 'pending_ops';
const DB_VERSION = 1;

let dbInstance = null;

function openDb() {
    if (dbInstance) return Promise.resolve(dbInstance);

    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);

        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                const store = db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
                store.createIndex('queued_at', 'queued_at');
            }
        };

        req.onsuccess = (e) => {
            dbInstance = e.target.result;
            resolve(dbInstance);
        };

        req.onerror = () => reject(req.error);
    });
}

export async function enqueue(type, date, data = null) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        const store = tx.objectStore(STORE);
        const req = store.add({ type, date, data, queued_at: Date.now(), error: null });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export async function getPending() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const store = tx.objectStore(STORE);
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export async function removeOp(id) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).delete(id);
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
    });
}

export async function count() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const req = tx.objectStore(STORE).count();
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/db.js && git commit -m "feat: add IndexedDB wrapper for offline queue"
```

---

## Task 12: Frontend api.js (fetch wrapper)

**Files:**
- Create: `resources/js/api.js`

- [ ] **Step 1: Create api.js**

Create `resources/js/api.js`:

```js
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
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/api.js && git commit -m "feat: add API fetch wrapper with offline queue integration"
```

---

## Task 13: Frontend ui.js (DOM manipulation)

**Files:**
- Create: `resources/js/ui.js`

- [ ] **Step 1: Create ui.js**

Create `resources/js/ui.js`:

```js
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
    const numericFields = ['kcal', 'protein_g', 'carbs_g', 'fat_g', 'steps', 'hunger', 'energy'];
    const decimalFields = ['weight_kg', 'sleep_hours', 'waist_cm', 'rpe'];
    const textFields = ['lifts', 'notes'];
    const booleanFields = ['refeed', 'photos_taken'];

    const data = {};

    for (const f of numericFields) {
        const val = document.getElementById(f)?.value;
        data[f] = val !== '' && val != null ? parseInt(val, 10) : null;
    }

    for (const f of decimalFields) {
        const el = document.getElementById(f);
        if (f === 'rpe' || f === 'hunger' || f === 'energy') {
            data[f] = getActiveRating(f.replace('_', '') + '-group') ?? null;
        } else {
            const val = el?.value;
            data[f] = val !== '' && val != null ? parseFloat(val) : null;
        }
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
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/ui.js && git commit -m "feat: add UI DOM manipulation helpers"
```

---

## Task 14: Frontend app.js (SPA bootstrap) + web routes

**Files:**
- Modify: `resources/js/app.js`

- [ ] **Step 1: Write app.js**

Replace `resources/js/app.js`:

```js
import * as api from './api.js';
import * as db from './db.js';
import * as ui from './ui.js';

let currentDate = todayDate();
let saveTimeout = null;

function todayDate() {
    return new Date().toISOString().slice(0, 10);
}

function formatDate(isoDate) {
    const [y, m, d] = isoDate.split('-');
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
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

    let deferredInstallPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredInstallPrompt = e;
        ui.setupInstallButton(e, () => { deferredInstallPrompt = null; });
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
```

- [ ] **Step 2: Build assets**

```bash
npm run build
```

Expected: builds without errors, produces `public/build/` files.

- [ ] **Step 3: Commit**

```bash
git add resources/js/app.js && git commit -m "feat: add SPA bootstrap - app.js"
```

---

## Task 15: PWA Manifest + Icons

**Files:**
- Create: `public/manifest.webmanifest`
- Create: `public/icons/icon-192.png`
- Create: `public/icons/icon-512.png`

- [ ] **Step 1: Create icons directory**

```bash
mkdir -p /home/timotej/Documents/projects/cut-tracker/public/icons
```

- [ ] **Step 2: Generate icons with PHP**

```bash
php artisan tinker --execute '
$sizes = [192, 512];
foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($img, 10, 10, 10);
    $accent = imagecolorallocate($img, 59, 130, 246);
    imagefill($img, 0, 0, $bg);
    $margin = (int)($size * 0.15);
    imagefilledellipse($img, (int)($size/2), (int)($size/2), $size - $margin*2, $size - $margin*2, $accent);
    $white = imagecolorallocate($img, 255, 255, 255);
    $fontSize = (int)($size * 0.35);
    if (function_exists("imagettftext") && file_exists("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf")) {
        $font = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf";
        $bbox = imagettfbbox($fontSize, 0, $font, "C");
        $tx = (int)($size/2) - (int)(($bbox[2] - $bbox[0])/2);
        $ty = (int)($size/2) - (int)(($bbox[5] - $bbox[3])/2);
        imagettftext($img, $fontSize, 0, $tx, $ty, $white, $font, "C");
    } else {
        imagestring($img, 5, (int)($size/2 - 5), (int)($size/2 - 7), "C", $white);
    }
    imagepng($img, "public/icons/icon-{$size}.png");
    imagedestroy($img);
    echo "Created icon-{$size}.png\n";
}
'
```

Expected: creates `public/icons/icon-192.png` and `public/icons/icon-512.png`.

- [ ] **Step 3: Create manifest.webmanifest**

Create `public/manifest.webmanifest`:

```json
{
    "name": "Cut Tracker",
    "short_name": "Cut",
    "start_url": "/",
    "display": "standalone",
    "background_color": "#0a0a0a",
    "theme_color": "#0a0a0a",
    "orientation": "portrait-primary",
    "icons": [
        {
            "src": "/icons/icon-192.png",
            "sizes": "192x192",
            "type": "image/png",
            "purpose": "any maskable"
        },
        {
            "src": "/icons/icon-512.png",
            "sizes": "512x512",
            "type": "image/png",
            "purpose": "any maskable"
        }
    ]
}
```

- [ ] **Step 4: Commit**

```bash
git add public/manifest.webmanifest public/icons/ && git commit -m "feat: add PWA manifest and icons"
```

---

## Task 16: Service Worker (public/sw.js)

**Files:**
- Create: `public/sw.js`

- [ ] **Step 1: Create public/sw.js using Workbox**

Create `public/sw.js`:

```js
importScripts('https://storage.googleapis.com/workbox-cdn/releases/7.0.0/workbox-sw.js');

const { registerRoute } = workbox.routing;
const { StaleWhileRevalidate, NetworkFirst } = workbox.strategies;
const { CacheFirst } = workbox.strategies;
const { ExpirationPlugin } = workbox.expiration;

workbox.core.setCacheNameDetails({ prefix: 'cut-tracker' });

// App shell: serve from cache, update in background
registerRoute(
    ({ request, url }) =>
        url.pathname === '/' ||
        request.destination === 'script' ||
        request.destination === 'style' ||
        url.pathname.endsWith('.webmanifest') ||
        url.pathname.startsWith('/icons/'),
    new StaleWhileRevalidate({
        cacheName: 'cut-tracker-shell',
        plugins: [
            new ExpirationPlugin({ maxEntries: 30, maxAgeSeconds: 60 * 60 * 24 * 7 }),
        ],
    })
);

// API GET: network first, fall back to cache
registerRoute(
    ({ url }) => url.pathname.startsWith('/api/days') || url.pathname.startsWith('/api/settings'),
    new NetworkFirst({
        cacheName: 'cut-tracker-api',
        networkTimeoutSeconds: 3,
        plugins: [
            new ExpirationPlugin({ maxEntries: 100, maxAgeSeconds: 60 * 60 * 24 }),
        ],
    }),
    'GET'
);
```

- [ ] **Step 2: Commit**

```bash
git add public/sw.js && git commit -m "feat: add Workbox service worker"
```

---

## Task 17: Dockerfile + docker/ configs

**Files:**
- Create: `Dockerfile`
- Create: `docker/nginx.conf`
- Create: `docker/supervisord.conf`
- Create: `docker/entrypoint.sh`

- [ ] **Step 1: Create docker directory**

```bash
mkdir -p /home/timotej/Documents/projects/cut-tracker/docker
```

- [ ] **Step 2: Create docker/nginx.conf**

```nginx
user nginx;
worker_processes auto;
error_log /dev/stderr warn;
pid /tmp/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    access_log /dev/stdout;
    sendfile on;

    server {
        listen 8080;
        root /var/www/html/public;
        index index.php;
        client_max_body_size 10M;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 300;
        }

        location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2|webmanifest)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
            access_log off;
        }
    }
}
```

- [ ] **Step 3: Create docker/supervisord.conf**

```ini
[supervisord]
nodaemon=true
user=root
logfile=/dev/stdout
logfile_maxbytes=0
pidfile=/tmp/supervisord.pid

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:php-fpm]
command=php-fpm8.3 -F
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

- [ ] **Step 4: Create docker/entrypoint.sh**

```bash
#!/bin/sh
set -e

DB_DIR="${DB_DATABASE%/*}"
if [ ! -d "$DB_DIR" ]; then
    mkdir -p "$DB_DIR"
fi

if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
    sqlite3 "$DB_DATABASE" "PRAGMA journal_mode=WAL;"
    echo "Created SQLite database at $DB_DATABASE"
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache "$DB_DATABASE"

cd /var/www/html

php artisan config:cache
php artisan migrate --force
php artisan db:seed --force

exec supervisord -c /etc/supervisord.conf
```

- [ ] **Step 5: Create Dockerfile**

```dockerfile
# Stage 1: PHP dependencies
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Stage 2: Node/Vite build
FROM node:20-alpine AS node
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources/ resources/
COPY vite.config.js ./
COPY public/ public/
RUN npm run build

# Stage 3: Runtime
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor sqlite sqlite-dev \
    && docker-php-ext-install pdo_sqlite bcmath opcache \
    && apk add --no-cache php83-pdo_sqlite \
    && rm -rf /var/cache/apk/*

RUN adduser -D -u 1000 appuser \
    && addgroup -g 1001 nginx \
    && adduser -D -G nginx nginx 2>/dev/null || true

WORKDIR /var/www/html

COPY --from=composer /app/vendor vendor/
COPY --from=node /app/public/build public/build/
COPY . .

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN mkdir -p /var/www/html/database/sqlite \
    && chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod 775 /var/www/html/storage /var/www/html/bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/sqlite/cut.sqlite

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s CMD wget -qO- http://localhost:8080/api/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
```

- [ ] **Step 6: Add .dockerignore**

Create `.dockerignore`:

```
node_modules/
vendor/
.env
*.sqlite
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
```

- [ ] **Step 7: Commit**

```bash
git add Dockerfile docker/ .dockerignore && git commit -m "feat: add multi-stage Dockerfile with nginx+php-fpm+supervisord"
```

---

## Task 18: Kubernetes manifests

**Files:**
- Create: `k8s/deployment.yaml`
- Create: `k8s/service.yaml`
- Create: `k8s/pvc.yaml`
- Create: `k8s/secret.yaml.example`
- Create: `k8s/ingress.yaml`
- Create: `k8s/kustomization.yaml`

- [ ] **Step 1: Create k8s directory**

```bash
mkdir -p /home/timotej/Documents/projects/cut-tracker/k8s
```

- [ ] **Step 2: Create k8s/pvc.yaml**

```yaml
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: cut-tracker-sqlite
  namespace: default
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 1Gi
```

- [ ] **Step 3: Create k8s/secret.yaml.example**

```yaml
# Copy to secret.yaml and fill in real values, then:
#   kubectl apply -f k8s/secret.yaml
apiVersion: v1
kind: Secret
metadata:
  name: cut-tracker-secrets
  namespace: default
type: Opaque
stringData:
  APP_KEY: "base64:REPLACE_WITH_php_artisan_key_generate_output"
  APP_PASSWORD_HASH: "REPLACE_WITH_php_artisan_app_hash-password_output"
  APP_URL: "https://cut.example.com"
  SANCTUM_STATEFUL_DOMAINS: "cut.example.com"
  SESSION_DOMAIN: "cut.example.com"
```

- [ ] **Step 4: Create k8s/deployment.yaml**

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: cut-tracker
  namespace: default
spec:
  replicas: 1
  selector:
    matchLabels:
      app: cut-tracker
  template:
    metadata:
      labels:
        app: cut-tracker
    spec:
      containers:
        - name: app
          image: cut-tracker:latest
          ports:
            - containerPort: 8080
          resources:
            requests:
              cpu: 100m
              memory: 128Mi
            limits:
              cpu: 500m
              memory: 512Mi
          envFrom:
            - secretRef:
                name: cut-tracker-secrets
          env:
            - name: DB_DATABASE
              value: /var/www/html/database/sqlite/cut.sqlite
            - name: APP_ENV
              value: production
            - name: LOG_CHANNEL
              value: stderr
          volumeMounts:
            - name: sqlite-data
              mountPath: /var/www/html/database/sqlite
          livenessProbe:
            httpGet:
              path: /api/health
              port: 8080
            initialDelaySeconds: 15
            periodSeconds: 30
          readinessProbe:
            httpGet:
              path: /api/health
              port: 8080
            initialDelaySeconds: 5
            periodSeconds: 10
      volumes:
        - name: sqlite-data
          persistentVolumeClaim:
            claimName: cut-tracker-sqlite
```

- [ ] **Step 5: Create k8s/service.yaml**

```yaml
apiVersion: v1
kind: Service
metadata:
  name: cut-tracker
  namespace: default
spec:
  selector:
    app: cut-tracker
  ports:
    - port: 80
      targetPort: 8080
  type: ClusterIP
```

- [ ] **Step 6: Create k8s/ingress.yaml**

```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: cut-tracker
  namespace: default
  annotations:
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
    nginx.ingress.kubernetes.io/proxy-body-size: "10m"
spec:
  ingressClassName: nginx
  tls:
    - hosts:
        - cut.example.com
      secretName: cut-tracker-tls
  rules:
    - host: cut.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: cut-tracker
                port:
                  number: 80
```

- [ ] **Step 7: Create k8s/kustomization.yaml**

```yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization

namespace: default

resources:
  - pvc.yaml
  - deployment.yaml
  - service.yaml
  - ingress.yaml
```

- [ ] **Step 8: Commit**

```bash
git add k8s/ && git commit -m "feat: add Kubernetes manifests"
```

---

## Task 19: README

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Replace README.md**

```markdown
# Cut Tracker

Personal fitness cut tracker PWA. Log daily nutrition, training, sleep, and bodyweight in under 60 seconds. Works offline, installs as a native-like app on phone and desktop.

## Quick Start (local dev)

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Visit http://localhost:8000. Default credentials: no password set until you configure `APP_PASSWORD_HASH`.

## Setting the Password

Generate a bcrypt hash:

```bash
php artisan app:hash-password
```

Copy the output hash into your `.env`:

```
APP_PASSWORD_HASH=$2y$12$...
```

## Deployment (Docker)

Build the image:

```bash
docker build -t cut-tracker:latest .
```

Run locally with a persistent volume:

```bash
docker run -p 8080:8080 \
  -e APP_KEY="$(php artisan key:generate --show)" \
  -e APP_PASSWORD_HASH="$HASH" \
  -e APP_URL="http://localhost:8080" \
  -v cut-data:/var/www/html/database/sqlite \
  cut-tracker:latest
```

## Kubernetes Deployment

1. Copy `k8s/secret.yaml.example` to `k8s/secret.yaml` and fill in values.
2. Apply:

```bash
kubectl apply -f k8s/secret.yaml
kubectl apply -k k8s/
```

3. Update `k8s/ingress.yaml` host to your domain.

## Backup

Copy the SQLite file from the running pod:

```bash
kubectl cp <pod-name>:/var/www/html/database/sqlite/cut.sqlite ./cut-backup-$(date +%Y%m%d).sqlite
```

## Running Tests

```bash
php artisan test --compact
```

## Export

Click **Export** in the app status bar. Downloads a JSON file with all days and settings. Use an LLM to analyze trends.
```

- [ ] **Step 2: Commit**

```bash
git add README.md && git commit -m "docs: add README with setup, password, deployment, backup instructions"
```

---

## Self-Review

### Spec Coverage Check

| Spec requirement | Covered by task |
|---|---|
| `POST /api/login` + password check | Task 5 AuthController |
| `POST /api/logout` | Task 5 |
| `GET /api/me` | Task 5 |
| `GET/PUT/DELETE /api/days/{date}` | Task 6 |
| `GET /api/days` | Task 6 |
| `GET/PUT /api/settings` | Task 7 |
| `GET /api/export` | Task 8 |
| `POST /api/sync` | Task 9 |
| `GET /api/health` | Task 5 |
| Upsert semantics | Task 6 - `updateOrCreate` |
| Partial updates don't wipe fields | Task 6 - `request->only()` |
| Rate limit login 5/min | Task 5 - `RateLimiter` |
| Token 30-day expiry | Task 5 - `createToken(..., now()->addDays(30))` |
| `app:hash-password` Artisan command | Task 4 |
| `APP_PASSWORD_HASH` env var | Task 4 |
| Single seeded user | Task 4 |
| PWA manifest | Task 15 |
| Service worker with Workbox | Task 16 |
| IndexedDB offline queue | Task 11 |
| Offline badge "N unsaved changes" | Task 12 + 14 |
| Online event → sync | Task 14 |
| Install prompt button | Task 14 |
| Date validation (reject invalid dates) | Task 6 - `isValidDate()` |
| Sync endpoint batch + per-op results | Task 9 |
| `days` migration with all columns | Task 2 |
| `settings` key-value migration | Task 2 |
| `GET /api/export` shape | Task 8 |
| Dockerfile multi-stage | Task 17 |
| nginx + php-fpm + supervisord | Task 17 |
| K8s manifests | Task 18 |
| WAL mode on SQLite | Task 17 (entrypoint.sh) |
| Tests: auth, days, settings, sync, export | Tasks 5-9 |
| All tests pass, under 10s | Task 9 step 6 |
| Log to stderr | Task 18 (LOG_CHANNEL=stderr) |
| README with backup instructions | Task 19 |

All spec requirements are covered.
