<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, Mail } from 'lucide-vue-next';
import { ref } from 'vue';

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <teleport to="head">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500&display=swap"
            rel="stylesheet"
        />
    </teleport>

    <div class="root">
        <div class="card">
            <div class="left-panel">
                <div class="brand">
                    <div class="brand-icon">
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#ffffff"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <path d="M3 9h18M9 21V9" />
                        </svg>
                    </div>
                    <span class="brand-name">ForU Tex</span>
                </div>

                <h1 class="login-heading">
                    Bienvenido<br />
                    de nuevo
                </h1>
                <p class="login-sub">Ingresa tus credenciales para continuar</p>

                <form @submit.prevent="submit">
                    <div class="field">
                        <label class="field-label" for="email">Correo electrónico</label>
                        <div class="field-wrap">
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="field-input"
                                :class="{ 'has-error': form.errors.email }"
                                placeholder="usuario@empresa.com"
                                autocomplete="username"
                                required
                            />
                            <span class="field-icon">
                                <Mail :size="15" />
                            </span>
                        </div>
                        <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="password">Contraseña</label>
                        <div class="field-wrap">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                class="field-input"
                                :class="{ 'has-error': form.errors.password }"
                                placeholder="••••••••"
                                autocomplete="current-password"
                                required
                            />
                            <button
                                type="button"
                                class="field-icon-btn"
                                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                @click="showPassword = !showPassword"
                            >
                                <Eye v-if="!showPassword" :size="15" />
                                <EyeOff v-else :size="15" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
                    </div>

                    <div class="row-aux">
                        <label class="remember">
                            <input v-model="form.remember" type="checkbox" />
                            <span>Recuérdame</span>
                        </label>

                        <a :href="route('password.request')" class="forgot"> ¿Olvidaste tu contraseña? </a>
                    </div>

                    <button type="submit" class="btn-submit" :disabled="form.processing">Iniciar sesión</button>
                </form>
            </div>

            <div class="right-panel">
                <div class="blob-1"></div>
                <div class="blob-2"></div>
                <div class="geo-1"></div>
                <div class="geo-2"></div>
                <div class="accent-dot"></div>

                <div class="right-content">
                    <p class="right-eyebrow">Software de gestión</p>
                    <h2 class="right-title">
                        ForU<br />
                        Tex
                    </h2>
                    <div class="right-divider"></div>
                    <p class="right-phrase">Gestión empresarial profesional, clara y sin fricciones.</p>
                    <p class="right-ver">v1.0</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
* {
    box-sizing: border-box;
}

.root {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e8e4dd;
    padding: 2rem;
    font-family: 'Inter', sans-serif;
}

.card {
    display: grid;
    grid-template-columns: 1fr 1fr;
    width: 100%;
    max-width: 900px;
    min-height: 540px;
    border-radius: 22px;
    overflow: hidden;
    box-shadow:
        0 2px 6px rgba(0, 0, 0, 0.06),
        0 12px 48px rgba(0, 0, 0, 0.13);
}

@media (max-width: 768px) {
    .card {
        grid-template-columns: 1fr;
    }

    .right-panel {
        display: none;
    }

    .left-panel {
        padding: 36px 28px;
    }
}

/* PANEL IZQUIERDO */
.left-panel {
    background: #ffffff;
    padding: 54px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.brand {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 42px;
}

.brand-icon {
    width: 32px;
    height: 32px;
    background: #111827;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.brand-name {
    font-family: 'Syne', sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: #111827;
}

.login-heading {
    font-family: 'Syne', sans-serif;
    font-size: 30px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.15;
    letter-spacing: -0.5px;
    margin: 0 0 8px;
}

.login-sub {
    font-size: 14px;
    color: #94a3b8;
    margin: 0 0 38px;
}

.field {
    margin-bottom: 18px;
}

.field-label {
    display: block;
    font-size: 11px;
    font-weight: 500;
    color: #64748b;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 7px;
}

.field-wrap {
    position: relative;
}

.field-input {
    width: 100%;
    height: 44px;
    padding: 0 42px 0 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    color: #0f172a;
    background: #f8fafc;
    outline: none;
    transition:
        border-color 0.15s,
        box-shadow 0.15s,
        background 0.15s;
}

.field-input:focus {
    border-color: #0f172a;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.07);
}

.field-input::placeholder {
    color: #cbd5e1;
}

.field-input.has-error {
    border-color: #ef4444;
}

.field-error {
    font-size: 12px;
    color: #ef4444;
    margin-top: 5px;
}

.field-icon {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: #cbd5e1;
    display: flex;
    align-items: center;
    pointer-events: none;
}

.field-icon-btn {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    display: flex;
    align-items: center;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    transition: color 0.1s;
}

.field-icon-btn:hover {
    color: #0f172a;
}

.row-aux {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 4px 0 28px;
}

.remember {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #64748b;
    cursor: pointer;
}

.remember input[type='checkbox'] {
    width: 15px;
    height: 15px;
    accent-color: #0f172a;
    cursor: pointer;
}

.forgot {
    font-size: 13px;
    color: #0f172a;
    font-weight: 500;
    text-decoration: none;
    opacity: 0.7;
    transition: opacity 0.15s;
}

.forgot:hover {
    opacity: 1;
}

.btn-submit {
    width: 100%;
    height: 46px;
    background: #0f172a;
    color: #ffffff;
    border: none;
    border-radius: 10px;
    font-family: 'Syne', sans-serif;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.3px;
    cursor: pointer;
    transition: background 0.15s;
}

.btn-submit:hover:not(:disabled) {
    background: #1e293b;
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* PANEL DERECHO */
.right-panel {
    background: #111827;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 48px 44px;
}

.blob-1 {
    position: absolute;
    top: -120px;
    right: -100px;
    width: 420px;
    height: 420px;
    background: #1e3a5f;
    border-radius: 60% 40% 55% 45% / 50% 60% 40% 50%;
    opacity: 0.7;
    pointer-events: none;
}

.blob-2 {
    position: absolute;
    top: 80px;
    left: -60px;
    width: 200px;
    height: 200px;
    background: #162d4a;
    border-radius: 40% 60% 45% 55% / 55% 45% 60% 40%;
    opacity: 0.5;
    pointer-events: none;
}

.geo-1 {
    position: absolute;
    bottom: 120px;
    right: -40px;
    width: 160px;
    height: 160px;
    border: 1.5px solid rgba(255, 255, 255, 0.06);
    border-radius: 24px;
    transform: rotate(20deg);
    pointer-events: none;
}

.geo-2 {
    position: absolute;
    bottom: 80px;
    right: 10px;
    width: 90px;
    height: 90px;
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: 16px;
    transform: rotate(20deg);
    pointer-events: none;
}

.accent-dot {
    position: absolute;
    top: 52px;
    left: 44px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #3b82f6;
    opacity: 0.85;
    pointer-events: none;
}

.right-content {
    position: relative;
    z-index: 1;
}

.right-eyebrow {
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.25);
    margin: 0 0 14px;
}

.right-title {
    font-family: 'Syne', sans-serif;
    font-size: 46px;
    font-weight: 800;
    color: #ffffff;
    line-height: 0.92;
    letter-spacing: -2px;
    margin: 0 0 28px;
}

.right-divider {
    width: 32px;
    height: 2px;
    background: #3b82f6;
    border-radius: 2px;
    margin-bottom: 20px;
    opacity: 0.8;
}

.right-phrase {
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 300;
    color: rgba(255, 255, 255, 0.5);
    line-height: 1.65;
    max-width: 220px;
    margin: 0 0 36px;
}

.right-ver {
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.15);
}
</style>
