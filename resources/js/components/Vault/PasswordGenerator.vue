<template>
  <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
    <h4 class="mb-3 text-sm font-medium text-gray-700">Password Generator</h4>

    <!-- Generated Password Display -->
    <div class="mb-3">
      <div class="relative">
        <input
          v-model="generatedPassword"
          type="text"
          readonly
          class="w-full rounded-md border border-gray-300 bg-white px-4 py-2 pr-10 font-mono text-sm"
          placeholder="Click Generate to create a password"
        />
        <button
          v-if="generatedPassword"
          type="button"
          @click="copyToClipboard"
          class="absolute inset-y-0 right-0 flex items-center pr-3 text-blue-600 hover:text-blue-800"
          title="Copy password"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
            />
          </svg>
        </button>
      </div>
    </div>

    <!-- Length Slider -->
    <div class="mb-3">
      <label class="mb-1 flex items-center justify-between text-sm text-gray-700">
        <span>Length</span>
        <span class="font-medium">{{ length }}</span>
      </label>
      <input
        v-model.number="length"
        type="range"
        min="8"
        max="64"
        class="w-full"
      />
    </div>

    <!-- Options -->
    <div class="mb-3 space-y-2">
      <label class="flex items-center">
        <input
          v-model="options.lowercase"
          type="checkbox"
          class="mr-2 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        />
        <span class="text-sm text-gray-700">Lowercase (a-z)</span>
      </label>
      <label class="flex items-center">
        <input
          v-model="options.uppercase"
          type="checkbox"
          class="mr-2 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        />
        <span class="text-sm text-gray-700">Uppercase (A-Z)</span>
      </label>
      <label class="flex items-center">
        <input
          v-model="options.numbers"
          type="checkbox"
          class="mr-2 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        />
        <span class="text-sm text-gray-700">Numbers (0-9)</span>
      </label>
      <label class="flex items-center">
        <input
          v-model="options.symbols"
          type="checkbox"
          class="mr-2 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        />
        <span class="text-sm text-gray-700">Symbols (!@#$...)</span>
      </label>
    </div>

    <!-- Actions -->
    <div class="flex space-x-2">
      <button
        type="button"
        @click="generatePassword"
        class="flex-1 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
      >
        Generate
      </button>
      <button
        v-if="generatedPassword"
        type="button"
        @click="usePassword"
        class="flex-1 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
      >
        Use This Password
      </button>
    </div>

    <!-- Copy Notification -->
    <div
      v-if="showCopiedMessage"
      class="mt-2 text-center text-xs text-green-600"
    >
      Copied to clipboard!
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { generatePassword } from '@/services/cryptoService';

const emit = defineEmits<{
  generated: [password: string];
}>();

const length = ref(20);
const options = ref({
  lowercase: true,
  uppercase: true,
  numbers: true,
  symbols: true,
});

const generatedPassword = ref('');
const showCopiedMessage = ref(false);

function generateNewPassword() {
  generatedPassword.value = generatePassword(length.value, options.value);
}

function usePassword() {
  emit('generated', generatedPassword.value);
}

async function copyToClipboard() {
  if (!generatedPassword.value) return;

  try {
    await navigator.clipboard.writeText(generatedPassword.value);
    showCopiedMessage.value = true;
    setTimeout(() => {
      showCopiedMessage.value = false;
    }, 2000);
  } catch (err) {
    console.error('Failed to copy:', err);
  }
}
</script>
