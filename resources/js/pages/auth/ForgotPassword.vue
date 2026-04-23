<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

defineProps<{
  status?: string;
}>();

const form = useForm({
  email: '',
});

const submit = () => {
  form.post(route('password.email'));
};
</script>

<template>
  <teleport to="head">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
  </teleport>

  <div class="root">
    <div class="card">

      <div class="brand">
        <div class="brand-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
               stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <path d="M3 9h18M9 21V9"/>
          </svg>
        </div>
        <span class="brand-name">ForU Tex</span>
      </div>

      <h1 class="login-heading">Recuperar<br>contraseña</h1>
      <p class="login-sub">Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.</p>

      <div v-if="status" class="status-msg">{{ status }}</div>

      <form @submit.prevent="submit">
        <label class="field-label" for="email">Correo electrónico</label>
        <div class="field-wrap">
          <input
            id="email"
            type="email"
            class="field-input"
            :class="{ 'has-error': form.errors.email }"
            placeholder="usuario@empresa.com"
            v-model="form.email"
            autocomplete="email"
            autofocus
            required
          />
          <span class="field-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
          </span>
        </div>
        <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>

        <button type="submit" class="btn-submit" :disabled="form.processing">
          Enviar enlace de recuperación
        </button>
      </form>

      <a :href="route('login')" class="back-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
        Volver al inicio de sesión
      </a>

    </div>
  </div>
</template>

<style scoped>
* { box-sizing: border-box; }

.root {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #E8E4DD;
  padding: 2rem;
  font-family: 'Inter', sans-serif;
}

.card {
  background: #ffffff;
  width: 100%;
  max-width: 420px;
  border-radius: 22px;
  padding: 52px 48px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06), 0 12px 48px rgba(0,0,0,0.11);
  display: flex;
  flex-direction: column;
}

@media (max-width: 480px) {
  .card { padding: 36px 28px; }
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

.status-msg {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #15803d;
  font-size: 13px;
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 20px;
  line-height: 1.5;
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

.field-wrap { position: relative; }

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
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}

.field-input:focus {
  border-color: #0f172a;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(15,23,42,0.07);
}

.field-input::placeholder { color: #cbd5e1; }

.field-input.has-error { border-color: #ef4444; }

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
  margin-top: 24px;
  transition: background 0.15s;
}

.btn-submit:hover:not(:disabled) { background: #1e293b; }

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

.back-link:hover { color: #0f172a; }
</style>
