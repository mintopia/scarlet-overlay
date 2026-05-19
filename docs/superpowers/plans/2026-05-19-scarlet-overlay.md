# Scarlet Overlay Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build four surfaces for the yacht Scarlet's real-time sailing telemetry system: stream overlay, admin dashboard (5 pages), public dashboard, and login page.

**Architecture:** Laravel 12 serves three entry points: a Blade overlay (vanilla JS), an Inertia.js SPA (Vue 3) for admin + public dashboard, and a login page. All surfaces receive real-time data from Prometheus via Laravel Reverb WebSocket. Auth uses email/password + WebAuthn passkeys with a multi-user invite system.

**Tech Stack:** Laravel 12, FrankenPHP, Vue 3, Inertia.js, Tailwind CSS 4, Leaflet, Laravel Reverb, Prometheus, WebAuthn (laragear/webauthn), SQLite.

**Design reference:** Approved mockups in `.superpowers/brainstorm/37477-1779186435/content/` (files 05-08 for overlay states, 11 for public dashboard, 12 for login, 13 for admin). Design spec at `docs/superpowers/specs/2026-05-19-scarlet-overlay-design.md`.

---

## File Structure

### New Files

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── BoatMetricsController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── SettingsController.php
│   │   │   ├── TeamController.php
│   │   │   └── TrackerController.php
│   │   ├── Auth/
│   │   │   ├── LoginController.php
│   │   │   ├── PasskeyController.php
│   │   │   └── RegisterController.php
│   │   ├── DashboardController.php
│   │   └── OverlayController.php
│   └── Middleware/
│       └── HandleInertiaRequests.php
├── Mail/
│   └── TeamInviteMail.php
├── Models/
│   ├── BoatSetting.php
│   ├── Invite.php
│   └── User.php
└── Services/
    └── PrometheusService.php

database/migrations/
├── 0001_01_01_000000_create_users_table.php
├── 0001_01_01_000001_create_cache_table.php
├── 2026_05_19_000001_create_boat_settings_table.php
└── 2026_05_19_000002_create_invites_table.php

database/seeders/
└── DatabaseSeeder.php (modify)

resources/
├── css/
│   ├── app.css (rewrite — Tailwind tokens for admin/public)
│   └── overlay.css (create — scoped overlay styles)
├── js/
│   ├── app.js (rewrite — Inertia/Vue entry)
│   ├── overlay.js (rewrite — state machine + WebSocket)
│   ├── Layouts/
│   │   └── AdminLayout.vue
│   ├── Pages/
│   │   ├── Admin/
│   │   │   ├── Settings.vue
│   │   │   ├── Tracker.vue
│   │   │   ├── BoatMetrics.vue
│   │   │   ├── Profile.vue
│   │   │   └── Team.vue
│   │   ├── Auth/
│   │   │   ├── Login.vue
│   │   │   └── Register.vue
│   │   └── Public/
│   │       └── Dashboard.vue
│   └── composables/
│       └── useEcho.js
└── views/
    ├── app.blade.php (create — Inertia root)
    ├── overlay.blade.php (create — new overlay)
    └── mail/
        └── team-invite.blade.php
```

### Modified Files

```
bootstrap/app.php              — add Inertia middleware
composer.json                  — add inertiajs/inertia-laravel, laragear/webauthn
config/scarlet.php             — expand Prometheus queries, keep as defaults
package.json                   — add vue, @vitejs/plugin-vue, @inertiajs/vue3, leaflet
resources/js/bootstrap.js      — keep Echo setup, remove overlay import
routes/web.php                 — admin routes, public dashboard, overlay
routes/channels.php            — channel auth
routes/api.php                 — keep existing, add settings endpoints
vite.config.js                 — add Vue plugin, overlay entry point
.env.example                   — add new variables
app/Console/Commands/MetricsPushCommand.php — expanded metrics
app/Events/MetricsUpdated.php  — expanded payload
app/Services/MetricsService.php — rewrite for structured queries
app/Services/GpsService.php    — rewrite to use Prometheus
```

---

## Phase 1: Foundation

### Task 1: Install and Configure Inertia.js + Vue 3

**Files:**
- Modify: `composer.json`
- Modify: `package.json`
- Modify: `vite.config.js`
- Modify: `bootstrap/app.php`
- Create: `app/Http/Middleware/HandleInertiaRequests.php`
- Create: `resources/views/app.blade.php`
- Rewrite: `resources/js/app.js`
- Create: `resources/js/Pages/Admin/Settings.vue` (placeholder)

- [ ] **Step 1: Install server-side Inertia**

Run:
```bash
composer require inertiajs/inertia-laravel
```

- [ ] **Step 2: Install client-side packages**

Run:
```bash
npm install vue @vitejs/plugin-vue @inertiajs/vue3
```

- [ ] **Step 3: Create the Inertia root Blade template**

Create `resources/views/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
```

- [ ] **Step 4: Create HandleInertiaRequests middleware**

Create `app/Http/Middleware/HandleInertiaRequests.php`:

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'initials' => $request->user()->initials,
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'boatName' => config('scarlet.name'),
        ];
    }
}
```

- [ ] **Step 5: Register middleware in bootstrap/app.php**

Replace the `withMiddleware` callback in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
    $middleware->web(append: \App\Http\Middleware\HandleInertiaRequests::class);
})
```

- [ ] **Step 6: Update vite.config.js**

Replace `vite.config.js`:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/overlay.css',
                'resources/js/overlay.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
```

- [ ] **Step 7: Rewrite app.js as Inertia entry**

Replace `resources/js/app.js`:

```javascript
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import './bootstrap';

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
```

- [ ] **Step 8: Create a placeholder page to verify setup**

Create `resources/js/Pages/Admin/Settings.vue`:

```vue
<template>
    <div style="padding: 40px; font-family: Outfit, sans-serif;">
        <h1>Settings</h1>
        <p>Inertia is working.</p>
    </div>
</template>
```

- [ ] **Step 9: Create an empty overlay.css**

Create `resources/css/overlay.css`:

```css
/* Overlay styles — scoped, no Tailwind */
```

- [ ] **Step 10: Add a test route and verify**

Add to `routes/web.php`:

```php
use Inertia\Inertia;

Route::get('/admin/settings', fn () => Inertia::render('Admin/Settings'))->name('admin.settings');
```

Run:
```bash
npm run build
php artisan serve &
curl -s http://localhost:8000/admin/settings | grep -q 'app.js' && echo "PASS: Inertia rendering" || echo "FAIL"
kill %1
```

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: install and configure Inertia.js + Vue 3

Set up server-side Inertia middleware, Vue 3 Vite plugin,
Inertia root template, and verify rendering with placeholder page."
```

---

### Task 2: Database Migrations and User Model

**Files:**
- Create: `database/migrations/0001_01_01_000000_create_users_table.php`
- Create: `database/migrations/0001_01_01_000001_create_cache_table.php`
- Create: `app/Models/User.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Write test for User model**

Create `tests/Feature/UserTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_owner(): void
    {
        $user = User::factory()->create([
            'name' => 'Jessica Smith',
            'email' => 'jess@mintopia.net',
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jess@mintopia.net',
            'role' => 'owner',
        ]);
        $this->assertEquals('JS', $user->initials);
    }

    public function test_can_create_crew(): void
    {
        $user = User::factory()->create(['role' => 'crew']);
        $this->assertEquals('crew', $user->role);
    }

    public function test_role_defaults_to_crew(): void
    {
        $user = User::factory()->create();
        $this->assertEquals('crew', $user->role);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UserTest`

Expected: FAIL — table does not exist.

- [ ] **Step 3: Create users migration**

Create `database/migrations/0001_01_01_000000_create_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['owner', 'crew'])->default('crew');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
```

- [ ] **Step 4: Create cache migration**

Create `database/migrations/0001_01_01_000001_create_cache_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
```

- [ ] **Step 5: Create User model**

Create `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', $this->name))
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->join('');
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
```

- [ ] **Step 6: Create User factory**

Create `database/factories/UserFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'crew',
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => 'owner']);
    }
}
```

- [ ] **Step 7: Update DatabaseSeeder**

Replace `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() === 0) {
            User::factory()->owner()->create([
                'name' => 'Jessica Smith',
                'email' => 'jess@mintopia.net',
            ]);
        }
    }
}
```

- [ ] **Step 8: Run migrations and test**

Run:
```bash
php artisan migrate:fresh --seed
php artisan test --filter=UserTest
```

Expected: 3 tests PASS.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add database migrations and User model

Create users/sessions/cache tables, User model with role (owner/crew),
initials accessor, factory, and initial owner seeder."
```

---

### Task 3: Authentication (Login and Logout)

**Files:**
- Create: `app/Http/Controllers/Auth/LoginController.php`
- Create: `resources/js/Pages/Auth/Login.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Write auth feature tests**

Create `tests/Feature/AuthTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'jess@mintopia.net',
        ]);

        $response = $this->post('/login', [
            'email' => 'jess@mintopia.net',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/settings');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_rejected(): void
    {
        User::factory()->create(['email' => 'jess@mintopia.net']);

        $response = $this->post('/login', [
            'email' => 'jess@mintopia.net',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_requires_auth(): void
    {
        $response = $this->get('/admin/settings');
        $response->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AuthTest`

Expected: FAIL — routes not defined.

- [ ] **Step 3: Create LoginController**

Create `app/Http/Controllers/Auth/LoginController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/admin/settings');
        }

        return back()->withErrors([
            'email' => 'These credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
```

- [ ] **Step 4: Update routes**

Replace `routes/web.php`:

```php
<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapTileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/overlay', [HomeController::class, 'index'])->name('overlay');
Route::get('/snow', [HomeController::class, 'snow'])->name('snow');
Route::get('/openseamap/{z}/{x}/{y}', [MapTileController::class, 'seamap'])->name('openseamap');

Route::get('/login', [LoginController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'store'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/settings', fn () => Inertia::render('Admin/Settings'))->name('admin.settings');
    Route::get('/tracker', fn () => Inertia::render('Admin/Tracker'))->name('admin.tracker');
    Route::get('/metrics', fn () => Inertia::render('Admin/BoatMetrics'))->name('admin.metrics');
    Route::get('/profile', fn () => Inertia::render('Admin/Profile'))->name('admin.profile');
    Route::get('/team', fn () => Inertia::render('Admin/Team'))->name('admin.team');
});
```

Note: The `/` (home) route now points to `/overlay`. The old home route is preserved temporarily.

- [ ] **Step 5: Update session driver to database**

Add to `.env.example` and `.env`:
```
SESSION_DRIVER=database
```

- [ ] **Step 6: Create Login.vue**

Create `resources/js/Pages/Auth/Login.vue`. Translate the design from mockup `12-admin-login.html`:

```vue
<template>
    <div class="login-page">
        <div class="login-card">
            <div class="brand">
                <div class="brand-name">Scarlet</div>
                <div class="brand-sub">Boat Administration</div>
            </div>

            <form @submit.prevent="submit">
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input
                        class="form-input"
                        :class="{ 'form-input--error': form.errors.email }"
                        type="email"
                        id="email"
                        v-model="form.email"
                        placeholder="you@example.com"
                        autofocus
                    >
                    <div v-if="form.errors.email" class="form-error">{{ form.errors.email }}</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input
                        class="form-input"
                        type="password"
                        id="password"
                        v-model="form.password"
                        placeholder="Enter password"
                    >
                </div>

                <div class="form-row remember-row">
                    <label class="remember">
                        <input type="checkbox" v-model="form.remember"> Remember me
                    </label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                    Sign in
                </button>
            </form>

            <div class="divider"><span>or</span></div>

            <button class="btn btn-passkey" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 18v3c0 .6.4 1 1 1h4v-3h3v-3h2l1.4-1.4a6.5 6.5 0 1 0-4-4Z"/>
                    <circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>
                </svg>
                Sign in with passkey
            </button>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<style scoped>
/* Translate styles from mockup 12-admin-login.html.
   Use the exact oklch colors and Outfit typography from the mockup.
   Key tokens:
   --scarlet: oklch(0.54 0.22 27);
   --bg: oklch(0.97 0.003 70);
   --surface: oklch(1 0 0);
   --border: oklch(0.88 0.005 70);
   --text: oklch(0.20 0.005 40);
   --text-secondary: oklch(0.45 0.005 40);
   --radius: 10px;
   Full CSS is in the mockup file — copy directly. */

.login-page {
    font-family: 'Outfit', system-ui, sans-serif;
    background: oklch(0.97 0.003 70);
    color: oklch(0.20 0.005 40);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.login-card {
    width: 100%;
    max-width: 380px;
    background: oklch(1 0 0);
    border-radius: 14px;
    padding: 40px 36px 36px;
    box-shadow: 0 1px 3px oklch(0 0 0 / 0.04), 0 8px 32px oklch(0 0 0 / 0.06);
}

.brand { text-align: center; margin-bottom: 32px; }
.brand-name { font-size: 28px; font-weight: 700; color: oklch(0.54 0.22 27); letter-spacing: 0.02em; }
.brand-sub { font-size: 13px; font-weight: 500; color: oklch(0.45 0.005 40); margin-top: 4px; }

.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }

.form-input {
    width: 100%; height: 44px; padding: 0 14px;
    font-family: 'Outfit', system-ui, sans-serif; font-size: 14px;
    color: oklch(0.20 0.005 40); background: oklch(0.97 0.003 70);
    border: 1.5px solid oklch(0.88 0.005 70); border-radius: 7px;
    outline: none; transition: border-color 0.15s, box-shadow 0.15s;
}
.form-input:focus {
    border-color: oklch(0.54 0.22 27);
    box-shadow: 0 0 0 3px oklch(0.54 0.22 27 / 0.08);
}
.form-input--error { border-color: oklch(0.55 0.20 27); }
.form-error { font-size: 12px; color: oklch(0.50 0.20 27); margin-top: 4px; }
.form-input::placeholder { color: oklch(0.65 0.005 40); }

.form-row { display: flex; align-items: center; margin-bottom: 24px; }
.remember { display: flex; align-items: center; gap: 8px; font-size: 13px; color: oklch(0.45 0.005 40); cursor: pointer; }
.remember input { width: 16px; height: 16px; accent-color: oklch(0.54 0.22 27); cursor: pointer; }

.btn {
    width: 100%; height: 46px; display: flex; align-items: center; justify-content: center; gap: 8px;
    font-family: 'Outfit', system-ui, sans-serif; font-size: 14px; font-weight: 600;
    border: none; border-radius: 7px; cursor: pointer; transition: background 0.15s;
}
.btn-primary {
    background: oklch(0.54 0.22 27); color: oklch(0.98 0 0);
    box-shadow: 0 1px 3px oklch(0.54 0.22 27 / 0.3);
}
.btn-primary:hover { background: oklch(0.48 0.22 27); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.divider { display: flex; align-items: center; gap: 12px; margin: 20px 0; }
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: oklch(0.88 0.005 70); }
.divider span { font-size: 12px; font-weight: 500; color: oklch(0.65 0.005 40); }

.btn-passkey {
    background: oklch(0.97 0.003 70); color: oklch(0.20 0.005 40);
    border: 1.5px solid oklch(0.88 0.005 70);
}
.btn-passkey:hover { background: oklch(0.95 0.003 70); border-color: oklch(0.82 0.005 70); }
.btn-passkey svg { width: 18px; height: 18px; flex-shrink: 0; }
</style>
```

- [ ] **Step 7: Create placeholder Vue pages for remaining admin routes**

Create each file with a minimal template. Example for `resources/js/Pages/Admin/Tracker.vue`:

```vue
<template>
    <div style="padding: 40px; font-family: Outfit, sans-serif;">
        <h1>Tracker</h1>
    </div>
</template>
```

Repeat for `BoatMetrics.vue`, `Profile.vue`, `Team.vue`.

- [ ] **Step 8: Build and run tests**

Run:
```bash
npm run build
php artisan test --filter=AuthTest
```

Expected: 5 tests PASS.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add authentication with login/logout

LoginController with Inertia, Login.vue matching approved mockup,
auth middleware on admin routes, session-based auth."
```

---

### Task 4: Admin Layout with Sidebar Navigation

**Files:**
- Create: `resources/js/Layouts/AdminLayout.vue`
- Modify: all admin page components to use layout
- Rewrite: `resources/css/app.css` (Tailwind tokens)

- [ ] **Step 1: Set up Tailwind design tokens in app.css**

Rewrite `resources/css/app.css`:

```css
@import 'tailwindcss';

@source '../**/*.vue';
@source '../**/*.blade.php';

@theme {
    --font-sans: 'Outfit', ui-sans-serif, system-ui, sans-serif;

    --color-scarlet: oklch(0.54 0.22 27);
    --color-scarlet-hover: oklch(0.48 0.22 27);
    --color-scarlet-light: oklch(0.54 0.22 27 / 0.06);
    --color-surface: oklch(1 0 0);
    --color-bg: oklch(0.97 0.003 70);
    --color-border: oklch(0.90 0.005 70);
    --color-border-light: oklch(0.93 0.003 70);
    --color-text-primary: oklch(0.18 0.005 40);
    --color-text-secondary: oklch(0.45 0.005 40);
    --color-text-dim: oklch(0.60 0.005 40);
    --color-green: oklch(0.62 0.15 155);
    --color-green-bg: oklch(0.62 0.15 155 / 0.08);
    --color-amber: oklch(0.70 0.14 70);
    --color-amber-bg: oklch(0.70 0.14 70 / 0.08);
}
```

- [ ] **Step 2: Create AdminLayout.vue**

Create `resources/js/Layouts/AdminLayout.vue`. This is the sidebar + main content shell from mockup `13-admin-dashboard.html`. The sidebar contains navigation grouped into Boat, Links, and Account sections:

```vue
<template>
    <div class="flex h-screen overflow-hidden bg-bg font-sans text-text-primary">
        <!-- Sidebar -->
        <aside class="w-[220px] bg-surface border-r border-border flex flex-col shrink-0">
            <div class="px-5 pt-5 pb-4 border-b border-border-light">
                <div class="text-[22px] font-bold text-scarlet tracking-wide">Scarlet</div>
                <div class="text-[11px] font-medium text-text-dim mt-0.5">Admin</div>
            </div>

            <nav class="px-2.5 py-3 flex-1 flex flex-col gap-0.5">
                <div class="nav-label first:pt-1">Boat</div>
                <NavLink href="/admin/settings" icon="settings" :active="currentPage === 'Admin/Settings'">Settings</NavLink>
                <NavLink href="/admin/tracker" icon="activity" :active="currentPage === 'Admin/Tracker'">Tracker</NavLink>
                <NavLink href="/admin/metrics" icon="chart" :active="currentPage === 'Admin/BoatMetrics'">Boat Metrics</NavLink>

                <div class="nav-label">Links</div>
                <a href="/overlay" target="_blank" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    Broadcast Overlay
                </a>
                <a href="/dashboard" target="_blank" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                    Public Dashboard
                </a>

                <div class="nav-label">Account</div>
                <NavLink href="/admin/profile" icon="user" :active="currentPage === 'Admin/Profile'">Profile</NavLink>
                <NavLink href="/admin/team" icon="users" :active="currentPage === 'Admin/Team'">Team</NavLink>
            </nav>

            <div class="px-2.5 py-3.5 border-t border-border-light">
                <div class="flex items-center gap-2.5 px-3 mb-2">
                    <div class="w-7 h-7 rounded-full bg-scarlet-light text-scarlet flex items-center justify-center text-xs font-bold shrink-0">
                        {{ $page.props.auth.user?.initials }}
                    </div>
                    <div>
                        <div class="text-[13px] font-semibold">{{ $page.props.auth.user?.name }}</div>
                        <div class="text-[11px] text-text-dim capitalize">{{ $page.props.auth.user?.role }}</div>
                    </div>
                </div>
                <Link href="/logout" method="post" as="button" class="nav-link text-[13px] text-text-dim w-full">
                    <svg class="nav-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Sign out
                </Link>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 overflow-y-auto px-10 py-8">
            <div class="max-w-[820px]">
                <slot />
            </div>
        </main>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import NavLink from './NavLink.vue';

const currentPage = usePage().component;
</script>

<style scoped>
.nav-label {
    font-size: 10px;
    font-weight: 700;
    color: oklch(0.60 0.005 40);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 16px 12px 6px;
}

.nav-link, :deep(.nav-link) {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 7px;
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.45 0.005 40);
    cursor: pointer;
    transition: all 0.12s ease-out;
    text-decoration: none;
}

.nav-link:hover { background: oklch(0.98 0.003 70); color: oklch(0.18 0.005 40); }

.nav-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
</style>
```

- [ ] **Step 3: Create NavLink component**

Create `resources/js/Layouts/NavLink.vue`:

```vue
<template>
    <Link :href="href" class="nav-link" :class="{ 'nav-link--active': active }">
        <svg class="nav-icon" viewBox="0 0 24 24">
            <template v-if="icon === 'settings'"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></template>
            <template v-else-if="icon === 'activity'"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></template>
            <template v-else-if="icon === 'chart'"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></template>
            <template v-else-if="icon === 'user'"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></template>
            <template v-else-if="icon === 'users'"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></template>
        </svg>
        <slot />
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    icon: String,
    active: Boolean,
});
</script>

<style scoped>
.nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 7px;
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.45 0.005 40);
    cursor: pointer;
    transition: all 0.12s ease-out;
    text-decoration: none;
}

.nav-link:hover { background: oklch(0.98 0.003 70); color: oklch(0.18 0.005 40); }

.nav-link--active {
    background: oklch(0.54 0.22 27 / 0.06);
    color: oklch(0.54 0.22 27);
    font-weight: 600;
}

.nav-icon {
    width: 18px; height: 18px; flex-shrink: 0;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}
</style>
```

- [ ] **Step 4: Update all admin pages to use AdminLayout**

Update each admin page (Settings, Tracker, BoatMetrics, Profile, Team) to use the layout. Example for `resources/js/Pages/Admin/Settings.vue`:

```vue
<template>
    <AdminLayout>
        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Settings</h1>
        </div>
        <p class="text-text-secondary">Settings page coming soon.</p>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
</script>
```

Repeat the pattern for Tracker, BoatMetrics, Profile, Team (changing the title).

- [ ] **Step 5: Build and verify**

Run:
```bash
npm run build
php artisan migrate:fresh --seed
```

Start dev server and log in at `/login` with `jess@mintopia.net` / `password`. Verify sidebar renders with all nav items and page switching works.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add admin layout with sidebar navigation

AdminLayout with grouped nav (Boat, Links, Account), user footer,
Tailwind design tokens matching approved mockup. All admin pages
use shared layout."
```

---

### Task 5: Boat Settings Model and Settings Page

**Files:**
- Create: `database/migrations/2026_05_19_000001_create_boat_settings_table.php`
- Create: `app/Models/BoatSetting.php`
- Create: `app/Http/Controllers/Admin/SettingsController.php`
- Modify: `resources/js/Pages/Admin/Settings.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Write settings feature test**

Create `tests/Feature/SettingsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\BoatSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_renders(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/admin/settings');
        $response->assertStatus(200);
    }

    public function test_can_save_boat_identity(): void
    {
        $user = User::factory()->owner()->create();

        $response = $this->actingAs($user)->put('/admin/settings/identity', [
            'boat_name' => 'Scarlet',
            'mmsi' => '235117890',
        ]);

        $response->assertRedirect();
        $this->assertEquals('Scarlet', BoatSetting::getValue('boat_name'));
        $this->assertEquals('235117890', BoatSetting::getValue('mmsi'));
    }

    public function test_can_save_passage(): void
    {
        $user = User::factory()->owner()->create();

        $response = $this->actingAs($user)->put('/admin/settings/passage', [
            'passage_from' => 'La Rochelle',
            'passage_to' => 'Hendaye',
        ]);

        $response->assertRedirect();
        $this->assertEquals('La Rochelle', BoatSetting::getValue('passage_from'));
    }

    public function test_can_save_port(): void
    {
        $user = User::factory()->owner()->create();

        $response = $this->actingAs($user)->put('/admin/settings/port', [
            'port_name' => 'Lymington Marina',
        ]);

        $response->assertRedirect();
        $this->assertEquals('Lymington Marina', BoatSetting::getValue('port_name'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SettingsTest`

- [ ] **Step 3: Create boat_settings migration**

Create `database/migrations/2026_05_19_000001_create_boat_settings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_settings');
    }
};
```

- [ ] **Step 4: Create BoatSetting model**

Create `app/Models/BoatSetting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BoatSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember("boat_setting.{$key}", 60, function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("boat_setting.{$key}");
    }

    public static function getAll(): array
    {
        return static::pluck('value', 'key')->toArray();
    }
}
```

- [ ] **Step 5: Create SettingsController**

Create `app/Http/Controllers/Admin/SettingsController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings', [
            'settings' => BoatSetting::getAll(),
        ]);
    }

    public function updateIdentity(Request $request)
    {
        $validated = $request->validate([
            'boat_name' => ['required', 'string', 'max:100'],
            'mmsi' => ['nullable', 'string', 'size:9', 'regex:/^\d{9}$/'],
        ]);

        BoatSetting::setValue('boat_name', $validated['boat_name']);
        BoatSetting::setValue('mmsi', $validated['mmsi'] ?? '');

        return back()->with('success', 'Boat identity updated.');
    }

    public function updatePassage(Request $request)
    {
        $validated = $request->validate([
            'passage_from' => ['nullable', 'string', 'max:100'],
            'passage_to' => ['nullable', 'string', 'max:100'],
        ]);

        BoatSetting::setValue('passage_from', $validated['passage_from'] ?? '');
        BoatSetting::setValue('passage_to', $validated['passage_to'] ?? '');

        return back()->with('success', 'Passage updated.');
    }

    public function updatePort(Request $request)
    {
        $validated = $request->validate([
            'port_name' => ['nullable', 'string', 'max:100'],
        ]);

        BoatSetting::setValue('port_name', $validated['port_name'] ?? '');

        return back()->with('success', 'Port settings updated.');
    }
}
```

- [ ] **Step 6: Update routes**

Add to the admin group in `routes/web.php`:

```php
use App\Http\Controllers\Admin\SettingsController;

// Replace the existing settings route:
Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
Route::put('/settings/identity', [SettingsController::class, 'updateIdentity'])->name('admin.settings.identity');
Route::put('/settings/passage', [SettingsController::class, 'updatePassage'])->name('admin.settings.passage');
Route::put('/settings/port', [SettingsController::class, 'updatePort'])->name('admin.settings.port');
```

- [ ] **Step 7: Build Settings.vue**

Rewrite `resources/js/Pages/Admin/Settings.vue` with the full form layout from mockup `13-admin-dashboard.html` (Settings tab). Three sections (Boat Identity, Current Passage, Port Settings), each with inline save. Use Inertia forms for submission. Reference the mockup HTML for the exact structure and styling.

Key Vue logic: three separate `useForm()` instances, one per section. Each section has its own submit handler targeting the corresponding PUT route.

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ settings: Object });

const identityForm = useForm({
    boat_name: props.settings?.boat_name ?? '',
    mmsi: props.settings?.mmsi ?? '',
});

const passageForm = useForm({
    passage_from: props.settings?.passage_from ?? '',
    passage_to: props.settings?.passage_to ?? '',
});

const portForm = useForm({
    port_name: props.settings?.port_name ?? '',
});
</script>
```

- [ ] **Step 8: Run tests**

Run:
```bash
php artisan migrate:fresh --seed
npm run build
php artisan test --filter=SettingsTest
```

Expected: 4 tests PASS.

- [ ] **Step 9: Seed default boat settings from config**

Update `DatabaseSeeder.php` to populate initial settings from `config/scarlet.php`:

```php
use App\Models\BoatSetting;

// In run():
BoatSetting::setValue('boat_name', config('scarlet.name'));
BoatSetting::setValue('mmsi', config('scarlet.mmsi'));
BoatSetting::setValue('passage_from', '');
BoatSetting::setValue('passage_to', '');
BoatSetting::setValue('port_name', '');
```

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: add boat settings model and settings page

Key-value BoatSetting model with caching, SettingsController with
inline save per section (identity, passage, port). Settings.vue
with three forms matching approved mockup."
```

---

## Phase 2: Data Layer

### Task 6: Expand Prometheus Service for All Metrics

**Files:**
- Create: `app/Services/PrometheusService.php`
- Rewrite: `app/Services/MetricsService.php`
- Rewrite: `app/Services/GpsService.php`
- Modify: `config/scarlet.php`

- [ ] **Step 1: Write test for PrometheusService**

Create `tests/Unit/PrometheusServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\PrometheusService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrometheusServiceTest extends TestCase
{
    public function test_query_returns_value(): void
    {
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'vector',
                    'result' => [['value' => [1716000000, '4.6']]],
                ],
            ]),
        ]);

        $service = new PrometheusService();
        $result = $service->query('boat_speed_kn');
        $this->assertEquals(4.6, $result);
    }

    public function test_query_returns_null_when_no_data(): void
    {
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => ['resultType' => 'vector', 'result' => []],
            ]),
        ]);

        $service = new PrometheusService();
        $this->assertNull($service->query('nonexistent_metric'));
    }

    public function test_range_query_returns_series(): void
    {
        Http::fake([
            '*/api/v1/query_range*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'matrix',
                    'result' => [[
                        'values' => [[1716000000, '4.6'], [1716000015, '4.8']],
                    ]],
                ],
            ]),
        ]);

        $service = new PrometheusService();
        $result = $service->queryRange('boat_speed_kn', '1h');
        $this->assertCount(2, $result);
        $this->assertEquals(4.6, $result[0]['value']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PrometheusServiceTest`

- [ ] **Step 3: Create PrometheusService**

Create `app/Services/PrometheusService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrometheusService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('scarlet.metrics.prometheus_url');
    }

    public function query(string $promql): ?float
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $promql,
            ]);

            if (!$response->ok()) {
                return null;
            }

            $result = $response->json('data.result');
            if (empty($result)) {
                return null;
            }

            return (float) $result[0]['value'][1];
        } catch (\Throwable $e) {
            Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");
            return null;
        }
    }

    public function queryRange(string $promql, string $duration, string $step = '15s'): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/v1/query_range", [
                'query' => $promql,
                'start' => now()->sub(\Carbon\CarbonInterval::fromString($duration))->timestamp,
                'end' => now()->timestamp,
                'step' => $step,
            ]);

            if (!$response->ok()) {
                return [];
            }

            $result = $response->json('data.result');
            if (empty($result)) {
                return [];
            }

            return collect($result[0]['values'])->map(fn ($v) => [
                'timestamp' => (int) $v[0],
                'value' => (float) $v[1],
            ])->all();
        } catch (\Throwable $e) {
            Log::warning("Prometheus range query failed [{$promql}]: {$e->getMessage()}");
            return [];
        }
    }

    public function queryMultiple(array $queries): array
    {
        $results = [];
        foreach ($queries as $key => $promql) {
            $results[$key] = $this->query($promql);
        }
        return $results;
    }
}
```

- [ ] **Step 4: Rewrite MetricsService**

Rewrite `app/Services/MetricsService.php` to return structured metric groups:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MetricsService
{
    public function __construct(protected PrometheusService $prometheus) {}

    public function getBoatMetrics(): array
    {
        return $this->prometheus->queryMultiple([
            'speed_sog' => 'scarlet_boat_speed_kn',
            'speed_stw' => 'scarlet_signalk_navigation_speed_through_water_kn',
            'heading' => 'scarlet_boat_heading_deg',
            'cog' => 'scarlet_signalk_navigation_course_over_ground_deg',
            'depth' => 'scarlet_boat_depth_m',
            'wind_speed_true' => 'scarlet_boat_wind_speed_kn',
            'wind_direction_true' => 'scarlet_boat_wind_direction_deg',
            'wind_speed_apparent' => 'scarlet_signalk_environment_wind_speed_apparent_kn',
            'wind_angle_apparent' => 'scarlet_signalk_environment_wind_angle_apparent_deg',
            'heel' => 'scarlet_signalk_navigation_attitude_heel_deg',
            'rudder' => 'scarlet_signalk_steering_rudder_angle_deg',
            'rate_of_turn' => 'scarlet_signalk_navigation_rate_of_turn_deg_per_min',
            'water_temp' => 'scarlet_signalk_environment_water_temperature_c',
            'pressure' => 'scarlet_signalk_environment_outside_pressure_hpa',
            'house_battery_voltage' => 'scarlet_signalk_electrical_batteries_house_voltage',
            'house_battery_soc' => 'scarlet_signalk_electrical_batteries_house_soc',
            'house_battery_current' => 'scarlet_signalk_electrical_batteries_house_current',
            'engine_battery_voltage' => 'scarlet_signalk_electrical_batteries_engine_voltage',
            'solar_power' => 'scarlet_signalk_electrical_solar_power_w',
            'solar_current' => 'scarlet_signalk_electrical_solar_current_a',
            'solar_voltage' => 'scarlet_signalk_electrical_solar_voltage_v',
            'load_current' => 'scarlet_signalk_electrical_load_current_a',
            'fuel_level' => 'scarlet_boat_fuel_level_pct',
            'water_level' => 'scarlet_boat_water_level_pct',
            'engine_rpm' => 'scarlet_signalk_propulsion_engine_revolutions',
            'engine_hours' => 'scarlet_signalk_propulsion_engine_run_time_h',
            'engine_coolant_temp' => 'scarlet_signalk_propulsion_engine_coolant_temp_c',
            'trip_log' => 'scarlet_signalk_navigation_trip_log_nm',
            'air_temp' => 'scarlet_signalk_environment_outside_temperature_c',
        ]);
    }

    public function getTrackerMetrics(): array
    {
        return $this->prometheus->queryMultiple([
            'battery_voltage' => 'scarlet_system_battery_voltage_volts',
            'battery_percent' => 'scarlet_system_battery_percent',
            'lte_rssi' => 'scarlet_system_lte_rssi_dbm',
            'wifi_rssi' => 'scarlet_system_wifi_rssi_dbm',
            'uptime' => 'scarlet_system_uptime_seconds',
            'heap_free' => 'scarlet_system_heap_free_bytes',
            'cabin_temp' => 'scarlet_environment_temperature_c',
            'cabin_humidity' => 'scarlet_environment_humidity_pct',
        ]);
    }

    public function getGpsMetrics(): array
    {
        return $this->prometheus->queryMultiple([
            'latitude' => 'scarlet_gps_latitude',
            'longitude' => 'scarlet_gps_longitude',
            'satellites' => 'scarlet_gps_satellites',
            'hdop' => 'scarlet_gps_hdop',
        ]);
    }

    public function getAllMetrics(): array
    {
        return [
            'boat' => $this->getBoatMetrics(),
            'tracker' => $this->getTrackerMetrics(),
            'gps' => $this->getGpsMetrics(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

Note: Prometheus metric names follow the pattern `scarlet_<source>_<metric>`. The exact names will need to match what the OTel collector exports. Verify against the actual Prometheus instance and adjust names accordingly during implementation.

- [ ] **Step 5: Rewrite GpsService to use Prometheus**

Rewrite `app/Services/GpsService.php`:

```php
<?php

namespace App\Services;

use App\Models\Gps;
use Illuminate\Support\Facades\Cache;

class GpsService
{
    public function __construct(protected PrometheusService $prometheus) {}

    public function getLocation(bool $force = false): Gps
    {
        if (!$force && $cached = Cache::get('gps.location')) {
            return $cached;
        }

        $data = $this->prometheus->queryMultiple([
            'latitude' => 'scarlet_gps_latitude',
            'longitude' => 'scarlet_gps_longitude',
            'speed' => 'scarlet_boat_speed_kn',
            'course' => 'scarlet_boat_heading_deg',
            'satellites' => 'scarlet_gps_satellites',
            'hdop' => 'scarlet_gps_hdop',
        ]);

        $gps = new Gps();
        $gps->latitude = $data['latitude'] ?? 0;
        $gps->longitude = $data['longitude'] ?? 0;
        $gps->speed = $data['speed'] ?? 0;
        $gps->course = $data['course'] ?? 0;
        $gps->satellites = (int) ($data['satellites'] ?? 0);
        $gps->hdop = (int) ($data['hdop'] ?? 9999);
        $gps->valid = $gps->latitude !== 0.0 && $gps->longitude !== 0.0;
        $gps->timestamp = now();

        Cache::put('gps.location', $gps, 10);
        return $gps;
    }
}
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=PrometheusServiceTest`

Expected: 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: expand Prometheus integration for all boat metrics

PrometheusService for query/queryRange, MetricsService restructured
into boat/tracker/gps metric groups, GpsService rewritten to use
Prometheus instead of ThingsBoard."
```

---

### Task 7: WebSocket Broadcasting and Real-time Updates

**Files:**
- Modify: `app/Events/MetricsUpdated.php`
- Modify: `app/Console/Commands/MetricsPushCommand.php`
- Modify: `routes/channels.php`
- Create: `resources/js/composables/useEcho.js`

- [ ] **Step 1: Update MetricsUpdated event with structured payload**

Rewrite `app/Events/MetricsUpdated.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class MetricsUpdated implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public array $boat,
        public array $tracker,
        public array $gps,
        public string $timestamp,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('metrics');
    }

    public function broadcastAs(): string
    {
        return 'metrics.updated';
    }
}
```

- [ ] **Step 2: Update MetricsPushCommand**

Rewrite `app/Console/Commands/MetricsPushCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Events\MetricsUpdated;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MetricsPushCommand extends Command
{
    protected $signature = 'metrics:push';
    protected $description = 'Query Prometheus and broadcast metrics via Reverb every 15 seconds';

    public function handle(MetricsService $metricsService): int
    {
        $interval = config('scarlet.metrics.push_interval');
        $this->info("Starting metrics push loop (every {$interval}s)");

        while (true) {
            try {
                $all = $metricsService->getAllMetrics();
                MetricsUpdated::dispatch(
                    $all['boat'],
                    $all['tracker'],
                    $all['gps'],
                    $all['timestamp'],
                );
                $this->line('Pushed metrics at ' . $all['timestamp']);
            } catch (\Throwable $e) {
                Log::error("Metrics push failed: {$e->getMessage()}");
                $this->error("Error: {$e->getMessage()}");
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 3: Create useEcho composable**

Create `resources/js/composables/useEcho.js`:

```javascript
import { ref, onMounted, onUnmounted } from 'vue';

export function useMetrics() {
    const metrics = ref(null);
    const lastUpdate = ref(null);
    let channel = null;

    onMounted(() => {
        if (!window.Echo) return;

        channel = window.Echo.channel('metrics');
        channel.listen('.metrics.updated', (data) => {
            metrics.value = data;
            lastUpdate.value = new Date();
        });
    });

    onUnmounted(() => {
        if (channel) {
            window.Echo.leave('metrics');
        }
    });

    return { metrics, lastUpdate };
}
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: restructure WebSocket broadcasting with grouped metrics

MetricsUpdated event carries boat/tracker/gps groups. MetricsPushCommand
uses expanded MetricsService. useEcho composable for Vue components."
```

---

## Phase 3: Admin Pages

### Task 8: Tracker Page

**Files:**
- Create: `app/Http/Controllers/Admin/TrackerController.php`
- Rewrite: `resources/js/Pages/Admin/Tracker.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Create TrackerController**

Create `app/Http/Controllers/Admin/TrackerController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use App\Services\PrometheusService;
use Inertia\Inertia;

class TrackerController extends Controller
{
    public function index(MetricsService $metrics, PrometheusService $prometheus)
    {
        return Inertia::render('Admin/Tracker', [
            'tracker' => $metrics->getTrackerMetrics(),
            'gps' => $metrics->getGpsMetrics(),
            'signalHistory' => [
                'lte' => $prometheus->queryRange('scarlet_system_lte_rssi_dbm', '1h', '60s'),
                'wifi' => $prometheus->queryRange('scarlet_system_wifi_rssi_dbm', '1h', '60s'),
            ],
            'gpsHistory' => $prometheus->queryRange('scarlet_gps_satellites', '1h', '60s'),
            'tempHistory' => $prometheus->queryRange('scarlet_environment_temperature_c', '6h', '120s'),
            'humidityHistory' => $prometheus->queryRange('scarlet_environment_humidity_pct', '6h', '120s'),
        ]);
    }
}
```

- [ ] **Step 2: Update route**

In `routes/web.php`, replace the tracker placeholder:

```php
use App\Http\Controllers\Admin\TrackerController;

Route::get('/tracker', [TrackerController::class, 'index'])->name('admin.tracker');
```

- [ ] **Step 3: Build Tracker.vue**

Rewrite `resources/js/Pages/Admin/Tracker.vue`. Translate the Tracker tab from mockup `13-admin-dashboard.html`. The page has:

1. Page header with "Tracker" title and live indicator
2. Device status strip (4-column grid): Status, Connection, Mode, Battery
3. Signal Strength chart (SVG area chart, LTE + WiFi lines)
4. GPS Quality chart (SVG area chart, satellite count)
5. Cabin Temperature chart (SVG area chart)
6. Humidity chart (SVG area chart)
7. Device Details table

Key Vue logic:
- Accept props: `tracker`, `gps`, `signalHistory`, `gpsHistory`, `tempHistory`, `humidityHistory`
- Use `useMetrics()` composable for live WebSocket updates
- Compute display values from props, override with WebSocket data when available
- SVG charts: render `<polyline>` from history arrays, scaling values to viewBox coordinates

```vue
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useMetrics } from '@/composables/useEcho.js';
import { computed } from 'vue';

const props = defineProps({
    tracker: Object,
    gps: Object,
    signalHistory: Object,
    gpsHistory: Array,
    tempHistory: Array,
    humidityHistory: Array,
});

const { metrics, lastUpdate } = useMetrics();

const live = computed(() => metrics.value?.tracker ?? props.tracker);

const timeSinceUpdate = computed(() => {
    if (!lastUpdate.value) return 'Loading...';
    const seconds = Math.floor((Date.now() - lastUpdate.value) / 1000);
    return `${seconds}s ago`;
});
</script>
```

For the SVG chart rendering, create a helper function that converts an array of `{timestamp, value}` objects to SVG polyline points:

```javascript
function toPolyline(data, viewWidth, viewHeight, minVal, maxVal) {
    if (!data || data.length === 0) return '';
    const range = maxVal - minVal || 1;
    return data.map((d, i) => {
        const x = (i / (data.length - 1)) * viewWidth;
        const y = viewHeight - ((d.value - minVal) / range) * (viewHeight - 10) - 5;
        return `${x},${y}`;
    }).join(' ');
}
```

- [ ] **Step 4: Build and verify visually**

Run:
```bash
npm run build
```

Log in and navigate to the Tracker page. Verify the layout matches the mockup.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add tracker page with device monitoring

TrackerController serving Prometheus data with history ranges.
Tracker.vue with status strip, signal/GPS/temp/humidity charts,
device details table. Live WebSocket updates via useEcho composable."
```

---

### Task 9: Boat Metrics Page

**Files:**
- Create: `app/Http/Controllers/Admin/BoatMetricsController.php`
- Rewrite: `resources/js/Pages/Admin/BoatMetrics.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Create BoatMetricsController**

Create `app/Http/Controllers/Admin/BoatMetricsController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use App\Services\PrometheusService;
use Inertia\Inertia;

class BoatMetricsController extends Controller
{
    public function index(MetricsService $metrics, PrometheusService $prometheus)
    {
        return Inertia::render('Admin/BoatMetrics', [
            'boat' => $metrics->getBoatMetrics(),
            'pressureHistory' => $prometheus->queryRange('scarlet_signalk_environment_outside_pressure_hpa', '24h', '300s'),
            'batteryHistory' => $prometheus->queryRange('scarlet_signalk_electrical_batteries_house_soc', '24h', '300s'),
            'waterTempHistory' => $prometheus->queryRange('scarlet_signalk_environment_water_temperature_c', '24h', '300s'),
            'airTempHistory' => $prometheus->queryRange('scarlet_signalk_environment_outside_temperature_c', '24h', '300s'),
        ]);
    }
}
```

- [ ] **Step 2: Update route**

In `routes/web.php`:

```php
use App\Http\Controllers\Admin\BoatMetricsController;

Route::get('/metrics', [BoatMetricsController::class, 'index'])->name('admin.metrics');
```

- [ ] **Step 3: Build BoatMetrics.vue**

Rewrite `resources/js/Pages/Admin/BoatMetrics.vue`. Translate the Boat Metrics tab from mockup `13-admin-dashboard.html`. The page has:

1. Page header with live indicator
2. Critical metrics strip (5 columns with sparklines): Speed, Depth, Wind, House Battery, Heading
3. Barometric Pressure chart (24h)
4. Battery State of Charge chart (24h)
5. Tank Levels (fuel + water bars)
6. Wind compass rose panel (SVG with scarlet arrow rotated to wind direction)
7. Navigation compass panel (SVG with blue arrow rotated to heading)
8. Power Balance section (horizontal fill bars + net indicator)
9. Water & Air Temperature chart (dual-line)
10. Engine panel

Key Vue logic:
- SVG compass: use `transform="rotate(${angle}, 70, 70)"` on the arrow `<g>` element
- Power balance: calculate bar widths as percentages of max (e.g., 200W)
- Net power: solar - load, positive = charging
- Tank bars: `style="width: ${percentage}%"` on fill divs

The compass SVGs are already fully defined in the mockup HTML. Copy the SVG structure and make the rotation angle dynamic via the `wind_direction_true` and `heading` metric values.

- [ ] **Step 4: Build and verify visually**

Run:
```bash
npm run build
```

Verify against mockup: compass arrows render, power bars fill correctly, charts display.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add boat metrics page with charts and compass panels

BoatMetricsController with 24h history data. BoatMetrics.vue with
critical strip, pressure/battery/temp charts, wind/nav compass SVGs,
power balance bars, tank levels, engine status."
```

---

### Task 10: Profile Page and Password Change

**Files:**
- Create: `app/Http/Controllers/Admin/ProfileController.php`
- Rewrite: `resources/js/Pages/Admin/Profile.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Write profile test**

Create `tests/Feature/ProfileTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/admin/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.com']);
    }

    public function test_can_change_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $response = $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_wrong_current_password_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => 'wrong',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProfileTest`

- [ ] **Step 3: Create ProfileController**

Create `app/Http/Controllers/Admin/ProfileController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Profile', [
            'user' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::min(8), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated.');
    }
}
```

- [ ] **Step 4: Update routes**

```php
use App\Http\Controllers\Admin\ProfileController;

Route::get('/profile', [ProfileController::class, 'index'])->name('admin.profile');
Route::put('/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password');
```

- [ ] **Step 5: Build Profile.vue**

Rewrite `resources/js/Pages/Admin/Profile.vue`. Translate the Profile tab from mockup `13-admin-dashboard.html`. Three sections:
1. Your Details (name, email, save button)
2. Change Password (current, new, confirm, update button)
3. Passkeys (table, register new button) — render empty for now, functional in Task 11

Two separate `useForm()` instances: one for profile details, one for password.

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=ProfileTest`

Expected: 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add profile page with name/email and password change

ProfileController with update and password change validation.
Profile.vue with two forms and passkeys table placeholder."
```

---

### Task 11: WebAuthn Passkey Support

**Files:**
- Modify: `composer.json` (add laragear/webauthn)
- Modify: `app/Models/User.php`
- Create: `app/Http/Controllers/Auth/PasskeyController.php`
- Modify: `resources/js/Pages/Auth/Login.vue`
- Modify: `resources/js/Pages/Admin/Profile.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Install laragear/webauthn**

Run:
```bash
composer require laragear/webauthn
php artisan vendor:publish --provider="Laragear\WebAuthn\WebAuthnServiceProvider"
php artisan migrate
```

- [ ] **Step 2: Add WebAuthnAuthenticatable to User model**

In `app/Models/User.php`, add:

```php
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;

class User extends Authenticatable implements WebAuthnAuthenticatable
{
    use HasFactory, Notifiable, WebAuthnAuthentication;
    // ... rest unchanged
}
```

- [ ] **Step 3: Create PasskeyController**

Create `app/Http/Controllers/Auth/PasskeyController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

class PasskeyController extends Controller
{
    public function registerOptions(AttestationRequest $request)
    {
        return $request->toCreate();
    }

    public function register(AttestedRequest $request)
    {
        $request->save();
        return back()->with('success', 'Passkey registered.');
    }

    public function loginOptions(AssertionRequest $request)
    {
        return $request->toVerify();
    }

    public function login(AssertedRequest $request)
    {
        $user = $request->login();

        if ($user) {
            $request->session()->regenerate();
            return redirect()->intended('/admin/settings');
        }

        return back()->withErrors(['passkey' => 'Passkey authentication failed.']);
    }

    public function destroy(Request $request, int $id)
    {
        $request->user()->webAuthnCredentials()->where('id', $id)->delete();
        return back()->with('success', 'Passkey removed.');
    }

    public function list(Request $request)
    {
        return $request->user()->webAuthnCredentials()
            ->select('id', 'alias', 'created_at', 'updated_at')
            ->get();
    }
}
```

- [ ] **Step 4: Add passkey routes**

Add to `routes/web.php`:

```php
use App\Http\Controllers\Auth\PasskeyController;

// Auth passkey routes (login)
Route::post('/passkey/login/options', [PasskeyController::class, 'loginOptions'])->middleware('guest');
Route::post('/passkey/login', [PasskeyController::class, 'login'])->middleware('guest');

// Authenticated passkey routes (register/manage)
Route::middleware('auth')->group(function () {
    Route::post('/passkey/register/options', [PasskeyController::class, 'registerOptions']);
    Route::post('/passkey/register', [PasskeyController::class, 'register']);
    Route::delete('/passkey/{id}', [PasskeyController::class, 'destroy']);
    Route::get('/passkey/list', [PasskeyController::class, 'list']);
});
```

- [ ] **Step 5: Add passkey JavaScript to Login.vue**

Add WebAuthn browser API calls to the Login.vue passkey button. The flow:
1. Click "Sign in with passkey"
2. POST to `/passkey/login/options` to get challenge
3. Call `navigator.credentials.get()` with the challenge
4. POST the credential response to `/passkey/login`

```javascript
async function loginWithPasskey() {
    try {
        const optionsResponse = await fetch('/passkey/login/options', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        });
        const options = await optionsResponse.json();

        const credential = await navigator.credentials.get({ publicKey: options });

        const response = await fetch('/passkey/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify(credential),
        });

        if (response.ok) {
            window.location.href = '/admin/settings';
        }
    } catch (e) {
        console.error('Passkey login failed:', e);
    }
}
```

Note: The exact request/response format depends on `laragear/webauthn` version. Refer to the package documentation for the correct serialization of the WebAuthn credential. The package may provide JS helpers.

- [ ] **Step 6: Add passkey management to Profile.vue**

In the Passkeys section of Profile.vue:
- Fetch passkeys from `/passkey/list` on mount
- "Register new" button triggers attestation flow (similar to login but with `navigator.credentials.create()`)
- "Remove" button sends DELETE to `/passkey/{id}`

- [ ] **Step 7: Add CSRF meta tag to app.blade.php**

Add to the `<head>` of `resources/views/app.blade.php`:

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

- [ ] **Step 8: Build and test**

Run:
```bash
npm run build
```

Test manually: register a passkey from Profile page, then log out and log back in with passkey. Note: WebAuthn requires HTTPS in production; testing may need localhost exception or a tool like `ngrok`.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add WebAuthn passkey authentication

Install laragear/webauthn, PasskeyController for register/login/delete,
passkey login option on Login.vue, passkey management on Profile.vue."
```

---

### Task 12: Team Management and Invite System

**Files:**
- Create: `database/migrations/2026_05_19_000002_create_invites_table.php`
- Create: `app/Models/Invite.php`
- Create: `app/Http/Controllers/Admin/TeamController.php`
- Create: `app/Http/Controllers/Auth/RegisterController.php`
- Create: `app/Mail/TeamInviteMail.php`
- Create: `resources/views/mail/team-invite.blade.php`
- Create: `resources/js/Pages/Auth/Register.vue`
- Rewrite: `resources/js/Pages/Admin/Team.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Write team feature test**

Create `tests/Feature/TeamTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Mail\TeamInviteMail;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_member(): void
    {
        Mail::fake();
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->post('/admin/team/invite', [
            'email' => 'newcrew@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invites', ['email' => 'newcrew@example.com']);
        Mail::assertSent(TeamInviteMail::class);
    }

    public function test_crew_cannot_invite(): void
    {
        $crew = User::factory()->create(['role' => 'crew']);

        $response = $this->actingAs($crew)->post('/admin/team/invite', [
            'email' => 'another@example.com',
        ]);

        $response->assertForbidden();
    }

    public function test_can_register_with_valid_invite(): void
    {
        $invite = Invite::create([
            'email' => 'newcrew@example.com',
            'token' => 'valid-token',
            'invited_by' => User::factory()->owner()->create()->id,
        ]);

        $response = $this->post('/register', [
            'token' => 'valid-token',
            'name' => 'New Crew',
            'email' => 'newcrew@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/admin/settings');
        $this->assertDatabaseHas('users', ['email' => 'newcrew@example.com', 'role' => 'crew']);
        $this->assertDatabaseMissing('invites', ['token' => 'valid-token']);
    }

    public function test_owner_can_remove_crew(): void
    {
        $owner = User::factory()->owner()->create();
        $crew = User::factory()->create(['role' => 'crew']);

        $response = $this->actingAs($owner)->delete("/admin/team/{$crew->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $crew->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TeamTest`

- [ ] **Step 3: Create invites migration**

Create `database/migrations/2026_05_19_000002_create_invites_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};
```

- [ ] **Step 4: Create Invite model**

Create `app/Models/Invite.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invite extends Model
{
    protected $fillable = ['email', 'token', 'invited_by'];

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
```

- [ ] **Step 5: Create TeamController**

Create `app/Http/Controllers/Admin/TeamController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TeamInviteMail;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Team', [
            'members' => User::select('id', 'name', 'email', 'role', 'updated_at')
                ->orderByDesc('role')
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'initials' => $u->initials,
                    'last_active' => $u->updated_at->diffForHumans(),
                ]),
            'invites' => Invite::select('id', 'email', 'created_at')->get(),
        ]);
    }

    public function invite(Request $request)
    {
        if (!$request->user()->isOwner()) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email', 'unique:invites,email'],
        ]);

        $invite = Invite::create([
            'email' => $validated['email'],
            'token' => Str::random(64),
            'invited_by' => $request->user()->id,
        ]);

        Mail::to($invite->email)->send(new TeamInviteMail($invite));

        return back()->with('success', 'Invite sent.');
    }

    public function resend(Request $request, Invite $invite)
    {
        if (!$request->user()->isOwner()) {
            abort(403);
        }

        Mail::to($invite->email)->send(new TeamInviteMail($invite));

        return back()->with('success', 'Invite resent.');
    }

    public function destroy(Request $request, User $user)
    {
        if (!$request->user()->isOwner() || $user->id === $request->user()->id) {
            abort(403);
        }

        $user->delete();

        return back()->with('success', 'Member removed.');
    }

    public function destroyInvite(Request $request, Invite $invite)
    {
        if (!$request->user()->isOwner()) {
            abort(403);
        }

        $invite->delete();

        return back()->with('success', 'Invite cancelled.');
    }
}
```

- [ ] **Step 6: Create TeamInviteMail**

Create `app/Mail/TeamInviteMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invite $invite) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You\'ve been invited to Scarlet');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.team-invite');
    }
}
```

Create `resources/views/mail/team-invite.blade.php`:

```blade
<p>You've been invited to join Scarlet's crew dashboard.</p>
<p><a href="{{ url('/register?token=' . $invite->token) }}">Accept invitation</a></p>
```

- [ ] **Step 7: Create RegisterController**

Create `app/Http/Controllers/Auth/RegisterController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function show(Request $request)
    {
        $invite = Invite::where('token', $request->query('token'))->firstOrFail();

        return Inertia::render('Auth/Register', [
            'token' => $invite->token,
            'email' => $invite->email,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(8), 'confirmed'],
        ]);

        $invite = Invite::where('token', $validated['token'])
            ->where('email', $validated['email'])
            ->firstOrFail();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'crew',
        ]);

        $invite->delete();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/admin/settings');
    }
}
```

- [ ] **Step 8: Create Register.vue**

Create `resources/js/Pages/Auth/Register.vue`. Similar structure to Login.vue (centered card) with fields: Name, Email (pre-filled, read-only), Password, Confirm Password, and a hidden token field.

- [ ] **Step 9: Add team routes**

```php
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Auth\RegisterController;

// Guest routes
Route::get('/register', [RegisterController::class, 'show'])->middleware('guest');
Route::post('/register', [RegisterController::class, 'store'])->middleware('guest');

// In admin group:
Route::get('/team', [TeamController::class, 'index'])->name('admin.team');
Route::post('/team/invite', [TeamController::class, 'invite'])->name('admin.team.invite');
Route::post('/team/invite/{invite}/resend', [TeamController::class, 'resend'])->name('admin.team.resend');
Route::delete('/team/member/{user}', [TeamController::class, 'destroy'])->name('admin.team.destroy');
Route::delete('/team/invite/{invite}', [TeamController::class, 'destroyInvite'])->name('admin.team.destroy-invite');
```

- [ ] **Step 10: Build Team.vue**

Rewrite `resources/js/Pages/Admin/Team.vue`. Translate the Team tab from mockup `13-admin-dashboard.html`. Two sections: Members table and Pending Invites table.

- [ ] **Step 11: Run tests**

Run: `php artisan test --filter=TeamTest`

Expected: 4 tests PASS.

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "feat: add team management with invite system

Invite model, TeamController with CRUD, TeamInviteMail,
RegisterController for invite-based registration. Team.vue
with members table and pending invites. Owner-only permissions."
```

---

## Phase 4: Stream Overlay

### Task 13: Overlay Blade Template and CSS

**Files:**
- Create: `resources/views/overlay.blade.php`
- Rewrite: `resources/css/overlay.css`
- Create: `app/Http/Controllers/OverlayController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create OverlayController**

Create `app/Http/Controllers/OverlayController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;

class OverlayController extends Controller
{
    public function index()
    {
        return view('overlay', [
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => BoatSetting::getValue('passage_from', ''),
            'passageTo' => BoatSetting::getValue('passage_to', ''),
            'portName' => BoatSetting::getValue('port_name', ''),
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
            'utcOffset' => config('scarlet.time.offset'),
            'timeLabel' => config('scarlet.time.label'),
        ]);
    }
}
```

- [ ] **Step 2: Update routes**

In `routes/web.php`, replace the overlay route:

```php
use App\Http\Controllers\OverlayController;

Route::get('/overlay', [OverlayController::class, 'index'])->name('overlay');
```

Keep the old `/` route pointing to the old HomeController for backward compatibility during transition. Add a redirect from `/` to `/overlay` once the old overlay is no longer needed.

- [ ] **Step 3: Create overlay.blade.php**

Create `resources/views/overlay.blade.php`. This is the new broadcast-style overlay at 1920x1080 fixed layout. Translate from mockup `05-overlay-broadcast.html` (State 1).

The template includes:
- Leaflet CSS/JS from CDN (or npm if preferred)
- Outfit font from Google Fonts
- `overlay.css` via Vite
- `overlay.js` via Vite
- Config object in `window.scarletConfig` with reverb settings, boat data, UTC offset
- DOM structure for: PiP map container, LIVE badge, weather pills, lower third (brand tab, metrics, status pill, passage, clock)
- Hidden elements for states 2-4 (shown/hidden by JS state machine)

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1920">
    <title>Scarlet Overlay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>
    @vite(['resources/css/overlay.css', 'resources/js/overlay.js'])
    <script>
        window.scarletConfig = {
            utcOffset: @json($utcOffset),
            timeLabel: @json($timeLabel),
            boatName: @json($boatName),
            passageFrom: @json($passageFrom),
            passageTo: @json($passageTo),
            portName: @json($portName),
            reverb: {
                key: @json($reverbKey),
                host: @json($reverb['host']),
                port: @json($reverb['port']),
                scheme: @json($reverb['scheme']),
            },
            tileUrl: '/openseamap/{z}/{x}/{y}',
        };
    </script>
</head>
<body>
    <div id="overlay" data-state="loading">
        <!-- Map containers (PiP for state 1, full for states 2-4) -->
        <div id="map-pip"></div>
        <div id="map-full"></div>

        <!-- LIVE badge -->
        <div id="live-badge">
            <span class="live-dot"></span>
            <span class="live-text">LIVE</span>
            <span id="live-extension"></span>
        </div>

        <!-- Weather pills (top right) -->
        <div id="weather-strip">
            <div class="weather-hero" id="weather-hero"></div>
            <div class="weather-pill" id="weather-sea"></div>
            <div class="weather-pill" id="weather-wind"></div>
            <div class="weather-pill" id="weather-waves"></div>
        </div>

        <!-- Coordinate badge (states 2-4) -->
        <div id="coord-badge"></div>

        <!-- Speed legend (states 2-4) -->
        <div id="speed-legend"></div>

        <!-- Offline card (state 3) -->
        <div id="offline-card">
            <p class="offline-title">Telemetry Unavailable</p>
            <p id="offline-last-update">Last update received —</p>
        </div>

        <!-- Lower third -->
        <div id="lower-third">
            <div class="brand-tab">{{ $boatName }}</div>
            <div id="metrics-strip">
                <div class="metric" id="metric-speed"><span class="metric-label">Speed</span><span class="metric-value">--</span></div>
                <div class="metric" id="metric-heading"><span class="metric-label">Heading</span><span class="metric-value">--</span></div>
                <div class="metric" id="metric-depth"><span class="metric-label">Depth</span><span class="metric-value">--</span></div>
            </div>
            <div id="status-pill">--</div>
            <div id="passage">
                @if($passageFrom && $passageTo)
                    {{ $passageFrom }} → {{ $passageTo }}
                @endif
            </div>
            <div id="clock"></div>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 4: Write overlay.css**

Rewrite `resources/css/overlay.css` with the full broadcast overlay styles from mockups `05-overlay-broadcast.html` through `08-overlay-idle.html`. Key tokens:

```css
:root {
    --chrome-55: oklch(0.10 0.008 40 / 0.55);
    --chrome-72: oklch(0.08 0.008 40 / 0.72);
    --scarlet: oklch(0.54 0.22 27);
    --text-bright: oklch(0.96 0.005 70);
    --text-mid: oklch(0.75 0.008 70);
    --text-dim: oklch(0.62 0.008 70);
    --blur: blur(24px);
    --radius: 10px;
    --radius-sm: 7px;
    --font: 'Outfit', system-ui, sans-serif;
    --status-sail: oklch(0.78 0.12 155);
    --status-power: oklch(0.75 0.10 70);
    --status-port: oklch(0.72 0.08 230);
}

html, body {
    margin: 0;
    width: 1920px;
    height: 1080px;
    background: transparent;
    overflow: hidden;
    font-family: var(--font);
    color: var(--text-bright);
}
```

The full CSS translates directly from the approved mockup HTML files. Copy the styles from each mockup, combining them with CSS classes toggled by the JS state machine (e.g., `[data-state="video-live"]`, `[data-state="no-video"]`, `[data-state="offline"]`, `[data-state="port"]`).

- [ ] **Step 5: Verify overlay renders**

Run:
```bash
npm run build
```

Visit `/overlay` in browser. Verify the template renders at 1920x1080 with the broadcast design. Data will show dashes until the WebSocket connects.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add broadcast overlay template and CSS

OverlayController with boat settings, overlay.blade.php with DOM
structure for 4 states, overlay.css with broadcast design tokens
from approved mockups."
```

---

### Task 14: Overlay JavaScript — State Machine and WebSocket

**Files:**
- Rewrite: `resources/js/overlay.js`
- Modify: `resources/js/bootstrap.js`

- [ ] **Step 1: Update bootstrap.js**

Remove the overlay import from `resources/js/bootstrap.js`. The bootstrap file should only set up axios and Echo, shared by both entry points:

```javascript
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const reverb = window.scarletConfig?.reverb ?? {};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverb.key,
    wsHost: reverb.host,
    wsPort: reverb.port ?? 8080,
    wssPort: reverb.port ?? 443,
    forceTLS: (reverb.scheme ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

- [ ] **Step 2: Rewrite overlay.js with state machine**

Rewrite `resources/js/overlay.js`:

```javascript
import './bootstrap';

const Overlay = {
    state: 'loading',
    lastMetricsTime: null,
    elements: {},
    weatherTimer: null,
    clockTimer: null,

    el(id) {
        if (!this.elements[id]) this.elements[id] = document.getElementById(id);
        return this.elements[id];
    },

    setState(newState) {
        if (this.state === newState) return;
        this.state = newState;
        document.getElementById('overlay').dataset.state = newState;
    },

    determineState(metrics) {
        if (!metrics || !this.lastMetricsTime) {
            return 'loading';
        }

        const ageMs = Date.now() - this.lastMetricsTime;
        const twoHours = 2 * 60 * 60 * 1000;

        if (ageMs > twoHours) return 'offline';

        const speed = metrics.boat?.speed_sog;
        const portName = window.scarletConfig?.portName;
        if ((speed === null || speed === 0) && portName) return 'port';

        // Video feed state is set via admin config or OBS webhook
        if (window.scarletConfig?.videoFeedActive) return 'video-live';

        return 'no-video';
    },

    onMetrics(data) {
        this.lastMetricsTime = Date.now();
        const newState = this.determineState(data);
        this.setState(newState);
        this.updateMetricsDOM(data);
        this.updateStateUI(newState, data);
    },

    updateStateUI(state, data) {
        // Offline card: show last update time
        if (state === 'offline' && this.lastMetricsTime) {
            const ago = new Date(this.lastMetricsTime);
            this.el('offline-last-update').textContent =
                `Last update received ${ago.toLocaleString()}`;
        }

        // LIVE badge extensions
        const liveBadge = this.el('live-badge');
        if (state === 'offline') {
            liveBadge.innerHTML = '<span class="badge-dot badge-amber"></span> OFFLINE <span class="badge-ext">Telemetry Unavailable</span>';
        } else if (state === 'no-video') {
            liveBadge.innerHTML = '<span class="badge-dot badge-live"></span> LIVE <span class="badge-ext">Video Offline</span>';
        } else if (state === 'port') {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            liveBadge.innerHTML = `<span class="badge-dot badge-live"></span> LIVE <span class="badge-ext">Updated ${hh}:${mm}</span>`;
        } else {
            liveBadge.innerHTML = '<span class="badge-dot badge-live"></span> LIVE';
        }

        // Port state: suppress heading rotation on boat marker
        if (state === 'port' && this.boatMarkerFull) {
            const el = this.boatMarkerFull.getElement();
            if (el) el.querySelector('svg').style.transform = 'rotate(0deg)';
        }

        // Offline: show dashes for all metrics
        if (state === 'offline') {
            ['metric-speed', 'metric-heading', 'metric-depth'].forEach(id => {
                const val = this.el(id)?.querySelector('.metric-value');
                if (val) val.textContent = '--';
            });
        }
    },

    updateMetricsDOM(data) {
        const boat = data.boat || {};
        const gps = data.gps || {};

        const speed = boat.speed_sog;
        const heading = boat.heading;
        const depth = boat.depth;

        this.el('metric-speed').querySelector('.metric-value').textContent =
            speed != null ? speed.toFixed(1) + ' kn' : '--';
        this.el('metric-heading').querySelector('.metric-value').textContent =
            heading != null ? Math.round(heading) + '°' : '--';
        this.el('metric-depth').querySelector('.metric-value').textContent =
            depth != null ? depth.toFixed(1) + ' m' : '--';

        // Status pill
        const rpm = boat.engine_rpm;
        const statusPill = this.el('status-pill');
        if (this.state === 'port') {
            statusPill.textContent = 'In Port';
            statusPill.className = 'status-pill status-port';
        } else if (rpm && rpm > 0) {
            statusPill.textContent = 'Under Power';
            statusPill.className = 'status-pill status-power';
        } else {
            statusPill.textContent = 'Under Sail';
            statusPill.className = 'status-pill status-sail';
        }

        // Coordinates
        if (gps.latitude && gps.longitude) {
            this.el('coord-badge').textContent =
                `${gps.latitude.toFixed(4)}°N ${gps.longitude.toFixed(4)}°W`;
        }
    },

    updateClock() {
        const now = new Date();
        const offset = window.scarletConfig?.utcOffset ?? 0;
        const utc = now.getTime() + now.getTimezoneOffset() * 60000;
        const local = new Date(utc + offset * 3600000);
        const hours = String(local.getHours()).padStart(2, '0');
        const minutes = String(local.getMinutes()).padStart(2, '0');
        this.el('clock').textContent = `${hours}:${minutes}`;
    },

    async fetchWeather() {
        try {
            const response = await fetch('/api/v1/weather');
            if (!response.ok) return;
            const data = await response.json();
            this.updateWeatherDOM(data);
        } catch (e) {
            console.warn('Weather fetch failed:', e);
        }
    },

    updateWeatherDOM(data) {
        this.el('weather-hero').textContent = `${data.temp}°C`;
        this.el('weather-sea').textContent = `Sea ${data.seaTemp}°C`;
        this.el('weather-wind').textContent = `Wind ${data.wind.speed} kn`;
        this.el('weather-waves').textContent = `Waves ${data.waves.height} m`;
    },

    initWebSocket() {
        if (!window.Echo) return;
        window.Echo.channel('metrics').listen('.metrics.updated', (data) => {
            this.onMetrics(data);
        });
    },

    init() {
        this.clockTimer = setInterval(() => this.updateClock(), 1000);
        this.updateClock();
        this.fetchWeather();
        this.weatherTimer = setInterval(() => this.fetchWeather(), 5 * 60 * 1000);
        this.initWebSocket();
    },
};

document.addEventListener('DOMContentLoaded', () => Overlay.init());
```

- [ ] **Step 3: Build and verify**

Run:
```bash
npm run build
```

Visit `/overlay`. Verify clock updates, WebSocket connects (check browser console), and state transitions work when metrics arrive.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add overlay state machine and WebSocket integration

Four-state machine (video-live, no-video, offline, port) driven by
metrics freshness and speed. DOM updates for speed/heading/depth,
status pill, coordinates, weather, clock."
```

---

### Task 15: Overlay Map Integration with Leaflet

**Files:**
- Modify: `resources/js/overlay.js`
- Modify: `resources/css/overlay.css`

- [ ] **Step 1: Add map initialization to overlay.js**

Add map methods to the Overlay object:

```javascript
// Add to Overlay object:
maps: { pip: null, full: null },
trackPoints: [],
boatMarker: null,
trackLine: null,

initMaps() {
    const tileUrl = window.scarletConfig?.tileUrl || '/openseamap/{z}/{x}/{y}';

    this.maps.pip = L.map('map-pip', {
        zoomControl: false,
        attributionControl: false,
        dragging: false,
        scrollWheelZoom: false,
    }).setView([0, 0], 14);

    this.maps.full = L.map('map-full', {
        zoomControl: false,
        attributionControl: false,
    }).setView([0, 0], 14);

    [this.maps.pip, this.maps.full].forEach(map => {
        L.tileLayer(tileUrl, { maxZoom: 18 }).addTo(map);
    });
},

updateMap(gps, boat) {
    if (!gps?.latitude || !gps?.longitude) return;

    const pos = [gps.latitude, gps.longitude];
    const speed = boat?.speed_sog ?? 0;
    const heading = boat?.heading ?? 0;

    this.trackPoints.push({ pos, speed });

    // Update or create boat markers (one per map)
    const boatIcon = L.divIcon({
        className: 'boat-marker',
        html: `<svg width="24" height="24" viewBox="0 0 24 24" style="transform: rotate(${heading}deg)">
            <polygon points="12,2 20,20 12,16 4,20" fill="oklch(0.54 0.22 27)" stroke="oklch(0.96 0.005 70)" stroke-width="1"/>
        </svg>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12],
    });

    if (!this.boatMarkerPip) {
        this.boatMarkerPip = L.marker(pos, { icon: boatIcon }).addTo(this.maps.pip);
        this.boatMarkerFull = L.marker(pos, { icon: boatIcon }).addTo(this.maps.full);
    } else {
        this.boatMarkerPip.setLatLng(pos).setIcon(boatIcon);
        this.boatMarkerFull.setLatLng(pos).setIcon(boatIcon);
    }

    // Center PiP map on boat (always follows)
    this.maps.pip.setView(pos);

    // Draw speed-colored track
    this.updateTrack();
},

speedToColor(speed) {
    // 0kn = blue, 5kn = green, 10kn = scarlet
    const ratio = Math.min(speed / 10, 1);
    if (ratio <= 0.5) {
        // Blue to green
        const t = ratio * 2;
        return `oklch(${0.55 + t * 0.07} ${0.14 + t * 0.01} ${240 - t * 85})`;
    }
    // Green to scarlet
    const t = (ratio - 0.5) * 2;
    return `oklch(${0.62 - t * 0.08} ${0.15 + t * 0.07} ${155 - t * 128})`;
},

updateTrack() {
    // Remove old track segments
    if (this.trackLine) {
        this.trackLine.forEach(seg => seg.remove());
    }
    this.trackLine = [];

    for (let i = 1; i < this.trackPoints.length; i++) {
        const segment = L.polyline(
            [this.trackPoints[i - 1].pos, this.trackPoints[i].pos],
            { color: this.speedToColor(this.trackPoints[i].speed), weight: 3, opacity: 0.8 }
        );
        segment.addTo(this.maps.pip);
        segment.addTo(this.maps.full);
        this.trackLine.push(segment);
    }
},
```

Update `init()` to call `this.initMaps()` and update `onMetrics()` to call `this.updateMap(data.gps, data.boat)`.

- [ ] **Step 2: Add map CSS to overlay.css**

```css
#map-pip {
    position: absolute;
    top: 10px;
    left: 10px;
    width: 240px;
    height: 175px;
    border-radius: var(--radius);
    overflow: hidden;
    z-index: 10;
}

#map-full {
    position: absolute;
    top: 0;
    left: 0;
    width: 1920px;
    height: 1080px;
    z-index: 1;
    display: none;
}

[data-state="no-video"] #map-full,
[data-state="offline"] #map-full,
[data-state="port"] #map-full {
    display: block;
}

[data-state="no-video"] #map-pip,
[data-state="offline"] #map-pip,
[data-state="port"] #map-pip {
    display: none;
}

/* State 3: Offline — dimmed map, faded track/boat */
[data-state="offline"] #map-full {
    opacity: 0.6;
}
[data-state="offline"] .leaflet-overlay-pane {
    opacity: 0.5;
}
[data-state="offline"] .boat-marker {
    opacity: 0.5;
}
[data-state="offline"] .weather-pills {
    opacity: 0.5;
}

#offline-card {
    display: none;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 100;
    background: oklch(0.08 0.008 40 / 0.85);
    backdrop-filter: blur(24px);
    border-radius: 10px;
    padding: 24px 32px;
    text-align: center;
    color: var(--text-bright);
    font-size: 16px;
}
[data-state="offline"] #offline-card {
    display: block;
}

/* State 4: Port — hide track, no heading rotation on marker */
[data-state="port"] .leaflet-overlay-pane {
    display: none;
}

.boat-marker {
    background: none !important;
    border: none !important;
}
```

- [ ] **Step 3: Build and verify**

Run:
```bash
npm run build
```

Visit `/overlay`. Verify PiP map renders top-left, boat marker appears when metrics arrive, track draws with speed coloring.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add Leaflet map to overlay with speed-colored track

PiP map (state 1) and full-screen map (states 2-4), boat marker
with heading rotation, speed-colored track polyline
(blue 0kn → green 5kn → scarlet 10kn)."
```

---

## Phase 5: Public Dashboard

### Task 16: Public Dashboard with Map and Floating Clusters

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Create: `resources/js/Pages/Public/Dashboard.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Install Leaflet for Vue**

Run:
```bash
npm install leaflet
```

- [ ] **Step 2: Create DashboardController**

Create `app/Http/Controllers/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;
use App\Services\MetricsService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(MetricsService $metrics)
    {
        return Inertia::render('Public/Dashboard', [
            'initialMetrics' => $metrics->getAllMetrics(),
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => BoatSetting::getValue('passage_from', ''),
            'passageTo' => BoatSetting::getValue('passage_to', ''),
            'portName' => BoatSetting::getValue('port_name', ''),
            'tileUrl' => '/openseamap/{z}/{x}/{y}',
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
        ]);
    }
}
```

- [ ] **Step 3: Add dashboard route**

In `routes/web.php`:

```php
use App\Http\Controllers\DashboardController;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/', fn () => redirect('/dashboard'));
```

- [ ] **Step 4: Build Dashboard.vue**

Create `resources/js/Pages/Public/Dashboard.vue`. This is the full-viewport map with floating dark chrome clusters from mockup `11-public-dashboard-map.html`.

Key structure:
- Full viewport Leaflet map (no Inertia layout — standalone page)
- Four-corner floating clusters using the dark chrome design language
- WebSocket connection for live updates
- Speed-colored track and boat marker (same logic as overlay)

```vue
<template>
    <div class="dashboard">
        <div ref="mapContainer" class="map-container"></div>

        <!-- Top-left: LIVE badge + map controls -->
        <div class="cluster cluster-tl">
            <div class="live-badge"><span class="live-dot"></span> LIVE</div>
            <div class="map-controls">
                <button @click="zoomIn" class="map-btn">+</button>
                <button @click="zoomOut" class="map-btn">−</button>
                <button @click="recentre" class="map-btn recentre-btn" title="Re-centre on boat">⌖</button>
            </div>
        </div>

        <!-- Top-right: Weather pills -->
        <div class="cluster cluster-tr">
            <div class="weather-hero" v-if="weather">
                {{ weather.temp }}°C {{ weatherCondition }}
            </div>
            <div class="weather-pill">Sea {{ weather?.seaTemp ?? '--' }}°C</div>
            <div class="weather-pill">Wind {{ weather?.wind?.speed ?? '--' }} kn</div>
            <div class="weather-pill">Waves {{ weather?.waves?.height ?? '--' }} m</div>
        </div>

        <!-- Bottom-left: Coordinates + speed legend -->
        <div class="cluster cluster-bl">
            <div class="coord-badge">{{ coordText }}</div>
            <div class="speed-legend">
                <div class="legend-gradient"></div>
                <div class="legend-labels"><span>0 kn</span><span>5 kn</span><span>10 kn</span></div>
            </div>
        </div>

        <!-- Bottom-right: Sailing instruments -->
        <div class="cluster cluster-br">
            <div class="instrument-pill">
                <span class="instr-label">Apparent Wind</span>
                <span class="instr-value">{{ fmt(boat?.wind_speed_apparent) }} kn / {{ fmt(boat?.wind_angle_apparent, 0) }}°</span>
            </div>
            <div class="instrument-pill">
                <span class="instr-label">Heel</span>
                <span class="instr-value">{{ fmt(boat?.heel, 0) }}°</span>
            </div>
            <div class="instrument-pill">
                <span class="instr-label">Pressure</span>
                <span class="instr-value">{{ fmt(boat?.pressure, 0) }} hPa</span>
            </div>
            <div class="instrument-pill">
                <span class="instr-label">Trip</span>
                <span class="instr-value">{{ fmt(boat?.trip_log) }} nm</span>
            </div>
        </div>

        <!-- Lower third -->
        <div class="lower-third">
            <div class="brand-tab">{{ boatName }}</div>
            <div class="lt-metric"><span class="lt-label">Speed</span><span class="lt-value">{{ fmt(boat?.speed_sog) }} kn</span></div>
            <div class="lt-metric"><span class="lt-label">Heading</span><span class="lt-value">{{ fmt(boat?.heading, 0) }}°</span></div>
            <div class="lt-metric"><span class="lt-label">Depth</span><span class="lt-value">{{ fmt(boat?.depth) }} m</span></div>
            <div class="lt-status" :class="statusClass">{{ statusText }}</div>
            <div class="lt-passage" v-if="passageFrom && passageTo">{{ passageFrom }} → {{ passageTo }}</div>
            <div class="lt-clock">{{ clock }}</div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    initialMetrics: Object,
    boatName: String,
    passageFrom: String,
    passageTo: String,
    portName: String,
    tileUrl: String,
    reverb: Object,
    reverbKey: String,
});

const mapContainer = ref(null);
const boat = ref(props.initialMetrics?.boat ?? {});
const gps = ref(props.initialMetrics?.gps ?? {});
const weather = ref(null);
const clock = ref('--:--');
let map = null;
let boatMarker = null;
let trackSegments = [];
let trackPoints = [];
let clockInterval = null;
let weatherInterval = null;
let userPanned = false;

const fmt = (val, decimals = 1) => val != null ? Number(val).toFixed(decimals) : '--';

const coordText = computed(() => {
    const lat = gps.value?.latitude;
    const lon = gps.value?.longitude;
    if (!lat || !lon) return '--';
    return `${lat.toFixed(4)}°N ${Math.abs(lon).toFixed(4)}°${lon < 0 ? 'W' : 'E'}`;
});

const statusText = computed(() => {
    if (boat.value?.speed_sog === 0 && props.portName) return 'In Port';
    if (boat.value?.engine_rpm > 0) return 'Under Power';
    return 'Under Sail';
});

const statusClass = computed(() => {
    if (statusText.value === 'In Port') return 'status-port';
    if (statusText.value === 'Under Power') return 'status-power';
    return 'status-sail';
});

const weatherCondition = computed(() => ''); // Derive from WMO code if needed

function speedToColor(speed) {
    const ratio = Math.min(speed / 10, 1);
    if (ratio <= 0.5) {
        const t = ratio * 2;
        return `oklch(${0.55 + t * 0.07} ${0.14 + t * 0.01} ${240 - t * 85})`;
    }
    const t = (ratio - 0.5) * 2;
    return `oklch(${0.62 - t * 0.08} ${0.15 + t * 0.07} ${155 - t * 128})`;
}

function zoomIn() { map?.zoomIn(); }
function zoomOut() { map?.zoomOut(); }
function recentre() {
    if (!gps.value?.latitude) return;
    map?.setView([gps.value.latitude, gps.value.longitude]);
    userPanned = false;
}

function updateClock() {
    const now = new Date();
    clock.value = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

async function fetchWeather() {
    try {
        const res = await fetch('/api/v1/weather');
        if (res.ok) weather.value = await res.json();
    } catch (e) { /* silent */ }
}

function updateMap(newGps, newBoat) {
    if (!newGps?.latitude || !newGps?.longitude || !map) return;

    const pos = [newGps.latitude, newGps.longitude];
    const heading = newBoat?.heading ?? 0;
    const speed = newBoat?.speed_sog ?? 0;

    trackPoints.push({ pos, speed });

    // Boat marker
    const iconHtml = `<svg width="24" height="24" viewBox="0 0 24 24" style="transform:rotate(${heading}deg)">
        <polygon points="12,2 20,20 12,16 4,20" fill="oklch(0.54 0.22 27)" stroke="oklch(0.96 0.005 70)" stroke-width="1"/>
    </svg>`;
    const icon = L.divIcon({ className: 'boat-marker', html: iconHtml, iconSize: [24, 24], iconAnchor: [12, 12] });

    if (boatMarker) {
        boatMarker.setLatLng(pos);
        boatMarker.setIcon(icon);
    } else {
        boatMarker = L.marker(pos, { icon }).addTo(map);
    }

    // Track
    if (trackPoints.length > 1) {
        const prev = trackPoints[trackPoints.length - 2];
        const seg = L.polyline([prev.pos, pos], {
            color: speedToColor(speed), weight: 3, opacity: 0.8,
        }).addTo(map);
        trackSegments.push(seg);
    }

    // Don't reset user pan/zoom
    if (!userPanned) {
        map.setView(pos);
    }
}

onMounted(() => {
    // Init map
    map = L.map(mapContainer.value, {
        zoomControl: false,
        attributionControl: false,
    }).setView([0, 0], 14);

    L.tileLayer(props.tileUrl, { maxZoom: 18 }).addTo(map);

    map.on('dragstart', () => { userPanned = true; });

    // Init with provided metrics
    if (gps.value?.latitude) {
        map.setView([gps.value.latitude, gps.value.longitude]);
        updateMap(gps.value, boat.value);
    }

    // Clock
    updateClock();
    clockInterval = setInterval(updateClock, 1000);

    // Weather
    fetchWeather();
    weatherInterval = setInterval(fetchWeather, 5 * 60 * 1000);

    // WebSocket
    if (window.Echo) {
        window.Echo.channel('metrics').listen('.metrics.updated', (data) => {
            boat.value = data.boat;
            gps.value = data.gps;
            updateMap(data.gps, data.boat);
        });
    }
});

onUnmounted(() => {
    clearInterval(clockInterval);
    clearInterval(weatherInterval);
    if (window.Echo) window.Echo.leave('metrics');
    map?.remove();
});
</script>

<style scoped>
/* Dark chrome design language from approved mockup 11-public-dashboard-map.html.
   Full styles to be translated from the mockup. Key tokens: */

.dashboard {
    width: 100vw;
    height: 100vh;
    position: relative;
    overflow: hidden;
    font-family: 'Outfit', system-ui, sans-serif;
}

.map-container {
    position: absolute;
    inset: 0;
    z-index: 1;
}

.cluster {
    position: absolute;
    z-index: 100;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.cluster-tl { top: 16px; left: 16px; }
.cluster-tr { top: 16px; right: 16px; align-items: flex-end; }
.cluster-bl { bottom: 80px; left: 16px; }
.cluster-br { bottom: 80px; right: 16px; align-items: flex-end; }

.live-badge, .weather-hero, .weather-pill, .coord-badge,
.instrument-pill, .map-btn {
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 10px;
    color: oklch(0.96 0.005 70);
    font-variant-numeric: tabular-nums;
}

.live-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 700;
}

.live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: oklch(0.78 0.12 155);
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.lower-third {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    z-index: 100;
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 0 20px;
    color: oklch(0.96 0.005 70);
}

.brand-tab {
    background: oklch(0.54 0.22 27);
    padding: 8px 20px;
    font-weight: 700;
    font-size: 16px;
    border-radius: 7px;
}

.lt-label { font-size: 10px; color: oklch(0.62 0.008 70); text-transform: uppercase; display: block; }
.lt-value { font-size: 16px; font-weight: 600; font-variant-numeric: tabular-nums; }

.lt-status { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.status-sail { background: oklch(0.78 0.12 155 / 0.2); color: oklch(0.78 0.12 155); }
.status-power { background: oklch(0.75 0.10 70 / 0.2); color: oklch(0.75 0.10 70); }
.status-port { background: oklch(0.72 0.08 230 / 0.2); color: oklch(0.72 0.08 230); }

.lt-passage { flex: 1; text-align: center; font-size: 14px; color: oklch(0.75 0.008 70); }
.lt-clock { font-size: 20px; font-weight: 600; font-variant-numeric: tabular-nums; margin-left: auto; }

.boat-marker { background: none !important; border: none !important; }

/* Additional styles from mockup 11-public-dashboard-map.html to be translated. */
</style>
```

- [ ] **Step 5: Build and verify**

Run:
```bash
npm run build
```

Visit `/dashboard`. Verify: map fills viewport, floating clusters render in four corners, lower third spans bottom, WebSocket updates move the boat marker.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add public dashboard with map and floating clusters

DashboardController serving initial metrics. Dashboard.vue with
full-viewport Leaflet map, four-corner dark chrome clusters,
lower third bar, speed-colored track, live WebSocket updates.
Re-centre button preserves zoom level."
```

---

## Phase 6: Final Integration

### Task 17: Clean Up and Final Wiring

**Files:**
- Modify: `routes/web.php` (final route cleanup)
- Modify: `.env.example`
- Remove: old overlay references

- [ ] **Step 1: Clean up routes**

Finalize `routes/web.php`:
- Remove old `/` route (now redirects to `/dashboard`)
- Remove `/snow` route if no longer needed
- Ensure all routes are properly grouped and named

- [ ] **Step 2: Update .env.example**

Add new variables, remove ThingsBoard GPS variables:

```
# Remove:
GPS_ENDPOINT=
GPS_DEVICE=
GPS_USERNAME=
GPS_PASSWORD=

# Session now uses database:
SESSION_DRIVER=database
```

- [ ] **Step 3: Update config/scarlet.php**

Remove the `gps` section (ThingsBoard config) from `config/scarlet.php`. The GPS data now comes from Prometheus.

- [ ] **Step 4: Run full test suite**

Run:
```bash
php artisan migrate:fresh --seed
npm run build
php artisan test
```

Expected: All tests PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: clean up routes, remove ThingsBoard GPS config

Final route structure, updated .env.example, removed old GPS
integration in favor of Prometheus."
```

---

## Notes for Implementation

### Prometheus Metric Names

The metric names in this plan (e.g., `scarlet_boat_speed_kn`, `scarlet_signalk_navigation_speed_through_water_kn`) are best guesses based on the OTel collector configuration namespace `scarlet`. The implementing agent should:

1. Query the Prometheus instance to discover actual metric names: `curl http://prometheus:9090/api/v1/label/__name__/values`
2. Update the queries in `MetricsService.php` to match the actual names
3. This is a one-time discovery step that should happen early in Phase 2

### Design Mockup Translation

Each Vue component references an approved mockup HTML file. The implementing agent should:

1. Read the referenced mockup file
2. Translate the HTML structure to Vue template syntax
3. Convert inline styles and `<style>` blocks to scoped styles or Tailwind classes
4. Replace static values with dynamic bindings from props/reactive data

### Leaflet Tile Server

The existing `MapTileController` and `OpenSeaMapService` handle tile serving at `/openseamap/{z}/{x}/{y}`. This is used by both the overlay and public dashboard. No changes needed.

### WebAuthn Browser Compatibility

WebAuthn (passkeys) requires:
- HTTPS in production (localhost works for development)
- A modern browser (Chrome 67+, Firefox 60+, Safari 14+)
- The `laragear/webauthn` package handles server-side ceremony; the browser's `navigator.credentials` API handles client-side

If `laragear/webauthn` does not support Laravel 12 at implementation time, alternatives:
1. Use `web-auth/webauthn-lib` directly (more code, same result)
2. Defer passkey support to a follow-up PR
