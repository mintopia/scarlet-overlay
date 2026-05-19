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
.remember-row { justify-content: space-between; }
.remember { display: flex; align-items: center; gap: 8px; font-size: 13px; color: oklch(0.45 0.005 40); cursor: pointer; }
.remember input { width: 16px; height: 16px; accent-color: oklch(0.54 0.22 27); cursor: pointer; }
.forgot-link { font-size: 13px; color: oklch(0.54 0.22 27); text-decoration: none; }
.forgot-link:hover { text-decoration: underline; }

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
