<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
      <h2 class="mb-4 text-xl font-bold text-gray-900">Enter Master Password</h2>
      <p class="mb-6 text-sm text-gray-600">
        Your master password is used to decrypt your vault entries. It is never sent to the server.
      </p>

      <form @submit.prevent="handleSubmit">
        <div class="mb-4">
          <label for="masterPassword" class="mb-2 block text-sm font-medium text-gray-700">
            Master Password
          </label>
          <div class="relative">
            <input
              id="masterPassword"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              class="w-full rounded-md border border-gray-300 px-4 py-2 pr-10 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="Enter your master password"
              required
              autocomplete="off"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute inset-y-0 right-0 flex items-center pr-3"
            >
              <svg
                v-if="!showPassword"
                class="h-5 w-5 text-gray-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                />
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                />
              </svg>
              <svg
                v-else
                class="h-5 w-5 text-gray-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                />
              </svg>
            </button>
          </div>
        </div>

        <div v-if="error" class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-600">
          {{ error }}
        </div>

        <div class="flex justify-end space-x-3">
          <button
            type="button"
            @click="$emit('close')"
            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="!password || loading"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {{ loading ? 'Unlocking...' : 'Unlock' }}
          </button>
        </div>
      </form>

      <div class="mt-6 border-t border-gray-200 pt-4">
        <p class="text-xs text-gray-500">
          <strong>Security Note:</strong> Your master password is never sent to the server. All
          encryption and decryption happens in your browser.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const emit = defineEmits<{
  unlock: [password: string];
  close: [];
}>();

const password = ref('');
const showPassword = ref(false);
const loading = ref(false);
const error = ref('');

async function handleSubmit() {
  if (!password.value) return;

  loading.value = true;
  error.value = '';

  try {
    emit('unlock', password.value);
  } catch (err) {
    error.value = 'Invalid master password';
  } finally {
    loading.value = false;
  }
}
</script>
