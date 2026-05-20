<template>
    <Head title="Accept Invitation" />
    <div class="register-page">
        <div class="register-card">
            <div class="brand">
                <div class="brand-name">Scarlet</div>
                <div class="brand-sub">Accept Invitation</div>
            </div>

            <form @submit.prevent="submit">
                <input type="hidden" v-model="form.token">

                <div class="form-group">
                    <label class="form-label" for="name">Name</label>
                    <input
                        class="form-input"
                        :class="{ 'form-input--error': form.errors.name }"
                        type="text"
                        id="name"
                        v-model="form.name"
                        placeholder="Your full name"
                        autofocus
                    >
                    <div v-if="form.errors.name" class="form-error">{{ form.errors.name }}</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input
                        class="form-input form-input--readonly"
                        type="email"
                        id="email"
                        :value="form.email"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input
                        class="form-input"
                        :class="{ 'form-input--error': form.errors.password }"
                        type="password"
                        id="password"
                        v-model="form.password"
                        placeholder="At least 8 characters"
                    >
                    <div v-if="form.errors.password" class="form-error">{{ form.errors.password }}</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                    <input
                        class="form-input"
                        :class="{ 'form-input--error': form.errors.password_confirmation }"
                        type="password"
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        placeholder="Repeat your password"
                    >
                    <div v-if="form.errors.password_confirmation" class="form-error">{{ form.errors.password_confirmation }}</div>
                </div>

                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Creating account…' : 'Create account' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: String,
    email: String,
});

const form = useForm({
    token: props.token,
    name: '',
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<style scoped>
.register-page {
    font-family: 'Outfit', system-ui, sans-serif;
    background: oklch(0.97 0.003 70);
    color: oklch(0.20 0.005 40);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.register-card {
    width: 100%;
    max-width: 380px;
    background: var(--color-surface);
    border-radius: 14px;
    padding: 40px 36px 36px;
    box-shadow: 0 1px 3px oklch(0.05 0.008 40 / 0.04), 0 8px 32px oklch(0.05 0.008 40 / 0.06);
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
    box-sizing: border-box;
}
.form-input:focus {
    border-color: oklch(0.54 0.22 27);
    box-shadow: 0 0 0 3px oklch(0.54 0.22 27 / 0.08);
}
.form-input--error { border-color: oklch(0.55 0.20 27); }
.form-input--readonly {
    color: oklch(0.55 0.005 40);
    cursor: not-allowed;
}
.form-error { font-size: 12px; color: oklch(0.50 0.20 27); margin-top: 4px; }
.form-input::placeholder { color: oklch(0.65 0.005 40); }

.btn {
    width: 100%; height: 46px; display: flex; align-items: center; justify-content: center; gap: 8px;
    font-family: 'Outfit', system-ui, sans-serif; font-size: 14px; font-weight: 600;
    border: none; border-radius: 7px; cursor: pointer; transition: background 0.15s;
    margin-top: 8px;
}
.btn-primary {
    background: oklch(0.54 0.22 27); color: oklch(0.98 0 0);
    box-shadow: 0 1px 3px oklch(0.54 0.22 27 / 0.3);
}
.btn-primary:hover { background: oklch(0.48 0.22 27); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
