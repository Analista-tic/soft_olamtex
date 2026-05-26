<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, Mail } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    token: string;
    email: string;
}

const props = defineProps<Props>();

const showPassword = ref(false);
const showConfirm = ref(false);

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

// Fortaleza de contraseña: solo visual, sin lógica de backend.
const passwordStrength = computed(() => {
    const p = form.password;
    if (!p) return 0;

    let score = 0;
    if (p.length >= 8) score++;
    if (/[A-Z]/.test(p)) score++;
    if (/[0-9]/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;

    return score;
});

const strengthLabel = computed(() => {
    const labels = ['', 'Débil', 'Regular', 'Media', 'Fuerte'];
    return labels[passwordStrength.value] ?? '';
});

const strengthColor = computed(() => {
    const colors = ['#e2e8f0', '#ef4444', '#f97316', '#eab308', '#22c55e'];
    return colors[passwordStrength.value] ?? '#e2e8f0';
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <teleport to="head">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link
            href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500&display=swap"
            rel="stylesheet"
        />
    </teleport>

    <div class="root">
        <div class="card">
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
                Nueva<br />
                contraseña
            </h1>
            <p class="login-sub">Crea una contraseña segura para tu cuenta. Debe tener al menos 8 caracteres.</p>

            <form @submit.prevent="submit">
                <div class="field">
                    <label class="field-label" for="email">Correo electrónico</label>
                    <div class="field-wrap">
                        <input id="email" type="email" class="field-input is-readonly" :value="form.email" readonly tabindex="-1" />
                        <span class="field-icon">
                            <Mail :size="15" />
                        </span>
                    </div>
                    <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                </div>

                <div class="field">
                    <label class="field-label" for="password">Nueva contraseña</label>
                    <div class="field-wrap">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="field-input"
                            :class="{ 'has-error': form.errors.password }"
                            placeholder="Mínimo 8 caracteres"
                            autocomplete="new-password"
                            data-no-uppercase
                            autofocus
                        />
                        <button
                            type="button"
                            class="field-icon-btn"
                            :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" :size="15" />
                            <Eye v-else :size="15" />
                        </button>
                    </div>

                    <div class="strength-bar">
                        <div
                            v-for="i in 4"
                            :key="i"
                            class="strength-seg"
                            :style="{ background: i <= passwordStrength ? strengthColor : '#e2e8f0' }"
                        />
                    </div>
                    <p v-if="form.password" class="field-hint">Fortaleza: {{ strengthLabel }}</p>
                    <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
                </div>

                <div class="field">
                    <label class="field-label" for="password_confirmation">Confirmar contraseña</label>
                    <div class="field-wrap">
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="showConfirm ? 'text' : 'password'"
                            class="field-input"
                            :class="{ 'has-error': form.errors.password_confirmation }"
                            placeholder="Repite tu contraseña"
                            autocomplete="new-password"
                            data-no-uppercase
                        />
                        <button
                            type="button"
                            class="field-icon-btn"
                            :aria-label="showConfirm ? 'Ocultar confirmación' : 'Mostrar confirmación'"
                            @click="showConfirm = !showConfirm"
                        >
                            <EyeOff v-if="showConfirm" :size="15" />
                            <Eye v-else :size="15" />
                        </button>
                    </div>
                    <p v-if="form.errors.password_confirmation" class="field-error">{{ form.errors.password_confirmation }}</p>
                </div>

                <button type="submit" class="btn-submit" :disabled="form.processing">Restablecer contraseña</button>
            </form>

            <a :href="route('login')" class="back-link">
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M19 12H5M12 5l-7 7 7 7" />
                </svg>
                Volver al inicio de sesión
            </a>
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
    background: #ffffff;
    width: 100%;
    max-width: 420px;
    border-radius: 22px;
    padding: 52px 48px;
    box-shadow:
        0 2px 6px rgba(0, 0, 0, 0.06),
        0 12px 48px rgba(0, 0, 0, 0.11);
    display: flex;
    flex-direction: column;
}

@media (max-width: 480px) {
    .card {
        padding: 36px 28px;
    }
}

.brand {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 40px;
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
    font-size: 26px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.15;
    letter-spacing: -0.5px;
    margin: 0 0 8px;
}

.login-sub {
    font-size: 13.5px;
    color: #94a3b8;
    line-height: 1.6;
    margin: 0 0 36px;
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

.field-input.is-readonly {
    color: #94a3b8;
    cursor: not-allowed;
    background: #f1f5f9;
}

.field-error {
    font-size: 12px;
    color: #ef4444;
    margin-top: 5px;
}

.field-hint {
    font-size: 11.5px;
    color: #94a3b8;
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

.strength-bar {
    display: flex;
    gap: 4px;
    margin-top: 8px;
}

.strength-seg {
    height: 3px;
    flex: 1;
    border-radius: 2px;
    background: #e2e8f0;
    transition: background 0.2s;
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
    margin-top: 8px;
    transition: background 0.15s;
}

.btn-submit:hover:not(:disabled) {
    background: #1e293b;
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.back-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 28px;
    font-size: 13px;
    color: #64748b;
    text-decoration: none;
    transition: color 0.15s;
}

.back-link:hover {
    color: #0f172a;
}
</style>
